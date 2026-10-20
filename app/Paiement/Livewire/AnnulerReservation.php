<?php

namespace App\Paiement\Livewire;

use App\Paiement\Services\AnnulationService;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Reservation;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Annuler ma réservation')]
class AnnulerReservation extends Component
{
    public int $reservationId;

    public ?string $erreur = null;

    public function mount(int $reservation): void
    {
        $this->reservationId = Reservation::where('client_id', auth()->id())->findOrFail($reservation)->id;
    }

    public function confirmer(AnnulationService $service)
    {
        $this->erreur = null;
        $reservation = Reservation::where('client_id', auth()->id())->with('unite.residence')->findOrFail($this->reservationId);

        $rembourse = $service->apercu($reservation, auth()->user())['rembourse'];

        try {
            $service->annuler($reservation, auth()->user());
        } catch (ReservationImpossible $e) {
            $this->erreur = $e->getMessage();

            return null;
        } catch (AuthorizationException) {
            abort(403);
        }

        session()->flash('message', 'Annulation enregistrée.'.($rembourse > 0 ? ' Votre remboursement est en cours.' : ''));

        return redirect()->route('mes-reservations');
    }

    public function render(AnnulationService $service)
    {
        $reservation = Reservation::where('client_id', auth()->id())->with('unite.residence')->findOrFail($this->reservationId);
        $annulable = in_array($reservation->statut, [StatutReservation::EnAttente, StatutReservation::Confirmee], true);
        $apercu = $annulable ? $service->apercu($reservation, auth()->user()) : null;

        return view('livewire.paiement.annuler-reservation', ['r' => $reservation, 'apercu' => $apercu, 'annulable' => $annulable]);
    }
}
