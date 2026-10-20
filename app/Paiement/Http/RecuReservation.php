<?php

namespace App\Paiement\Http;

use App\Paiement\Models\Paiement;
use App\Sejours\Models\Reservation;
use Illuminate\Support\Facades\Gate;

/** Reçu imprimable (« Enregistrer en PDF » depuis le navigateur) : seulement après un paiement réussi, et seulement pour son propriétaire. */
class RecuReservation
{
    public function __invoke(int $reservation)
    {
        $r = Reservation::with('unite.residence', 'client')->findOrFail($reservation);
        Gate::authorize('voirRecu', $r);

        $paiement = Paiement::where('reservation_id', $r->id)->where('statut', 'reussi')->orderBy('id')->first();
        abort_if($paiement === null, 404, 'Ce reçu n\'est disponible qu\'après la confirmation du paiement.');

        return view('paiement.recu', ['r' => $r, 'paiement' => $paiement]);
    }
}
