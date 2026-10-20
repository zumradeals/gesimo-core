<?php

namespace App\Sejours\Console;

use App\Sejours\Services\ReservationService;
use Illuminate\Console\Command;

class ExpirerReservations extends Command
{
    protected $signature = 'gesimo:expirer-reservations';

    protected $description = 'Libère les réservations « En attente » dont le délai de garde est passé';

    public function handle(ReservationService $service): int
    {
        $nombre = $service->expirerLesReservations();
        $this->info("{$nombre} réservation(s) libérée(s).");

        return self::SUCCESS;
    }
}
