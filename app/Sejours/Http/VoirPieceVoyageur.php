<?php

namespace App\Sejours\Http;

use App\Noyau\Services\Audit;
use App\Sejours\Models\PieceVoyageur;
use App\Sejours\Models\Reservation;
use App\Sejours\Services\PiecesVoyageur;
use Illuminate\Support\Facades\Gate;

/** Ouverture de la pièce d'identité du voyageur par le gérant ou son personnel : droit vérifié, ouverture journalisée. */
class VoirPieceVoyageur
{
    public function __invoke(Reservation $reservation)
    {
        $reservation->load('unite.residence');
        Gate::authorize('voirPieceVoyageur', $reservation);

        $piece = PieceVoyageur::where('utilisateur_id', $reservation->client_id)->first();
        abort_if($piece === null || $piece->estExpiree(), 404);
        $contenu = PiecesVoyageur::contenu($piece);
        abort_if($contenu === null, 404);

        Audit::enregistrer('voyageur.piece_consultee', $reservation, ['piece_id' => $piece->id]);

        $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$piece->mime] ?? 'bin';

        return response($contenu, 200, [
            'Content-Type' => $piece->mime,
            'Content-Disposition' => 'inline; filename="piece-identite.'.$extension.'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
