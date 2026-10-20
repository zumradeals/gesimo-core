<?php

namespace App\Sejours\Services;

use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Avis;
use App\Sejours\Models\Reservation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;

/** F-C7 : un avis, une seule fois, uniquement après un séjour terminé (vérifié aussi par la base de données). */
class AvisService
{
    public function donner(Reservation $reservation, Utilisateur $client, int $note, ?string $commentaire): Avis
    {
        Gate::forUser($client)->authorize('donnerAvis', $reservation);

        if ($reservation->statut !== StatutReservation::Terminee) {
            throw new ReservationImpossible('Vous pourrez donner votre avis une fois le séjour terminé.');
        }
        if ($note < 1 || $note > 5) {
            throw new ReservationImpossible('Choisissez une note de 1 à 5 étoiles.');
        }
        $commentaire = trim((string) $commentaire);
        if (mb_strlen($commentaire) > 1000) {
            throw new ReservationImpossible('Votre commentaire est trop long (1 000 caractères au maximum).');
        }

        try {
            $avis = Avis::create(['reservation_id' => $reservation->id, 'client_id' => $client->id, 'note' => $note, 'commentaire' => $commentaire === '' ? null : $commentaire]);
        } catch (UniqueConstraintViolationException) {
            throw new ReservationImpossible('Vous avez déjà donné votre avis pour ce séjour.');
        }
        Audit::enregistrer('avis.donne', $avis, ['note' => $note], $client);

        return $avis;
    }

    /** @return array{moyenne: ?float, nombre: int} */
    public function resume(int $residenceId): array
    {
        $base = Avis::whereHas('reservation.unite', fn ($q) => $q->where('residence_id', $residenceId));

        return ['moyenne' => ($m = $base->avg('note')) === null ? null : round((float) $m, 1), 'nombre' => $base->count()];
    }
}
