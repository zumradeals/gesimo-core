<?php

namespace App\Sejours\Livewire;

use App\Paiement\Services\MiseEnAvantService;
use App\Paiement\Services\ReglagesPaiement;
use App\Sejours\Models\Avis;
use App\Sejours\Models\Unite;
use App\Sejours\Services\AvisService;
use App\Sejours\Services\Disponibilites;
use App\Sejours\Services\ReglesSejour;
use App\Sejours\Services\TarifCalculateur;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Fiche du logement')]
class FicheLogement extends Component
{
    public Unite $unite;

    #[Url]
    public string $arrivee = '';

    #[Url]
    public string $depart = '';

    #[Url]
    public int $personnes = 2;

    /** Nombre de nuits choisi ; 0 tant que le voyageur n'a rien choisi (le séjour minimum s'applique alors). */
    public int $nuits = 0;

    public function mount(Unite $unite): void
    {
        $unite->load('residence');
        abort_unless($unite->active && Gate::allows('view', $unite->residence), 404);
        $this->unite = $unite;
        $this->personnes = max(1, min($this->personnes, $unite->capacite));

        $a = $this->date($this->arrivee);
        $d = $this->date($this->depart);
        if ($a !== null && $d !== null && $d->gt($a)) {
            $this->nuits = (int) $a->diffInDays($d);
        } elseif ($a !== null && $this->depart === '') {
            $this->nuits = ReglesSejour::nuitsMin();
            $this->depart = $a->addDays($this->nuits)->toDateString();
        }
    }

    /** La date d'arrivée change : on garde le même nombre de nuits et le départ suit. */
    public function updatedArrivee(): void
    {
        $a = $this->date($this->arrivee);
        if ($a === null) {
            $this->depart = '';

            return;
        }
        $d = $this->date($this->depart);
        if ($this->nuits <= 0) {
            $this->nuits = $d !== null && $d->gt($a) ? (int) $a->diffInDays($d) : ReglesSejour::nuitsMin();
        }
        $this->depart = $a->addDays($this->nuits)->toDateString();
    }

    /** Un lien (recherche, ancien lien) fournit encore un départ : le nombre de nuits en découle. */
    public function updatedDepart(): void
    {
        $a = $this->date($this->arrivee);
        $d = $this->date($this->depart);
        if ($a !== null && $d !== null && $d->gt($a)) {
            $this->nuits = (int) $a->diffInDays($d);
        }
    }

    public function updatedPersonnes(): void
    {
        $this->personnes = max(1, min((int) $this->personnes, $this->unite->capacite));
    }

    public function plusNuit(): void
    {
        $this->fixerNuits($this->nuitsAffichees() + 1);
    }

    public function moinsNuit(): void
    {
        $this->fixerNuits($this->nuitsAffichees() - 1);
    }

    public function fixerNuits(int $nuits): void
    {
        $this->nuits = max(ReglesSejour::nuitsMin(), min(ReglesSejour::nuitsMax(), $nuits));
        $a = $this->date($this->arrivee);
        if ($a !== null) {
            $this->depart = $a->addDays($this->nuits)->toDateString();
        }
    }

    public function plusPersonne(): void
    {
        $this->personnes = min($this->unite->capacite, $this->personnes + 1);
    }

    public function moinsPersonne(): void
    {
        $this->personnes = max(1, $this->personnes - 1);
    }

    /** Le voyageur choisit l'une des dates libres proposées. */
    public function choisirArrivee(string $arrivee): void
    {
        $this->arrivee = $arrivee;
        $this->updatedArrivee();
    }

    public function render(TarifCalculateur $tarifs, Disponibilites $disponibilites)
    {
        abort_unless($this->unite->fresh()->active && Gate::allows('view', $this->unite->fresh()->residence), 404);
        $unite = $this->unite->load(['residence.gerant.profilGerant', 'residence.photos', 'photos', 'tarifs', 'equipements']);
        $detail = null;
        $groupes = null;
        $total = null;
        $libre = null;
        $erreur = null;
        $suggestions = [];
        $tropLong = false;
        $tropCourt = false;
        $limiteAnnulation = null;
        $nuits = $this->nuitsAffichees();
        $dateDepart = null;

        if ($this->arrivee !== '' && $this->depart !== '') {
            $a = $this->date($this->arrivee);
            $d = $this->date($this->depart);
            $nuits = $a !== null && $d !== null && $d->gt($a) ? (int) $a->diffInDays($d) : $nuits;
            if ($a === null || $d === null || $d->lte($a)) {
                $erreur = 'La date de départ doit être après la date d\'arrivée.';
            } elseif ($a->lt(now()->startOfDay())) {
                $erreur = 'La date d\'arrivée ne peut pas être dans le passé.';
            } elseif ($nuits > ReglesSejour::nuitsMax()) {
                $tropLong = true;
            } elseif ($nuits < ReglesSejour::nuitsMin()) {
                $tropCourt = true;
            } else {
                $dateDepart = $d;
                $detail = $tarifs->detail($unite, $a, $d);
                $total = $detail === null ? null : array_sum(array_column($detail, 'prix'));
                $libre = $disponibilites->estLibre($unite, $a, $d);
                if ($detail !== null) {
                    $groupes = $this->grouper($detail);
                }
                if (! $libre) {
                    $suggestions = $disponibilites->prochainesDatesLibres($unite, $a, $nuits);
                }
                if ($unite->residence->politique_annulation === 'flexible') {
                    $limiteAnnulation = ReglesSejour::limiteAnnulationGratuite($a, ReglagesPaiement::delaiAnnulationGratuiteHeures(), ReglesSejour::heureArrivee());
                }
            }
        }

        return view('livewire.sejours.fiche-logement', [
            'u' => $unite,
            'detail' => $detail,
            'groupes' => $groupes,
            'total' => $total,
            'libre' => $libre,
            'erreur' => $erreur,
            'tropLong' => $tropLong,
            'tropCourt' => $tropCourt,
            'suggestions' => $suggestions,
            'nuits' => $nuits,
            'dateDepart' => $dateDepart,
            'limiteAnnulation' => $limiteAnnulation,
            'nuitsMin' => ReglesSejour::nuitsMin(),
            'nuitsMax' => ReglesSejour::nuitsMax(),
            'heureArrivee' => ReglesSejour::heureLisible(ReglesSejour::heureArrivee()),
            'heureDepart' => ReglesSejour::heureLisible(ReglesSejour::heureDepart()),
            'prixNuit' => $unite->tarifs->firstWhere('type_periode', 'standard')?->prix_nuit,
            'sponsorise' => in_array($unite->residence_id, MiseEnAvantService::residencesEnAvant(), true),
            'resumeAvis' => app(AvisService::class)->resume($unite->residence_id),
            'avis' => Avis::whereHas('reservation.unite', fn ($q) => $q->where('residence_id', $unite->residence_id))->with('client')->latest('id')->limit(5)->get(),
        ]);
    }

    private function nuitsAffichees(): int
    {
        return $this->nuits > 0 ? $this->nuits : ReglesSejour::nuitsMin();
    }

    private function date(string $valeur): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $valeur) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Nuits regroupées par prix et par type, dans l'ordre : « 5 nuits × 10 000 FCFA ».
     *
     * @param  list<array{date: CarbonImmutable, prix: int, type: string}>  $detail
     * @return list<array{nuits: int, prix: int, type: string}>
     */
    private function grouper(array $detail): array
    {
        $groupes = [];
        foreach ($detail as $nuit) {
            $cle = $nuit['type'].'|'.$nuit['prix'];
            $groupes[$cle] ??= ['nuits' => 0, 'prix' => $nuit['prix'], 'type' => $nuit['type']];
            $groupes[$cle]['nuits']++;
        }

        return array_values($groupes);
    }
}
