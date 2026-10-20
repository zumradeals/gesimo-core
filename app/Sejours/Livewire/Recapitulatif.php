<?php

namespace App\Sejours\Livewire;

use App\Paiement\Services\PaiementService;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Unite;
use App\Sejours\Services\Disponibilites;
use App\Sejours\Services\ReservationService;
use App\Sejours\Services\TarifCalculateur;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Récapitulatif')]
class Recapitulatif extends Component
{
    public Unite $unite;

    #[Url]
    public string $arrivee = '';

    #[Url]
    public string $depart = '';

    #[Url]
    public int $personnes = 1;

    public ?string $erreur = null;

    public function mount(Unite $unite): void
    {
        $unite->load('residence');
        abort_unless($unite->active && $unite->residence->estVerifiee(), 404);
        $this->unite = $unite;
    }

    public function garder(ReservationService $service)
    {
        $this->erreur = null;
        try {
            $reservation = $service->garderDates(
                auth()->user(),
                $this->unite,
                CarbonImmutable::parse($this->arrivee),
                CarbonImmutable::parse($this->depart),
                $this->personnes,
            );
        } catch (ReservationImpossible $e) {
            $this->erreur = $e->getMessage();

            return null;
        }

        if (app(PaiementService::class)->disponible()) {
            return redirect()->route('payer', $reservation);
        }

        session()->flash('message', 'Vos dates sont gardées. Elles seront libérées automatiquement si la réservation n\'est pas confirmée à temps.');

        return redirect()->route('mes-reservations');
    }

    public function render(TarifCalculateur $tarifs, Disponibilites $disponibilites, ReservationService $service)
    {
        $unite = $this->unite->load(['residence', 'tarifs']);
        $a = $this->arrivee !== '' ? CarbonImmutable::parse($this->arrivee)->startOfDay() : null;
        $d = $this->depart !== '' ? CarbonImmutable::parse($this->depart)->startOfDay() : null;
        $valide = $a && $d && $d->gt($a) && $a->gte(now()->startOfDay());

        $detail = $valide ? $tarifs->detail($unite, $a, $d) : null;
        $total = $detail === null ? null : array_sum(array_column($detail, 'prix'));
        $libre = $valide && $disponibilites->estLibre($unite, $a, $d);
        [$acompte, $solde] = $total === null ? [null, null] : $service->repartition($unite, $total);

        return view('livewire.sejours.recapitulatif', [
            'u' => $unite,
            'valide' => $valide,
            'nuits' => $valide ? (int) $a->diffInDays($d) : null,
            'total' => $total,
            'libre' => $libre,
            'acompte' => $acompte,
            'solde' => $solde,
            'dureeHeures' => ReservationService::dureeGardeMinutes() / 60,
            'dureeMinutes' => ReservationService::dureeGardeMinutes(),
        ]);
    }
}
