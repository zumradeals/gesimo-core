<?php

namespace App\Noyau\Livewire;

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Noyau\Services\Installation as Assistant;
use App\Noyau\Services\Telephone;
use App\Paiement\Services\PlancherAcompte;
use App\Parametres\Exceptions\ReglageInvalide;
use App\Parametres\Services\Parametres;
use App\Parametres\Services\Reglages;
use App\Parametres\Services\TestsConnexion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Assistant d'installation du premier lancement : 1 compte, 2 général, 3 e-mail, 4 paiement, 5 fin.
 * Il disparaît (404) dès que l'installation est terminée.
 */
#[Layout('layouts.installation')]
#[Title('Installation')]
class Installation extends Component
{
    public int $etape = 1;

    public string $code = '';

    public string $nom = '';

    public string $telephone = '';

    public string $email = '';

    public string $motDePasse = '';

    public string $confirmation = '';

    /** @var array<string, mixed> */
    public array $valeurs = [];

    /** @var array<string, string> */
    public array $secrets = [];

    /** @var array<string, string> */
    public array $erreurs = [];

    /** @var array{ok: bool, message: string}|null */
    public ?array $resultat = null;

    public bool $codeVerifie = false;

    public function mount()
    {
        abort_if(Assistant::estTerminee(), 404);

        if (Assistant::superAdminExiste()) {
            if (! auth()->check() || ! auth()->user()->estSuperAdmin()) {
                return redirect()->guest(route('connexion'));
            }
            $this->etape = 2;
        }
        $this->valeurs = [
            'general__nom' => Reglages::valeur('general.nom'),
            'contact__email' => Reglages::valeur('contact.email') ?? '',
            'email__hote' => Reglages::valeur('email.hote') ?? '',
            'email__port' => Reglages::valeur('email.port'),
            'email__utilisateur' => Reglages::valeur('email.utilisateur') ?? '',
            'geniuspay__mode' => Reglages::valeur('geniuspay.mode'),
            'geniuspay__sandbox__cle_publique' => Reglages::valeur('geniuspay.sandbox.cle_publique') ?? '',
            'commission__pourcentage' => Reglages::valeur('commission.pourcentage') ?? '',
            'paiement__frais_pourcentage' => Reglages::valeur('paiement.frais_pourcentage') ?? '',
        ];

        return null;
    }

    public function creerCompte(): void
    {
        abort_if(Assistant::estTerminee() || Assistant::superAdminExiste(), 404);
        $this->erreurs = [];

        if (! Assistant::codeGenere()) {
            $this->erreurs['code'] = 'Aucun code d\'installation n\'a été généré. Sur le serveur, lancez : php artisan gesimo:installation';

            return;
        }
        if (! Assistant::verifierCode($this->code, request()->ip())) {
            $this->erreurs['code'] = 'Ce code d\'installation n\'est pas le bon. Vérifiez-le sur le serveur (5 essais, puis pause).';

            return;
        }

        $this->telephone = Telephone::normaliser($this->telephone);
        $this->email = mb_strtolower(trim($this->email));
        $donnees = $this->validate([
            'nom' => 'required|string|max:120',
            'telephone' => ['required', fn ($a, $v, $echec) => Telephone::estValide($v) ? null : $echec('Le numéro doit avoir 10 chiffres.'), 'unique:utilisateur,telephone'],
            'email' => 'required|email|max:190|unique:utilisateur,email',
            'motDePasse' => 'required|string|min:8|max:100|same:confirmation',
        ], [
            'telephone.unique' => 'Ce numéro a déjà un compte.',
            'email.unique' => 'Cette adresse e-mail a déjà un compte.',
            'motDePasse.same' => 'Les deux mots de passe ne sont pas identiques.',
            'motDePasse.min' => 'Le mot de passe doit avoir au moins 8 caractères.',
        ], ['nom' => 'Votre nom', 'telephone' => 'Le téléphone', 'email' => 'L\'e-mail', 'motDePasse' => 'Le mot de passe']);

        $utilisateur = DB::transaction(function () use ($donnees) {
            // Deux personnes en même temps : un seul super-administrateur peut naître de l'assistant.
            DB::statement('LOCK TABLE utilisateur IN SHARE ROW EXCLUSIVE MODE');
            abort_if(Assistant::superAdminExiste(), 404);
            $u = Utilisateur::create(['nom' => trim($donnees['nom']), 'telephone' => $donnees['telephone'], 'email' => $donnees['email'], 'mot_de_passe' => $donnees['motDePasse']]);
            $u->attribuerRole(Role::SuperAdmin);
            Audit::enregistrer('compte.cree', $u, ['profil' => 'super_admin', 'via' => 'installation'], $u);

            return $u;
        });

        Auth::guard('web')->login($utilisateur);
        session()->regenerate();
        $this->reset('code', 'motDePasse', 'confirmation');
        $this->etape = 2;
    }

    public function etapeGenerale(): void
    {
        $this->enregistrer('general', ['general.nom', 'contact.email'], [], 3);
    }

    public function etapeEmail(): void
    {
        $this->enregistrer('notifications', ['email.hote', 'email.port', 'email.utilisateur'], ['email.mot_de_passe'], 4);
    }

    public function testerEmail(TestsConnexion $tests): void
    {
        $this->controleSuperAdmin();
        if ($this->enregistrer('notifications', ['email.hote', 'email.port', 'email.utilisateur'], ['email.mot_de_passe'], null)) {
            $this->resultat = $tests->email(auth()->user());
        }
    }

    public function etapePaiement(): void
    {
        $this->enregistrer('paiement', ['geniuspay.mode', 'geniuspay.sandbox.cle_publique', 'commission.pourcentage', 'paiement.frais_pourcentage'], ['geniuspay.sandbox.cle_secrete', 'geniuspay.sandbox.webhook_secret'], 5);
    }

    public function passer(): void
    {
        $this->controleSuperAdmin();
        $this->etape = min(5, $this->etape + 1);
        $this->resultat = null;
        $this->erreurs = [];
    }

    public function terminer()
    {
        $this->controleSuperAdmin();
        Assistant::terminer(auth()->user());

        return redirect()->route('admin.tableau');
    }

    public function render()
    {
        return view('livewire.noyau.installation', [
            'plancher' => PlancherAcompte::calculer($this->valeurs['commission__pourcentage'] ?? null, $this->valeurs['paiement__frais_pourcentage'] ?? null),
            'codeGenere' => Assistant::codeGenere(),
            'superAdminExiste' => Assistant::superAdminExiste(),
            'faits' => $this->etape === 5 ? $this->faits() : [],
        ]);
    }

    /** @return array<string, array{ok: bool, texte: string}> */
    private function faits(): array
    {
        $oui = fn (string $cle) => Parametres::estDefini($cle);

        return [
            'Compte super-administrateur' => ['ok' => true, 'texte' => 'fait'],
            'Général' => ['ok' => $oui('contact.email'), 'texte' => $oui('contact.email') ? 'fait' : 'à compléter plus tard'],
            'E-mail' => ['ok' => $oui('email.hote'), 'texte' => $oui('email.hote') ? 'fait' : 'à compléter plus tard'],
            'Paiement' => ['ok' => $oui('commission.pourcentage') && $oui('geniuspay.sandbox.cle_secrete'), 'texte' => ($oui('commission.pourcentage') && $oui('geniuspay.sandbox.cle_secrete')) ? 'fait (mode test)' : 'à compléter plus tard'],
        ];
    }

    private function controleSuperAdmin(): void
    {
        abort_if(Assistant::estTerminee(), 404);
        abort_unless(auth()->check() && auth()->user()->estSuperAdmin(), 403);
    }

    /**
     * @param  list<string>  $cles  réglages non secrets de l'étape
     * @param  list<string>  $cleSecrets
     */
    private function enregistrer(string $rubrique, array $cles, array $cleSecrets, ?int $suivante): bool
    {
        $this->controleSuperAdmin();
        $this->erreurs = [];
        $valeurs = [];
        foreach ($cles as $cle) {
            $valeurs[$cle] = $this->valeurs[str_replace('.', '__', $cle)] ?? null;
        }
        $secrets = [];
        foreach ($cleSecrets as $cle) {
            $secrets[$cle] = (string) ($this->secrets[str_replace('.', '__', $cle)] ?? '');
        }

        try {
            // Pendant l'installation, la personne vient de créer son compte : pas de mot de passe à ressaisir.
            Reglages::enregistrer($rubrique, $valeurs, $secrets, auth()->user(), null, installation: true);
        } catch (ReglageInvalide $e) {
            $this->erreurs = $e->erreurs;

            return false;
        }
        $this->secrets = [];
        if ($suivante !== null) {
            $this->etape = $suivante;
            $this->resultat = null;
        }

        return true;
    }
}
