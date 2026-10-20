<?php

namespace App\Sejours\Livewire;

use App\Sejours\Models\Equipement;
use App\Sejours\Models\Residence;
use App\Sejours\Services\Disponibilites;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
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

    public bool $lancee = false;

    public ?string $erreur = null;

    public function mount(): void
    {
        $this->lancee = $this->arrivee !== '' && $this->depart !== '';
    }

    public function chercher(): void
    {
        $this->erreur = null;
        $this->validate([
            'arrivee' => 'required|date',
            'depart' => 'required|date',
            'personnes' => 'required|integer|min:1|max:50',
        ], [], ['arrivee' => 'La date d\'arrivée', 'depart' => 'La date de départ', 'personnes' => 'Le nombre de personnes']);

        $a = CarbonImmutable::parse($this->arrivee)->startOfDay();
        $d = CarbonImmutable::parse($this->depart)->startOfDay();
        if ($d->lte($a)) {
            $this->erreur = 'La date de départ doit être après la date d\'arrivée.';
        } elseif ($a->lt(now()->startOfDay())) {
            $this->erreur = 'La date d\'arrivée ne peut pas être dans le passé.';
        }
        $this->lancee = $this->erreur === null;
    }

    public function basculerEquipement(int $id): void
    {
        $this->equipements = in_array($id, array_map('intval', $this->equipements), true)
            ? array_values(array_diff(array_map('intval', $this->equipements), [$id]))
            : [...array_map('intval', $this->equipements), $id];
    }

    public function render(Disponibilites $disponibilites)
    {
        $resultats = new Collection;
        $nuits = null;

        if ($this->lancee && $this->erreur === null) {
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

        return view('livewire.sejours.recherche', [
            'communes' => Residence::where('statut_verification', 'verifiee')->distinct()->orderBy('commune')->pluck('commune'),
            'listeEquipements' => Equipement::orderBy('id')->get(),
            'resultats' => $resultats,
            'nuits' => $nuits,
        ]);
    }
}
