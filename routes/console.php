<?php

use App\Sejours\Console\ExpirerReservations;
use Illuminate\Support\Facades\Schedule;

// RG-2 : libération automatique des réservations en attente dont le délai est passé.
Schedule::command(ExpirerReservations::class)->everyMinute()->withoutOverlapping();
