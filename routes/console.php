<?php

use App\Noyau\Console\EnvoyerRappelsArrivee;
use App\Paiement\Services\MiseEnAvantService;
use App\Sejours\Console\ExpirerReservations;
use Illuminate\Support\Facades\Schedule;

// RG-2 : libération automatique des réservations en attente dont le délai est passé.
Schedule::command(ExpirerReservations::class)->everyMinute()->withoutOverlapping();

// Rappel d'arrivée la veille (e-mail), chaque jour à 9 h.
Schedule::command(EnvoyerRappelsArrivee::class)->dailyAt('09:00')->withoutOverlapping();

// Mise en avant : les périodes dépassées passent « Terminée » (l'annonce redevenait déjà normale à la date de fin).
Schedule::call(fn () => app(MiseEnAvantService::class)->ranger())->name('mises-en-avant-ranger')->hourly()->withoutOverlapping();
