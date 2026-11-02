<?php

namespace App\Noyau\Livewire;

use App\Noyau\Enums\Role;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Models\Reservation;
use App\Sejours\Services\PiecesVoyageur;
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

        $prochain = $mes?->clone()->where('statut', StatutReservation::Confirmee->value)->whereDate('date_depart', '>=', now()->toDateString())->orderBy('date_arrivee')->first();
        $avisADonner = $mes?->clone()->where('statut', StatutReservation::Terminee->value)->whereDoesntHave('avis')->latest('date_depart')->with('unite.residence')->limit(3)->get() ?? collect();

        return view('livewire.noyau.compte', [
            'utilisateur' => $u,
            'aFaire' => $client ? $this->aFaire($u, $mes, $prochain) : null,
            'prochain' => $prochain,
            'avisADonner' => $avisADonner,
            'nombreSejours' => $mes?->clone()->where('statut', StatutReservation::Terminee->value)->count() ?? 0,
            'nombreAVenir' => $mes?->clone()->whereIn('statut', [StatutReservation::Confirmee->value, StatutReservation::EnAttente->value])->whereDate('date_depart', '>=', now()->toDateString())->count() ?? 0,
        ]);
    }

    /**
     * La première chose à faire, avec les mêmes règles que « Mes réservations » : finir un paiement, ajouter la pièce
     * d'identité (séjour confirmé réservé sur la plateforme), puis donner un avis.
     *
     * @return array{type: string, reservation: Reservation}|null
     */
    private function aFaire($u, $mes, ?Reservation $prochain): ?array
    {
        $enAttente = $mes->clone()->where('statut', StatutReservation::EnAttente->value)->where('expire_le', '>', now())->orderBy('expire_le')->with('unite.residence')->first();
        if ($enAttente) {
            return ['type' => 'paiement', 'reservation' => $enAttente];
        }
        if ($prochain && $prochain->origine === 'plateforme' && PiecesVoyageur::pieceValide($u) === null) {
            return ['type' => 'piece', 'reservation' => $prochain];
        }
        $avis = $mes->clone()->where('statut', StatutReservation::Terminee->value)->whereDoesntHave('avis')->latest('date_depart')->with('unite.residence')->first();

        return $avis ? ['type' => 'avis', 'reservation' => $avis] : null;
    }
}
