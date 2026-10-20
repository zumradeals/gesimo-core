<?php

namespace App\Paiement\Livewire;

use App\Paiement\Models\AlerteAdmin;
use App\Paiement\Models\Paiement;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Models\Reservation;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Réservation confirmée')]
class Confirmation extends Component
{
    public int $reservationId;

    public function mount(int $reservation): void
    {
        $r = Reservation::where('client_id', auth()->id())->findOrFail($reservation);
        // Pas de confirmation sans paiement vérifié : on renvoie vers le paiement.
        abort_unless(Paiement::where('reservation_id', $r->id)->where('statut', 'reussi')->exists() && $r->statut !== StatutReservation::EnAttente, 404);
        $this->reservationId = $r->id;
    }

    public function render()
    {
        $r = Reservation::with('unite.residence.gerant')->findOrFail($this->reservationId);
        $paiement = Paiement::where('reservation_id', $r->id)->where('statut', 'reussi')->first();

        return view('livewire.paiement.confirmation', [
            'r' => $r,
            'paiement' => $paiement,
            'tardif' => $paiement && AlerteAdmin::where('paiement_id', $paiement->id)->where('type', 'paiement_tardif_reattribue')->exists(),
        ]);
    }
}
