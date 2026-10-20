<?php

namespace App\Noyau\Services;

use App\Noyau\Exceptions\ConnexionRefusee;
use App\Noyau\Mail\LienConnexion;
use App\Noyau\Models\JetonConnexion;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Sms\FournisseurSms;
use App\Parametres\Services\Parametres;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Connexion : téléphone + mot de passe, lien envoyé par e-mail, ou code SMS (si le SMS est activé).
 * Aucun code fixe, aucun contournement : chaque secret est tiré au hasard, ne sert qu'une fois et expire.
 */
class Connexion
{
    public const ESSAIS_MAX = 5;

    public const PAUSE_SECONDES = 300;

    public const VALIDITE_LIEN_MINUTES = 15;

    public const VALIDITE_CODE_MINUTES = 10;

    public function __construct(private FournisseurSms $sms) {}

    /** Le SMS est désactivé par défaut (réglage en base `sms.actif`) et exige un fournisseur réellement configuré. */
    public function smsActif(): bool
    {
        return Parametres::obtenir('sms.actif', '0') === '1' && $this->sms->estConfigure();
    }

    public function parMotDePasse(string $telephone, string $motDePasse, string $ip): Utilisateur
    {
        $telephone = Telephone::normaliser($telephone);
        $cle = $this->verrouiller($telephone, $ip);

        $utilisateur = Utilisateur::where('telephone', $telephone)->first();
        // Même durée de calcul que le compte existe ou non : on ne révèle pas quels numéros ont un compte.
        $bon = Hash::check($motDePasse, $utilisateur?->mot_de_passe ?? self::empreinteFactice()) && $utilisateur?->mot_de_passe !== null;

        if (! $bon) {
            RateLimiter::hit($cle, self::PAUSE_SECONDES);
            throw new ConnexionRefusee('Téléphone ou mot de passe incorrect.');
        }

        return $this->ouvrirSession($utilisateur, $cle);
    }

    /** Envoie un lien à l'e-mail du compte. Réponse toujours identique : on ne révèle pas si le compte existe. */
    public function envoyerLienEmail(string $telephone, string $ip): void
    {
        $telephone = Telephone::normaliser($telephone);
        RateLimiter::hit($this->cle('lien', $telephone, $ip), 600);
        if (RateLimiter::tooManyAttempts($this->cle('lien', $telephone, $ip), 3)) {
            throw new ConnexionRefusee('Trop de demandes. Réessayez dans quelques minutes.');
        }

        $utilisateur = Utilisateur::where('telephone', $telephone)->first();
        if ($utilisateur === null || ! $utilisateur->estActif()) {
            return;
        }

        $jeton = $this->creerJeton($utilisateur, 'lien_email', Str::random(48), self::VALIDITE_LIEN_MINUTES);
        Courriel::envoyer($utilisateur->email, new LienConnexion($utilisateur->nom, route('connexion.lien', $jeton), self::VALIDITE_LIEN_MINUTES));
    }

    public function lienValide(string $secret): ?JetonConnexion
    {
        return JetonConnexion::where('type', 'lien_email')
            ->where('empreinte', $this->empreinte($secret))
            ->whereNull('utilise_le')
            ->where('expire_le', '>', now())
            ->first();
    }

    public function consommerLien(string $secret): Utilisateur
    {
        $jeton = $this->lienValide($secret);
        if ($jeton === null) {
            throw new ConnexionRefusee('Ce lien n\'est plus valable. Demandez-en un nouveau.');
        }

        // Une seule utilisation, même si deux personnes cliquent en même temps.
        $modifie = JetonConnexion::whereKey($jeton->getKey())->whereNull('utilise_le')->update(['utilise_le' => now()]);
        if ($modifie !== 1) {
            throw new ConnexionRefusee('Ce lien n\'est plus valable. Demandez-en un nouveau.');
        }

        $utilisateur = Utilisateur::findOrFail($jeton->utilisateur_id);
        if (! $utilisateur->estActif()) {
            throw new ConnexionRefusee('Ce compte est suspendu.');
        }

        return $this->ouvrirSession($utilisateur, null);
    }

    public function envoyerCodeSms(string $telephone, string $ip): void
    {
        if (! $this->smsActif()) {
            throw new ConnexionRefusee('La connexion par SMS n\'est pas disponible.');
        }
        $telephone = Telephone::normaliser($telephone);
        RateLimiter::hit($this->cle('sms', $telephone, $ip), 600);
        if (RateLimiter::tooManyAttempts($this->cle('sms', $telephone, $ip), 3)) {
            throw new ConnexionRefusee('Trop de demandes. Réessayez dans quelques minutes.');
        }

        $utilisateur = Utilisateur::where('telephone', $telephone)->first();
        if ($utilisateur === null || ! $utilisateur->estActif()) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->creerJeton($utilisateur, 'code_sms', $code, self::VALIDITE_CODE_MINUTES);
        $this->sms->envoyer($telephone, "GESIMO : votre code est {$code}. Il expire dans ".self::VALIDITE_CODE_MINUTES.' minutes.');
    }

    public function verifierCodeSms(string $telephone, string $code, string $ip): Utilisateur
    {
        $telephone = Telephone::normaliser($telephone);
        $cle = $this->verrouiller($telephone, $ip);

        $utilisateur = Utilisateur::where('telephone', $telephone)->first();
        $jeton = $utilisateur === null ? null : JetonConnexion::where('utilisateur_id', $utilisateur->id)
            ->where('type', 'code_sms')->whereNull('utilise_le')->where('expire_le', '>', now())
            ->where('tentatives', '<', self::ESSAIS_MAX)->latest('id')->first();

        if ($jeton === null) {
            RateLimiter::hit($cle, self::PAUSE_SECONDES);
            throw new ConnexionRefusee('Code incorrect ou expiré. Demandez un nouveau code.');
        }

        $jeton->increment('tentatives');
        if (! hash_equals($jeton->empreinte, $this->empreinte(trim($code)))) {
            RateLimiter::hit($cle, self::PAUSE_SECONDES);
            throw new ConnexionRefusee('Code incorrect ou expiré. Demandez un nouveau code.');
        }

        if (JetonConnexion::whereKey($jeton->getKey())->whereNull('utilise_le')->update(['utilise_le' => now()]) !== 1) {
            throw new ConnexionRefusee('Code incorrect ou expiré. Demandez un nouveau code.');
        }

        return $this->ouvrirSession($utilisateur, $cle);
    }

    public function deconnecter(): void
    {
        Auth::guard('web')->logout();
        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }
    }

    /** Empreinte valide mais sans propriétaire : sert à « calculer pour rien » quand le compte n'existe pas. */
    private static function empreinteFactice(): string
    {
        static $empreinte = null;

        return $empreinte ??= Hash::make(Str::random(40));
    }

    private function ouvrirSession(Utilisateur $utilisateur, ?string $cleLimite): Utilisateur
    {
        if (! $utilisateur->estActif()) {
            throw new ConnexionRefusee('Ce compte est suspendu.');
        }

        if ($cleLimite !== null) {
            RateLimiter::clear($cleLimite);
        }
        Auth::guard('web')->login($utilisateur);
        if (request()->hasSession()) {
            request()->session()->regenerate(); // nouvelle session à chaque connexion
        }

        return $utilisateur;
    }

    private function verrouiller(string $telephone, string $ip): string
    {
        $cle = $this->cle('connexion', $telephone, $ip);
        if (RateLimiter::tooManyAttempts($cle, self::ESSAIS_MAX)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($cle) / 60));
            throw new ConnexionRefusee("Trop d'essais. Réessayez dans {$minutes} minute".($minutes > 1 ? 's' : '').'.');
        }

        return $cle;
    }

    private function creerJeton(Utilisateur $utilisateur, string $type, string $secret, int $minutes): string
    {
        JetonConnexion::where('utilisateur_id', $utilisateur->id)->where('type', $type)->whereNull('utilise_le')->update(['utilise_le' => now()]);
        JetonConnexion::create([
            'utilisateur_id' => $utilisateur->id,
            'type' => $type,
            'empreinte' => $this->empreinte($secret),
            'expire_le' => now()->addMinutes($minutes),
        ]);

        return $secret;
    }

    private function empreinte(string $secret): string
    {
        return hash_hmac('sha256', $secret, (string) config('app.key'));
    }

    private function cle(string $usage, string $telephone, string $ip): string
    {
        return "{$usage}:{$telephone}|{$ip}";
    }
}
