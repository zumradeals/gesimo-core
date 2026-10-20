<?php

namespace App\Sejours\Livewire;

use App\Sejours\Enums\StatutReservation;
use App\Sejours\Models\Litige;
use App\Sejours\Models\Reservation;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Réservations')]
class ReservationsGerant extends Component
{
    #[Url]
    public string $onglet = 'avenir';

    public ?int $ouverte = null;

    public function detail(int $id): void
    {
        $this->ouverte = $this->ouverte === $id ? null : $id;
    }

    public function render()
    {
        $base = Reservation::whereHas('unite.residence', fn ($q) => $q->where('gerant_id', auth()->id()))
            ->whereNotIn('statut', [StatutReservation::EnAttente->value, StatutReservation::Expiree->value]);
        $avenir = (clone $base)->whereIn('statut', [StatutReservation::Confirmee->value, StatutReservation::ArriveeEnregistree->value, StatutReservation::Litige->value, StatutReservation::HorsPlateforme->value])
            ->whereDate('date_depart', '>=', now()->toDateString());

        return view('livewire.sejours.reservations-gerant', [
            'reservations' => ($this->onglet === 'passees'
                ? (clone $base)->whereNotIn('id', (clone $avenir)->select('id'))->latest('date_arrivee')
                : (clone $avenir)->orderBy('date_arrivee'))->with('unite.residence', 'client')->limit(100)->get(),
            'nbAVenir' => (clone $avenir)->count(),
            'nbPassees' => (clone $base)->whereNotIn('id', (clone $avenir)->select('id'))->count(),
            'litigesOuverts' => Litige::where('statut', 'ouvert')->pluck('reservation_id')->all(),
        ]);
    }
}
