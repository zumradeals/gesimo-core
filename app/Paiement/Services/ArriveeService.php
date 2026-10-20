<?php

namespace App\Paiement\Services;

use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Checkin;
use App\Sejours\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Check-in et check-out minimaux. L'arrivée enregistrée rend la part du gérant « à reverser » (RG-15) :
 * la commission est retenue à ce moment-là, sur l'argent encaissé.
 */
class ArriveeService
{
    public function __construct(private AnnulationService $annulations) {}

    public function enregistrerArrivee(Reservation $reservation, Utilisateur $agent, ?string $pieceType = null, ?string $pieceNumero = null): Reservation
    {
        $reservation->loadMissing('unite.residence');
        Gate::forUser($agent)->authorize('enregistrerArrivee', $reservation);

        DB::transaction(function () use ($reservation, $agent, $pieceType, $pieceNumero) {
            $verrouillee = Reservation::whereKey($reservation->id)->lockForUpdate()->first();
            if ($verrouillee->statut !== StatutReservation::Confirmee) {
                throw new ReservationImpossible('Cette réservation n\'est pas confirmée (acompte non payé) : l\'arrivée ne peut pas être enregistrée.');
            }
            $aujourdhui = now()->startOfDay();
            if ($aujourdhui->lt($verrouillee->date_arrivee->startOfDay())) {
                throw new ReservationImpossible('L\'arrivée ne peut être enregistrée qu\'à partir de la date d\'arrivée prévue.');
            }
            if ($aujourdhui->gte($verrouillee->date_depart->startOfDay())) {
                throw new ReservationImpossible('Le séjour est terminé : l\'arrivée ne peut plus être enregistrée.');
            }

            Checkin::create([
                'reservation_id' => $verrouillee->id, 'arrivee_le' => now(), 'enregistre_par' => $agent->id,
                'piece_type' => $pieceType, 'piece_numero' => $pieceNumero, // le numéro est chiffré en base
            ]);
            $verrouillee->update(['statut' => StatutReservation::ArriveeEnregistree]);
            $verrouillee->setRelation('unite', $reservation->unite);
            $this->annulations->acquerir($verrouillee, Comptabilite::encaisse($verrouillee), $verrouillee->montant_total, $agent, 'Arrivée enregistrée');
            Audit::enregistrer('reservation.arrivee', $verrouillee, [], $agent);
        });

        return $reservation->fresh();
    }

    public function enregistrerDepart(Reservation $reservation, Utilisateur $agent): Reservation
    {
        $reservation->loadMissing('unite.residence');
        Gate::forUser($agent)->authorize('enregistrerArrivee', $reservation);

        DB::transaction(function () use ($reservation, $agent) {
            $verrouillee = Reservation::whereKey($reservation->id)->lockForUpdate()->first();
            if ($verrouillee->statut !== StatutReservation::ArriveeEnregistree) {
                throw new ReservationImpossible('Le départ ne peut être enregistré que pour un client dont l\'arrivée est enregistrée.');
            }
            $verrouillee->update(['statut' => StatutReservation::Terminee]);
            Checkin::where('reservation_id', $verrouillee->id)->update(['depart_le' => now(), 'depart_enregistre_par' => $agent->id]);
            Audit::enregistrer('reservation.depart', $verrouillee, [], $agent);
        });

        return $reservation->fresh();
    }
}
