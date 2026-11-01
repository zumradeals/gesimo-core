<?php

namespace App\Noyau\Console;

use App\Noyau\Messagerie\Messagerie;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Models\Reservation;
use App\Sejours\Services\PiecesVoyageur;
use Illuminate\Console\Command;

/** Rappel de la pièce d'identité : 2 jours avant l'arrivée, aux voyageurs confirmés qui ne l'ont pas encore ajoutée. */
class EnvoyerRappelsPiece extends Command
{
    protected $signature = 'gesimo:rappels-piece';

    protected $description = 'Rappelle d\'ajouter la pièce d\'identité aux clients qui arrivent dans 2 jours.';

    public function handle(): int
    {
        $envoyes = 0;

        Reservation::where('statut', StatutReservation::Confirmee->value)->where('origine', 'plateforme')
            ->whereDate('date_arrivee', now()->addDays(2)->toDateString())->with('unite.residence', 'client')
            ->each(function (Reservation $r) use (&$envoyes) {
                if (PiecesVoyageur::etat($r) === PiecesVoyageur::ETAT_A_FOURNIR
                    && Messagerie::envoyer('rappel_piece', $r->client, Messagerie::valeursReservation($r), $r) !== null) {
                    $envoyes++;
                }
            });

        $this->info("{$envoyes} rappel(s) de pièce d'identité envoyé(s).");

        return self::SUCCESS;
    }
}
