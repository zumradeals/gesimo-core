<?php

namespace App\Sejours\Livewire;

use App\Sejours\Models\Equipement;
use App\Sejours\Models\Residence;
use App\Sejours\Services\Disponibilites;
use App\Sejours\Services\ReglesSejour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Chercher un séjour')]
class Recherche extends Component
{
    #[Url]
    public string $commune = '';

    #[Url]
    public string $arrivee = '';

    #[Url]
    public string $depart = '';

    /** Nombre de nuits choisi ; 0 tant que rien n'est choisi. Le départ en découle. */
    #[Url]
    public int $nuits = 0;

    #[Url]
    public int $personnes = 2;

    /** Types de logement acceptés. */
    public const TYPES = ['studio' => 'Studio', 'appartement' => 'Appartement', 'chambre' => 'Chambre'];

    /** @var list<string> */
    #[Url]
    public array $types = [];

    #[Url]
    public string $budget = '';

    /** @var list<int|string> */
    #[Url]
    public array $equipements = [];

    /** recommande (défaut) | prix_asc | prix_desc — les annonces « Sponsorisé » restent toujours en premier */
    #[Url]
    public string $tri = 'recommande';

    public bool $lancee = false;

    public ?string $erreur = null;

    public function mount(): void
    {
        $this->types = array_values(array_intersect(array_map('strval', $this->types), array_keys(self::TYPES)));
        $a = $this->date($this->arrivee);
        $d = $this->date($this->depart);
        if ($a !== null && $this->depart === '' && $this->nuits > 0) {
            // Lien « arrivée + nuits » (formulaire de l'accueil) : le départ est calculé.
            $this->depart = $a->addDays($this->nuits)->toDateString();
        } elseif ($a !== null && $d !== null && $d->gt($a)) {
            $this->nuits = (int) $a->diffInDays($d);
        }
        if ($this->arrivee !== '' || $this->depart !== '') {
            $this->chercher();
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

    /** Un ancien lien ou un test fournit encore un départ : le nombre de nuits en découle. */
    public function updatedDepart(): void
    {
        $a = $this->date($this->arrivee);
        $d = $this->date($this->depart);
        if ($a !== null && $d !== null && $d->gt($a)) {
            $this->nuits = (int) $a->diffInDays($d);
        }
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

    public function choisirCommune(string $commune): void
    {
        $this->commune = $commune;
    }

    public function basculerType(string $type): void
    {
        if (! array_key_exists($type, self::TYPES)) {
            return;
        }
        $this->types = in_array($type, $this->types, true) ? array_values(array_diff($this->types, [$type])) : [...$this->types, $type];
    }

    public function retirerBudget(): void
    {
        $this->budget = '';
    }

    public function chercher(): void
    {
        $this->lancee = false;
        $this->erreur = null;
        $this->resetValidation();
        $regles = ['personnes' => 'required|integer|min:1|max:50'];
        if ($this->arrivee !== '' || $this->depart !== '') {
            $regles += ['arrivee' => 'required|date_format:Y-m-d', 'depart' => 'required|date_format:Y-m-d'];
        }
        $validation = Validator::make($this->only(array_keys($regles)), $regles, [], [
            'arrivee' => "La date d'arrivée", 'depart' => 'La date de départ', 'personnes' => 'Le nombre de personnes',
        ]);
        if ($validation->fails()) {
            $this->setErrorBag($validation->errors());

            return;
        }
        if ($this->arrivee === '' && $this->depart === '') {
            return;
        }
        $a = CarbonImmutable::parse($this->arrivee)->startOfDay();
        $d = CarbonImmutable::parse($this->depart)->startOfDay();
        if ($d->lte($a)) {
            $this->erreur = "La date de départ doit être après la date d'arrivée.";
        } elseif ($a->lt(now()->startOfDay())) {
            $this->erreur = "La date d'arrivée ne peut pas être dans le passé.";
        } elseif (($nuits = (int) $a->diffInDays($d)) < ReglesSejour::nuitsMin() || $nuits > ReglesSejour::nuitsMax()) {
            $this->erreur = 'Le nombre de nuits doit être compris entre '.ReglesSejour::nuitsMin().' et '.ReglesSejour::nuitsMax().'.';
        }
        $this->lancee = $this->erreur === null;
    }

    public function reinitialiser(): void
    {
        $this->reset('commune', 'budget', 'equipements', 'types', 'personnes', 'tri');
        $this->chercher();
    }

    public function basculerEquipement(int $id): void
    {
        $this->equipements = in_array($id, array_map('intval', $this->equipements), true)
            ? array_values(array_diff(array_map('intval', $this->equipements), [$id]))
            : [...array_map('intval', $this->equipements), $id];
    }

    public function render(Disponibilites $disponibilites)
    {
        $resultats = null;
        $nuits = null;

        if ($this->lancee && $this->erreur === null && ! Validator::make($this->only(['arrivee', 'depart']), ['arrivee' => 'required|date_format:Y-m-d', 'depart' => 'required|date_format:Y-m-d'])->fails()) {
            $a = CarbonImmutable::parse($this->arrivee)->startOfDay();
            $d = CarbonImmutable::parse($this->depart)->startOfDay();
            if ($d->gt($a) && $a->gte(now()->startOfDay())) {
                $nuits = (int) $a->diffInDays($d);
                $budget = preg_replace('/\D+/', '', $this->budget);
                $resultats = $disponibilites->rechercher(
                    $a, $d,
                    $this->commune ?: null,
                    max(1, $this->personnes),
                    $budget === '' ? null : (int) $budget,
                    array_map('intval', $this->equipements),
                    types: $this->types,
                );
            }
        }

        $resultats ??= $disponibilites->catalogue($this->commune ?: null, max(1, $this->personnes), array_map('intval', $this->equipements), types: $this->types);

        if ($this->tri === 'prix_desc') {
            $resultats = $resultats->where('sponsorise', true)->concat($resultats->where('sponsorise', false)->sortByDesc($nuits === null ? 'prix_reference' : 'total'))->values();
        }

        $communes = Residence::publiee()->distinct()->orderBy('commune')->pluck('commune');
        $listeEquipements = Equipement::orderBy('id')->get();
        $a = $this->date($this->arrivee);
        $nuitsChoisies = $this->nuitsAffichees();

        return view('livewire.sejours.recherche', [
            'communes' => $communes,
            'communesChiffrees' => $disponibilites->logementsParCommune(),
            'listeEquipements' => $listeEquipements,
            'listeTypes' => self::TYPES,
            'resultats' => $resultats,
            'nuitsRecherche' => $nuits,
            'nuitsChoisies' => $nuitsChoisies,
            'nuitsMin' => ReglesSejour::nuitsMin(),
            'nuitsMax' => ReglesSejour::nuitsMax(),
            'dateDepart' => $a !== null && $this->nuits > 0 ? $a->addDays($this->nuits) : null,
            'heureDepart' => ReglesSejour::heureLisible(ReglesSejour::heureDepart()),
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
}
