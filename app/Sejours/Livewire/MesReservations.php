<?php

namespace App\Sejours\Livewire;

use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Reservation;
use App\Sejours\Services\ReservationService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mes réservations')]
class MesReservations extends Component
{
    public ?string $erreur = null;

    public function annuler(int $id, ReservationService $service): void
    {
        $this->erreur = null;
        // Seulement parmi MES réservations : l'identifiant d'une autre personne donne « introuvable ».
        $reservation = Reservation::where('client_id', auth()->id())->with('unite.residence')->findOrFail($id);

        try {
            $service->annuler($reservation, auth()->user());
            session()->flash('message', 'Réservation annulée.');
        } catch (ReservationImpossible $e) {
            $this->erreur = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.sejours.mes-reservations', [
            'reservations' => Reservation::where('client_id', auth()->id())
                ->with('unite.residence.gerant')->latest('id')->get(),
        ]);
    }
}
