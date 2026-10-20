<?php

namespace App\Noyau\Console;

use App\Noyau\Messagerie\Messagerie;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Models\Reservation;
use Illuminate\Console\Command;

/** Rappel d'arrivée : la veille, à chaque client dont la réservation est confirmée. Sans danger si relancée. */
class EnvoyerRappelsArrivee extends Command
{
    protected $signature = 'gesimo:rappels-arrivee';

    protected $description = 'Envoie un e-mail de rappel aux clients qui arrivent demain.';

    public function handle(): int
    {
        $demain = now()->addDay()->toDateString();
        $envoyes = 0;

        Reservation::where('statut', StatutReservation::Confirmee->value)->where('origine', 'plateforme')
            ->whereDate('date_arrivee', $demain)->with('unite.residence', 'client')
            ->each(function (Reservation $r) use (&$envoyes) {
                if (Messagerie::envoyer('rappel_arrivee', $r->client, Messagerie::valeursReservation($r), $r) !== null) {
                    $envoyes++;
                }
            });

        $this->info("{$envoyes} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
