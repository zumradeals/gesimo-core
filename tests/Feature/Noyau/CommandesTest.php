<?php

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;
use App\Parametres\Services\Parametres;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

it('crée le premier super-administrateur : mot de passe saisi, haché, jamais affiché', function () {
    $this->artisan('gesimo:creer-super-admin', ['--nom' => 'Koné Djakaridja', '--telephone' => '07 00 00 00 00', '--email' => 'chef@exemple.com'])
        ->expectsQuestion('Mot de passe (8 caractères minimum)', 'un-mot-de-passe-solide')
        ->expectsQuestion('Retapez le mot de passe', 'un-mot-de-passe-solide')
        ->expectsOutputToContain('Super-administrateur créé')
        ->assertSuccessful();

    $u = Utilisateur::where('email', 'chef@exemple.com')->firstOrFail();
    expect($u->aRole(Role::SuperAdmin))->toBeTrue()->and($u->aRole(Role::Admin))->toBeTrue()
        ->and(password_verify('un-mot-de-passe-solide', $u->mot_de_passe))->toBeTrue()
        ->and($u->telephone)->toBe('0700000000');
});

it('refuse deux mots de passe différents ou trop courts, sans rien créer', function () {
    $this->artisan('gesimo:creer-super-admin', ['--nom' => 'X', '--telephone' => '0700000000', '--email' => 'x@exemple.com'])
        ->expectsQuestion('Mot de passe (8 caractères minimum)', 'un-mot-de-passe-solide')
        ->expectsQuestion('Retapez le mot de passe', 'autre-chose')
        ->assertFailed();
    $this->artisan('gesimo:creer-super-admin', ['--nom' => 'X', '--telephone' => '0700000000', '--email' => 'x@exemple.com'])
        ->expectsQuestion('Mot de passe (8 caractères minimum)', 'court')
        ->expectsQuestion('Retapez le mot de passe', 'court')
        ->assertFailed();

    expect(Utilisateur::count())->toBe(0);
});

it('la libération des réservations expirées est planifiée chaque minute', function () {
    $evenements = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains($e->command, 'gesimo:expirer-reservations'));

    expect($evenements)->toHaveCount(1)->and($evenements->first()->expression)->toBe('* * * * *');
});

it('enregistre un réglage depuis le serveur ; un secret est demandé à la saisie, chiffré, et refusé dans la commande', function () {
    $this->artisan('gesimo:parametre', ['cle' => 'email.hote', 'valeur' => 'smtp.exemple.test'])->assertSuccessful();
    expect(Parametres::obtenir('email.hote'))->toBe('smtp.exemple.test');

    $this->artisan('gesimo:parametre', ['cle' => 'email.mot_de_passe', 'valeur' => 'visible-dans-l-historique', '--secret' => true])->assertFailed();
    expect(Parametres::obtenir('email.mot_de_passe'))->toBeNull();

    $this->artisan('gesimo:parametre', ['cle' => 'email.mot_de_passe', '--secret' => true])
        ->expectsQuestion('Valeur de email.mot_de_passe (masquée)', 'mdp-smtp-secret')->assertSuccessful();
    expect(Parametres::obtenir('email.mot_de_passe'))->toBe('mdp-smtp-secret')
        ->and(DB::table('parametre')->where('cle', 'email.mot_de_passe')->value('valeur'))->not->toContain('mdp-smtp-secret');

    $this->artisan('gesimo:parametre', ['cle' => 'Nom Invalide', 'valeur' => 'x'])->assertFailed();
});
