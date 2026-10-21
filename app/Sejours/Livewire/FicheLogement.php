<?php

namespace App\Sejours\Livewire;

use App\Paiement\Services\MiseEnAvantService;
use App\Sejours\Models\Avis;
use App\Sejours\Models\Unite;
use App\Sejours\Services\AvisService;
use App\Sejours\Services\Disponibilites;
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

    public function mount(Unite $unite): void
    {
        $unite->load('residence');
        abort_unless($unite->active && Gate::allows('view', $unite->residence), 404);
        $this->unite = $unite;
    }

    public function render(TarifCalculateur $tarifs, Disponibilites $disponibilites)
    {
        abort_unless($this->unite->fresh()->active && Gate::allows('view', $this->unite->fresh()->residence), 404);
        $unite = $this->unite->load(['residence.gerant.profilGerant', 'residence.photos', 'photos', 'tarifs', 'equipements']);
        $detail = null;
        $total = null;
        $libre = null;
        $erreur = null;

        if ($this->arrivee !== '' && $this->depart !== '') {
            $a = CarbonImmutable::parse($this->arrivee)->startOfDay();
            $d = CarbonImmutable::parse($this->depart)->startOfDay();
            if ($d->lte($a)) {
                $erreur = 'La date de départ doit être après la date d\'arrivée.';
            } elseif ($a->lt(now()->startOfDay())) {
                $erreur = 'La date d\'arrivée ne peut pas être dans le passé.';
            } else {
                $detail = $tarifs->detail($unite, $a, $d);
                $total = $detail === null ? null : array_sum(array_column($detail, 'prix'));
                $libre = $disponibilites->estLibre($unite, $a, $d);
            }
        }

        return view('livewire.sejours.fiche-logement', [
            'u' => $unite,
            'detail' => $detail,
            'total' => $total,
            'libre' => $libre,
            'erreur' => $erreur,
            'sponsorise' => in_array($unite->residence_id, MiseEnAvantService::residencesEnAvant(), true),
            'resumeAvis' => app(AvisService::class)->resume($unite->residence_id),
            'avis' => Avis::whereHas('reservation.unite', fn ($q) => $q->where('residence_id', $unite->residence_id))->with('client')->latest('id')->limit(5)->get(),
        ]);
    }
}
