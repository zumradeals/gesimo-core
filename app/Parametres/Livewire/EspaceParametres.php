<?php

namespace App\Parametres\Livewire;

use App\Noyau\Services\Audit;
use App\Paiement\Services\Fournisseur\FournisseurPaiement;
use App\Paiement\Services\PlancherAcompte;
use App\Parametres\Exceptions\ReglageInvalide;
use App\Parametres\Models\ParametreHistorique;
use App\Parametres\Services\Marque;
use App\Parametres\Services\Parametres;
use App\Parametres\Services\Reglages;
use App\Parametres\Services\TestsConnexion;
use App\Sejours\Models\Residence;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Espace Paramètres (F-A7), réservé au super-administrateur. Construit à partir du catalogue Reglages.
 * Les secrets ne sont jamais chargés dans la page : on n'y met que ce que la personne vient de taper.
 */
#[Title('Réglages')]
class EspaceParametres extends Component
{
    use WithFileUploads;
    use WithPagination;

    /** accueil | une rubrique | tests | historique */
    public string $rubrique = 'accueil';

    /** @var array<string, mixed> réglages non secrets, clés avec « __ » à la place des points */
    public array $valeurs = [];

    /** @var array<string, string> nouveaux secrets tapés */
    public array $secrets = [];

    /** @var array<string, bool> secrets que la personne veut remplacer */
    public array $remplacer = [];

    public string $motDePasse = '';

    public $logo = null;

    /** @var array<string, string> */
    public array $erreurs = [];

    public ?string $message = null;

    /** @var array<string, array{ok: bool, message: string}> */
    public array $resultats = [];

    public string $filtreHistorique = '';

    public function mount(?string $rubrique = null): void
    {
        abort_unless(Gate::allows('modifier-parametres'), 403);
        $rubrique ??= 'accueil';
        abort_unless($rubrique === 'accueil' || $rubrique === 'tests' || $rubrique === 'historique' || isset(Reglages::RUBRIQUES[$rubrique]), 404);
        $this->rubrique = $rubrique;
        $this->charger();
    }

    public static function cle(string $reglage): string
    {
        return str_replace('.', '__', $reglage);
    }

    public function remplacer(string $cle): void
    {
        $this->remplacer[$cle] = true;
    }

    public function ajouterOffre(): void
    {
        $this->valeurs['mise_en_avant__offres'][] = ['nom' => '', 'jours' => '', 'prix' => ''];
    }

    public function retirerOffre(int $i): void
    {
        unset($this->valeurs['mise_en_avant__offres'][$i]);
        $this->valeurs['mise_en_avant__offres'] = array_values($this->valeurs['mise_en_avant__offres']);
    }

    public function enregistrer(): void
    {
        Gate::authorize('modifier-parametres');
        $this->message = null;
        $this->erreurs = [];
        abort_unless(isset(Reglages::RUBRIQUES[$this->rubrique]), 404);

        $valeurs = [];
        foreach (Reglages::pourRubrique($this->rubrique) as $cle => $def) {
            if (! in_array($def['type'], ['secret', 'logo'], true)) {
                $valeurs[$cle] = $this->valeurs[self::cle($cle)] ?? null;
            }
        }
        $secrets = [];
        foreach ($this->secrets as $cle => $valeur) {
            $secrets[self::cle2($cle)] = (string) $valeur;
        }

        try {
            if ($this->rubrique === 'general' && $this->logo !== null) {
                $this->enregistrerLogo();
            }
            $modifies = Reglages::enregistrer($this->rubrique, $valeurs, $secrets, auth()->user(), $this->motDePasse);
        } catch (ReglageInvalide $e) {
            $this->erreurs = $e->erreurs;

            return;
        }

        $this->secrets = [];
        $this->remplacer = [];
        $this->motDePasse = '';
        $this->charger();
        $this->message = $modifies === [] && $this->logo === null ? 'Aucun changement à enregistrer.' : 'Réglages enregistrés.';
        $this->logo = null;
    }

    public function testerEmail(TestsConnexion $tests): void
    {
        Gate::authorize('modifier-parametres');
        $this->resultats['email'] = $tests->email(auth()->user());
    }

    public function testerPaiement(TestsConnexion $tests): void
    {
        Gate::authorize('modifier-parametres');
        $this->resultats['paiement'] = $tests->paiement(auth()->user());
    }

    public function updatedFiltreHistorique(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $defs = Reglages::pourRubrique(in_array($this->rubrique, ['accueil', 'tests', 'historique'], true) ? 'general' : $this->rubrique);
        $plancher = PlancherAcompte::pourcentageMinimum();

        return view('livewire.parametres.espace', [
            'defs' => $defs,
            'rubriques' => Reglages::RUBRIQUES,
            'etats' => $this->rubrique === 'accueil' ? $this->etats() : [],
            'secretsEnregistres' => collect($defs)->filter(fn ($d) => $d['type'] === 'secret')->mapWithKeys(fn ($d, $cle) => [$cle => Parametres::estDefini($cle)])->all(),
            'plancher' => $plancher,
            'residencesSousPlancher' => $plancher === null ? 0 : Residence::where('mode_paiement', 'acompte')->where('pourcentage_acompte', '<', $plancher)->count(),
            'logoUrl' => Marque::logoUrl(),
            'historique' => $this->rubrique === 'historique' ? $this->historique() : null,
            'webhookUrl' => url('/webhooks/geniuspay'),
            'mode' => Reglages::valeur('geniuspay.mode'),
        ]);
    }

    private static function cle2(string $cleAvecTirets): string
    {
        return str_replace('__', '.', $cleAvecTirets);
    }

    private function charger(): void
    {
        $this->valeurs = [];
        foreach (Reglages::definitions() as $cle => $def) {
            if (in_array($def['type'], ['secret', 'logo'], true)) {
                continue;
            }
            $valeur = Reglages::valeur($cle);
            if ($def['type'] === 'offres') {
                $valeur = json_decode((string) $valeur, true) ?: [];
            }
            $this->valeurs[self::cle($cle)] = $valeur ?? '';
        }
    }

    private function enregistrerLogo(): void
    {
        $this->validate(['logo' => 'image|mimes:png,jpg,jpeg|max:1024'], [
            'logo.image' => 'Le logo doit être une image PNG ou JPG.',
            'logo.mimes' => 'Le logo doit être une image PNG ou JPG.',
            'logo.max' => 'Le logo est trop lourd (1 Mo au maximum).',
        ]);
        $ancien = Parametres::obtenir('general.logo');
        $chemin = $this->logo->storeAs('logo', Str::random(24).'.'.($this->logo->extension() ?: 'png'), 'public');
        Parametres::definir('general.logo', $chemin, auth()->user(), false, 'general');
        if ($ancien) {
            Storage::disk('public')->delete($ancien);
        }
        Audit::enregistrer('reglages.logo_change', null, [], auth()->user());
    }

    /** @return array<string, array{etat: string, texte: string, ton: string}> */
    private function etats(): array
    {
        $paiement = app(FournisseurPaiement::class);
        $offres = json_decode((string) Parametres::obtenir('mise_en_avant.offres'), true) ?: [];
        $e = fn (bool $ok, string $texte, string $manque = 'À compléter') => ['etat' => $ok ? 'À jour' : $manque, 'texte' => $texte, 'ton' => $ok ? 'b-conf' : 'b-attente'];

        return [
            'general' => $e(Parametres::estDefini('contact.email'), Marque::nom()),
            'reservation' => $e(true, 'Blocage '.Reglages::entier('reservation.duree_blocage_minutes').' min · annulation '.Reglages::entier('annulation.delai_gratuit_heures').' h'),
            'paiement' => $e($paiement->estConfigure() && Parametres::estDefini('commission.pourcentage'), 'Mode '.($paiement->mode() === 'live' ? 'réel' : 'test').' · commission '.(Parametres::obtenir('commission.pourcentage') ?? '—').' %', 'Clés ou commission à régler'),
            'notifications' => $e(Parametres::estDefini('email.hote'), 'E-mail · SMS '.(Parametres::obtenir('sms.actif') === '1' ? 'activé' : 'désactivé')),
            'mise_en_avant' => $e($offres !== [], count($offres).' offre(s)', 'Aucune offre'),
            'securite' => $e(true, 'Session '.Reglages::entier('securite.session_minutes').' min · '.Reglages::entier('securite.connexion_essais_max').' essais'),
        ];
    }

    private function historique()
    {
        $prefixes = ['paiement' => ['geniuspay.', 'commission.', 'paiement.'], 'securite' => ['securite.'], 'reservation' => ['reservation.', 'annulation.']][$this->filtreHistorique] ?? null;

        return ParametreHistorique::with(['parametre', 'auteur'])
            ->when($prefixes, fn ($q) => $q->whereHas('parametre', fn ($p) => $p->where(function ($w) use ($prefixes) {
                foreach ($prefixes as $pre) {
                    $w->orWhere('cle', 'like', $pre.'%');
                }
            })))
            ->latest('id')->paginate(30);
    }
}
