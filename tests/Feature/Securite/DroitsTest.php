<?php

use App\Noyau\Enums\Role;
use App\Sejours\Models\MembreResidence;
use App\Sejours\Models\Reservation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'role:super_admin'])->get('/__test/parametres', fn () => 'ok');
    Route::middleware(['web', 'role:admin'])->get('/__test/admin', fn () => 'ok');
    Route::middleware(['web', 'role:gerant,admin'])->get('/__test/gerant-ou-admin', fn () => 'ok');
});

it('refuse côté serveur (403) un utilisateur sans le bon rôle', function () {
    $this->actingAs(creerUtilisateur(Role::Client))->get('/__test/parametres')->assertForbidden();
    $this->actingAs(creerUtilisateur(Role::Gerant))->get('/__test/admin')->assertForbidden();
    $this->actingAs(creerUtilisateur(Role::Admin))->get('/__test/parametres')->assertForbidden();
    $this->actingAs(creerUtilisateur())->get('/__test/gerant-ou-admin')->assertForbidden();
});

it('refuse (401) un visiteur non connecté', function () {
    $this->get('/__test/admin')->assertUnauthorized();
});

it('accepte le bon rôle, et le super-administrateur fait tout ce que fait l\'admin', function () {
    $this->actingAs(creerUtilisateur(Role::SuperAdmin))->get('/__test/parametres')->assertOk();
    $this->actingAs(creerUtilisateur(Role::SuperAdmin))->get('/__test/admin')->assertOk();
    $this->actingAs(creerUtilisateur(Role::Admin))->get('/__test/admin')->assertOk();
    $this->actingAs(creerUtilisateur(Role::Gerant))->get('/__test/gerant-ou-admin')->assertOk();
});

it('refuse un compte suspendu, même avec le bon rôle', function () {
    $admin = creerUtilisateur(Role::Admin);
    $admin->update(['statut' => 'suspendu']);

    $this->actingAs($admin)->get('/__test/admin')->assertForbidden();
});

it('réserve les Paramètres au super-administrateur', function () {
    expect(Gate::forUser(creerUtilisateur(Role::SuperAdmin))->allows('modifier-parametres'))->toBeTrue()
        ->and(Gate::forUser(creerUtilisateur(Role::Admin))->allows('modifier-parametres'))->toBeFalse()
        ->and(Gate::forUser(creerUtilisateur(Role::Gerant))->allows('modifier-parametres'))->toBeFalse()
        ->and(Gate::forUser(creerUtilisateur(Role::Client))->allows('modifier-parametres'))->toBeFalse();
});

it('réserve journal d\'audit, comptabilité et reversements à l\'équipe Avelis', function (string $droit) {
    expect(Gate::forUser(creerUtilisateur(Role::Admin))->allows($droit))->toBeTrue()
        ->and(Gate::forUser(creerUtilisateur(Role::Gerant, Role::Client, Role::Personnel))->allows($droit))->toBeFalse();
})->with(['voir-journal-audit', 'voir-comptabilite', 'enregistrer-reversement']);

it('un gérant ne modifie que ses propres résidences', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $autreGerant = creerUtilisateur(Role::Gerant);
    $residence = creerResidence($gerant);

    expect(Gate::forUser($gerant)->allows('update', $residence))->toBeTrue()
        ->and(Gate::forUser($autreGerant)->allows('update', $residence))->toBeFalse()
        ->and(Gate::forUser(creerUtilisateur(Role::Client))->allows('update', $residence))->toBeFalse()
        ->and(Gate::forUser($gerant)->allows('verifier', $residence))->toBeFalse()
        ->and(Gate::forUser(creerUtilisateur(Role::Admin))->allows('verifier', $residence))->toBeTrue();
});

it('une résidence non vérifiée n\'est pas visible du public', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $enAttente = creerResidence($gerant, ['statut_verification' => 'en_attente']);
    $verifiee = creerResidence($gerant);

    expect(Gate::forUser(null)->allows('view', $verifiee))->toBeTrue()
        ->and(Gate::forUser(null)->allows('view', $enAttente))->toBeFalse()
        ->and(Gate::forUser($gerant)->allows('view', $enAttente))->toBeTrue()
        ->and(Gate::forUser(creerUtilisateur(Role::Client))->allows('view', $enAttente))->toBeFalse();
});

it('une réservation n\'est visible que de son client, du gérant, du personnel autorisé et de l\'admin', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $client = creerUtilisateur(Role::Client);
    $unite = creerUnite(creerResidence($gerant));
    $reservation = Reservation::find(insererReservationSql($unite, '2026-12-10', '2026-12-14', ['client_id' => $client->id]));

    $personnelAutorise = creerUtilisateur(Role::Personnel);
    MembreResidence::create(['residence_id' => $unite->residence_id, 'utilisateur_id' => $personnelAutorise->id, 'peut_gerer_checkin_checkout' => true]);
    $personnelSansDroit = creerUtilisateur(Role::Personnel);
    MembreResidence::create(['residence_id' => $unite->residence_id, 'utilisateur_id' => $personnelSansDroit->id]);

    expect(Gate::forUser($client)->allows('view', $reservation))->toBeTrue()
        ->and(Gate::forUser($gerant)->allows('view', $reservation))->toBeTrue()
        ->and(Gate::forUser($personnelAutorise)->allows('view', $reservation))->toBeTrue()
        ->and(Gate::forUser(creerUtilisateur(Role::Admin))->allows('view', $reservation))->toBeTrue()
        ->and(Gate::forUser($personnelSansDroit)->allows('view', $reservation))->toBeFalse()
        ->and(Gate::forUser(creerUtilisateur(Role::Client))->allows('view', $reservation))->toBeFalse()
        ->and(Gate::forUser(creerUtilisateur(Role::Gerant))->allows('view', $reservation))->toBeFalse();
});

it('seul le gérant de la résidence saisit une réservation par téléphone, et le personnel autorisé fait le check-in', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $unite = creerUnite(creerResidence($gerant));
    $reservation = Reservation::find(insererReservationSql($unite, '2026-12-10', '2026-12-14'));
    $personnel = creerUtilisateur(Role::Personnel);
    MembreResidence::create(['residence_id' => $unite->residence_id, 'utilisateur_id' => $personnel->id, 'peut_gerer_checkin_checkout' => true]);

    expect(Gate::forUser($gerant)->allows('creerHorsPlateforme', [Reservation::class, $unite]))->toBeTrue()
        ->and(Gate::forUser(creerUtilisateur(Role::Gerant))->allows('creerHorsPlateforme', [Reservation::class, $unite]))->toBeFalse()
        ->and(Gate::forUser(creerUtilisateur(Role::Client))->allows('creerHorsPlateforme', [Reservation::class, $unite]))->toBeFalse()
        ->and(Gate::forUser($personnel)->allows('enregistrerArrivee', $reservation))->toBeTrue()
        ->and(Gate::forUser(creerUtilisateur(Role::Client))->allows('enregistrerArrivee', $reservation))->toBeFalse();
});

it('un compte suspendu perd tous ses droits', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = creerResidence($gerant);
    $gerant->update(['statut' => 'suspendu']);

    expect(Gate::forUser($gerant->fresh())->allows('update', $residence))->toBeFalse();
});
