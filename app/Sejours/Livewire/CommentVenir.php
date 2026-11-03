<?php

namespace App\Sejours\Livewire;

use App\Sejours\Enums\StatutReservation as S;
use App\Sejours\Models\Reservation;
use Livewire\Attributes\Title;
use Livewire\Component;

/** « Comment venir » : adresse, repères et itinéraire, donnés seulement une fois la réservation confirmée. */
#[Title('Comment venir')]
class CommentVenir extends Component
{
    public int $reservationId;

    public function mount(int $reservation): void
    {
        $r = Reservation::where('client_id', auth()->id())->where('origine', 'plateforme')->findOrFail($reservation);
        abort_unless(in_array($r->statut, [S::EnAttente, S::Confirmee, S::ArriveeEnregistree, S::Terminee, S::Litige], true), 404);
        $this->reservationId = $r->id;
    }

    public function render()
    {
        $r = Reservation::with('unite.residence.gerant')->findOrFail($this->reservationId);

        return view('livewire.sejours.comment-venir', ['r' => $r, 'res' => $r->unite->residence, 'visible' => $r->statut !== S::EnAttente]);
    }
}
