<?php

namespace App\Noyau\Livewire;

use App\Noyau\Enums\Role;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Models\Reservation;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mon compte')]
class Compte extends Component
{
    public function render()
    {
        $u = auth()->user()->load('roles');
        $client = $u->aRole(Role::Client);
        $mes = $client ? Reservation::where('client_id', $u->id)->with(['unite.photos', 'unite.residence.photos', 'unite.residence.gerant']) : null;

        return view('livewire.noyau.compte', [
            'utilisateur' => $u,
            'prochain' => $mes?->clone()->where('statut', StatutReservation::Confirmee->value)->whereDate('date_depart', '>=', now()->toDateString())->orderBy('date_arrivee')->first(),
            'avisADonner' => $mes?->clone()->where('statut', StatutReservation::Terminee->value)->whereDoesntHave('avis')->latest('date_depart')->with('unite')->limit(3)->get() ?? collect(),
            'nombreSejours' => $mes?->clone()->where('statut', StatutReservation::Terminee->value)->count() ?? 0,
            'nombreAVenir' => $mes?->clone()->whereIn('statut', [StatutReservation::Confirmee->value, StatutReservation::EnAttente->value])->whereDate('date_depart', '>=', now()->toDateString())->count() ?? 0,
        ]);
    }
}
