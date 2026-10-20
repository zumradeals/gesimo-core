<?php

namespace App\Sejours\Livewire;

use App\Sejours\Models\Avis;
use App\Sejours\Models\Litige;
use App\Sejours\Models\Reservation;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Ma réservation')]
class DetailReservation extends Component
{
    public int $reservationId;

    public function mount(int $reservation): void
    {
        $this->reservationId = Reservation::where('client_id', auth()->id())->findOrFail($reservation)->id;
    }

    public function render()
    {
        $r = Reservation::with('unite.residence.gerant')->findOrFail($this->reservationId);

        return view('livewire.sejours.detail-reservation', [
            'r' => $r,
            'paye' => (int) $r->paiements()->where('statut', 'reussi')->sum('montant'),
            'avis' => Avis::where('reservation_id', $r->id)->first(),
            'litigeOuvert' => Litige::where('reservation_id', $r->id)->where('ouvert_par', auth()->id())->where('statut', 'ouvert')->first(),
        ]);
    }
}
