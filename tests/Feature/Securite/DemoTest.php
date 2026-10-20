<?php

use App\Noyau\Models\Utilisateur;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('charge des données marquées DÉMO hors production', function () {
    $this->seed(DemoSeeder::class);

    expect(Utilisateur::count())->toBe(5)
        ->and(Utilisateur::pluck('nom')->every(fn ($nom) => str_starts_with($nom, 'DÉMO')))->toBeTrue()
        ->and(Utilisateur::pluck('email')->every(fn ($email) => str_ends_with($email, '@demo.invalid')))->toBeTrue()
        ->and(DB::table('residence')->pluck('nom')->every(fn ($nom) => str_starts_with($nom, 'DÉMO')))->toBeTrue()
        ->and(DB::table('unite')->pluck('nom')->every(fn ($nom) => str_starts_with($nom, 'DÉMO')))->toBeTrue();
});

it('refuse de se charger en production, et ne laisse aucune donnée', function (string $environnement) {
    $this->app['env'] = $environnement;

    expect(fn () => (new DemoSeeder)->run())->toThrow(RuntimeException::class, 'ne se chargent jamais en production');
    expect(Utilisateur::count())->toBe(0);
})->with(['production', 'staging', 'prod']);

it('refuse aussi en ligne de commande (db:seed), même avec --force, et ne laisse aucune donnée', function () {
    $this->app['env'] = 'production';

    expect(fn () => Artisan::call('db:seed', ['--force' => true]))->toThrow(RuntimeException::class, 'ne se chargent jamais en production');

    expect(Utilisateur::count())->toBe(0);
});

it('ne crée aucun compte à mot de passe connu : les mots de passe sont tirés au hasard', function () {
    $this->seed(DemoSeeder::class);

    $hash = Utilisateur::first()->getAuthPassword();
    expect($hash)->toStartWith('$2y$')
        ->and(password_verify('password', $hash))->toBeFalse()
        ->and(password_verify('demo', $hash))->toBeFalse();
});
