<?php

namespace App\Parametres\Services;

use App\Noyau\Mail\MessageGesimo;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Noyau\Services\Courriel;
use App\Paiement\Services\Fournisseur\FournisseurIndisponible;
use App\Paiement\Services\Fournisseur\FournisseurPaiement;
use App\Paiement\Services\Fournisseur\RequeteRefusee;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * « Tester la connexion » (RG-21) : réussite ou échec, dit clairement. Un résultat ne contient jamais
 * une clé ni un mot de passe. Les tests sont sans danger : aucun argent, aucune donnée modifiée.
 */
class TestsConnexion
{
    public function __construct(private FournisseurPaiement $paiement) {}

    /** @return array{ok: bool, message: string} */
    public function email(Utilisateur $admin): array
    {
        if (($limite = $this->limite($admin, 'email')) !== null) {
            return $limite;
        }
        if (! Parametres::estDefini('email.hote')) {
            return $this->resultat($admin, 'email', false, 'Complétez d\'abord les réglages : le serveur d\'e-mail n\'est pas renseigné (les messages sont seulement écrits dans le journal du serveur).');
        }
        if ($admin->email === null || $admin->email === '') {
            return $this->resultat($admin, 'email', false, 'Votre compte n\'a pas d\'adresse e-mail pour recevoir le message de test.');
        }

        try {
            Courriel::appliquerLesReglages();
            Mail::to($admin->email)->send(new MessageGesimo('Test de connexion e-mail', "Bonjour {$admin->nom},\n\nSi vous lisez ce message, l'envoi d'e-mails de GESIMO Séjours fonctionne."));
        } catch (Throwable $e) {
            return $this->resultat($admin, 'email', false, 'Le serveur d\'e-mail n\'a pas accepté le message : '.$this->sansSecret($e->getMessage()).' Vérifiez le serveur, le port, le chiffrement et le mot de passe.');
        }

        return $this->resultat($admin, 'email', true, "Message de test envoyé à {$admin->email} à ".now()->format('H \h i').'. Vérifiez votre boîte de réception.');
    }

    /** @return array{ok: bool, message: string} */
    public function paiement(Utilisateur $admin): array
    {
        if (($limite = $this->limite($admin, 'paiement')) !== null) {
            return $limite;
        }
        if (! $this->paiement->estConfigure()) {
            return $this->resultat($admin, 'paiement', false, 'Complétez d\'abord les réglages : la clé publique ou la clé secrète du mode « '.($this->paiement->mode() === 'live' ? 'réel' : 'test').' » est manquante.');
        }

        try {
            $this->paiement->tester();
        } catch (RequeteRefusee $e) {
            return $this->resultat($admin, 'paiement', false, 'GeniusPay a refusé les clés. Vérifiez la clé secrète et le mode (test ou réel).');
        } catch (FournisseurIndisponible) {
            return $this->resultat($admin, 'paiement', false, 'GeniusPay ne répond pas pour le moment. Réessayez dans quelques minutes.');
        }

        return $this->resultat($admin, 'paiement', true, 'Clés acceptées par GeniusPay (mode '.($this->paiement->mode() === 'live' ? 'réel' : 'test').').');
    }

    /** @return array{ok: bool, message: string}|null */
    private function limite(Utilisateur $admin, string $nom): ?array
    {
        $cle = "test-connexion:{$nom}:{$admin->id}";
        if (RateLimiter::tooManyAttempts($cle, 10)) {
            return ['ok' => false, 'message' => 'Trop de tests : réessayez dans quelques minutes (10 par heure).'];
        }
        RateLimiter::hit($cle, 3600);

        return null;
    }

    /** @return array{ok: bool, message: string} */
    private function resultat(Utilisateur $admin, string $nom, bool $ok, string $message): array
    {
        Audit::enregistrer('reglages.test_connexion', null, ['test' => $nom, 'reussi' => $ok], $admin);

        return ['ok' => $ok, 'message' => $message];
    }

    /** Enlève du texte d'une erreur tout ce qui ressemble à un secret (mot de passe saisi, clés), et le raccourcit. */
    private function sansSecret(string $texte): string
    {
        foreach (['email.mot_de_passe', 'email.utilisateur'] as $cle) {
            $valeur = Parametres::obtenir($cle);
            if ($valeur !== null && $valeur !== '') {
                $texte = str_replace($valeur, '***', $texte);
            }
        }

        return mb_substr(trim(preg_replace('/\s+/', ' ', $texte)), 0, 160);
    }
}
