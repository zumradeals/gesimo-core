<?php

/*
 * Script lancé par tests/Concurrence : il tente une réservation à l'heure exacte donnée,
 * dans un processus PHP séparé (donc avec sa propre connexion à la base), pour imiter
 * plusieurs clients qui réservent au même instant. Ne sert qu'aux tests.
 *
 * Usage : php reserver-en-parallele.php <unite_id> <client_id> <arrivee> <depart> <heure_de_depart_en_secondes>
 */

use App\Noyau\Models\Utilisateur;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Unite;
use App\Sejours\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $uniteId, $clientId, $arrivee, $depart, $heure] = $argv;

$unite = Unite::findOrFail((int) $uniteId);
$client = Utilisateur::findOrFail((int) $clientId);

// Tous les processus attendent la même seconde, puis foncent en même temps.
while (microtime(true) < (float) $heure) {
    usleep(500);
}

try {
    app(ReservationService::class)->garderDates($client, $unite, CarbonImmutable::parse($arrivee), CarbonImmutable::parse($depart), 1);
    echo 'OK';
} catch (ReservationImpossible $e) {
    echo 'REFUSE:'.$e->getMessage();
}
