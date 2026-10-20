<?php

use App\Noyau\Enums\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => Artisan::call('migrate:fresh', ['--force' => true]));
afterEach(fn () => Artisan::call('migrate:fresh', ['--force' => true])); // aucune donnée ne reste pour les autres tests

it('deux clients — et même huit — qui réservent la même unité aux mêmes dates en même temps : un seul réussit', function () {
    $unite = creerLogement();
    $vendredi = prochainVendredi();
    $clients = collect(range(1, 8))->map(fn () => creerUtilisateur(Role::Client));
    $depart = microtime(true) + 3; // laisse le temps de démarrer tous les processus

    $processus = $clients->map(function ($client) use ($unite, $vendredi, $depart) {
        $p = proc_open(
            [PHP_BINARY, base_path('tests/Support/reserver-en-parallele.php'), (string) $unite->id, (string) $client->id, $vendredi->toDateString(), $vendredi->addDays(3)->toDateString(), (string) $depart],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            array_merge(getenv(), [
                'APP_ENV' => 'testing',
                'APP_KEY' => config('app.key'),
                'DB_CONNECTION' => 'pgsql',
                'DB_URL' => config('database.connections.pgsql.url'),
            ]),
        );

        return [$p, $pipes];
    });

    $resultats = $processus->map(function ($ligne) {
        [$p, $pipes] = $ligne;
        $sortie = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        proc_close($p);

        return trim($sortie);
    });

    expect($resultats->filter(fn ($r) => $r === 'OK'))->toHaveCount(1)
        ->and($resultats->filter(fn ($r) => str_starts_with($r, 'REFUSE:')))->toHaveCount(7);

    // La base ne contient qu'une seule réservation active pour ces dates.
    expect(DB::table('reservation')->where('unite_id', $unite->id)->count())->toBe(1)
        ->and(DB::table('occupation_unite')->where('unite_id', $unite->id)->count())->toBe(1);
});
