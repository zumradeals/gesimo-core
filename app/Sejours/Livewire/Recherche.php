<?php

namespace App\Sejours\Livewire;

use App\Sejours\Models\Equipement;
use App\Sejours\Models\Residence;
use App\Sejours\Services\Disponibilites;
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

    #[Url]
    public int $personnes = 2;

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
        if ($this->arrivee !== '' || $this->depart !== '') {
            $this->chercher();
        }
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
        }
        $this->lancee = $this->erreur === null;
    }

    public function reinitialiser(): void
    {
        $this->reset('commune', 'budget', 'equipements', 'personnes', 'tri');
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
                );
            }
        }

        $resultats ??= $disponibilites->catalogue($this->commune ?: null, max(1, $this->personnes), array_map('intval', $this->equipements));

        if ($this->tri === 'prix_desc') {
            $resultats = $resultats->where('sponsorise', true)->concat($resultats->where('sponsorise', false)->sortByDesc($nuits === null ? 'prix_reference' : 'total'))->values();
        }

        return view('livewire.sejours.recherche', [
            'communes' => Residence::publiee()->distinct()->orderBy('commune')->pluck('commune'),
            'listeEquipements' => Equipement::orderBy('id')->get(),
            'resultats' => $resultats,
            'nuits' => $nuits,
        ]);
    }
}
