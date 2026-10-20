<?php

namespace App\Sejours\Livewire;

use App\Paiement\Services\AnnulationService;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Reservation;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Mes réservations')]
class MesReservations extends Component
{
    #[Url]
    public string $onglet = 'avenir';

    public ?string $erreur = null;

    public function annuler(int $id, AnnulationService $service): void
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
        $mes = Reservation::where('client_id', auth()->id());
        $enCours = [StatutReservation::EnAttente->value, StatutReservation::Confirmee->value, StatutReservation::ArriveeEnregistree->value, StatutReservation::Litige->value];
        $avenir = (clone $mes)->whereIn('statut', $enCours)->whereDate('date_depart', '>=', now()->toDateString());

        return view('livewire.sejours.mes-reservations', [
            'reservations' => ($this->onglet === 'passees'
                ? (clone $mes)->whereNotIn('id', (clone $avenir)->select('id'))->latest('date_arrivee')
                : (clone $avenir)->orderBy('date_arrivee'))->with('unite.residence.gerant')->get(),
            'nbAVenir' => (clone $avenir)->count(),
            'nbPassees' => (clone $mes)->whereNotIn('id', (clone $avenir)->select('id'))->count(),
        ]);
    }
}
