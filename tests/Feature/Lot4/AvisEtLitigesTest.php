<?php

use App\Noyau\Enums\Role;
use App\Noyau\Mail\MessageGesimo;
use App\Paiement\Services\ArriveeService;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Livewire\Admin\LitigesAdmin;
use App\Sejours\Livewire\DonnerAvis;
use App\Sejours\Livewire\FicheLogement;
use App\Sejours\Livewire\OuvrirLitige;
use App\Sejours\Models\Avis;
use App\Sejours\Models\Litige;
use App\Sejours\Services\AvisService;
use App\Sejours\Services\LitigeService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    configurerGeniusPay();
    Mail::fake();
});

// ---------------------------------------------------------------- Avis (F-C7)
it('un avis n\'est possible qu\'après un séjour terminé, par son client, une seule fois', function () {
    $faux = fauxGeniusPay();
    $enCours = reservationAPayer();
    payerEtConfirmer($this, $faux, $enCours);

    // Séjour non terminé : refusé par l'application avec un message clair...
    expect(fn () => app(AvisService::class)->donner($enCours->fresh(), $enCours->client, 5, 'Super'))->toThrow(ReservationImpossible::class, 'une fois le séjour terminé');
    // ... et par la base de données si on essaie de la contourner.
    expect(codeRefusBase(fn () => Avis::create(['reservation_id' => $enCours->id, 'client_id' => $enCours->client_id, 'note' => 5])))->toBe('23514');

    $terminee = reservationTerminee($this, $faux);
    $autre = creerUtilisateur(Role::Client);
    expect(fn () => app(AvisService::class)->donner($terminee, $autre, 5, null))->toThrow(AuthorizationException::class);
    expect(fn () => app(AvisService::class)->donner($terminee, $terminee->client, 6, null))->toThrow(ReservationImpossible::class, '1 à 5');

    app(AvisService::class)->donner($terminee, $terminee->client, 4, '  Très propre  ');
    expect(Avis::sole()->commentaire)->toBe('Très propre');
    expect(fn () => app(AvisService::class)->donner($terminee, $terminee->client, 5, null))->toThrow(ReservationImpossible::class, 'déjà donné');
});

it('la page d\'avis publie, puis l\'avis apparaît sur la fiche du logement', function () {
    $faux = fauxGeniusPay();
    $r = reservationTerminee($this, $faux);

    Livewire::actingAs($r->client)->test(DonnerAvis::class, ['reservation' => $r->id])
        ->call('choisir', 3)->set('commentaire', 'Correct')->call('publier')->assertSee('Votre avis est publié');

    Livewire::test(FicheLogement::class, ['unite' => $r->unite])->assertSee('3,0 / 5')->assertSee('Correct');
    // Les autres ne peuvent pas ouvrir la page d'avis d'un client.
    $this->actingAs(creerUtilisateur(Role::Client))->get(route('reservation.avis', $r))->assertNotFound();
});

it('la page d\'avis d\'un séjour non terminé explique qu\'il faut attendre', function () {
    $faux = fauxGeniusPay();
    $r = reservationAPayer();
    payerEtConfirmer($this, $faux, $r);

    Livewire::actingAs($r->client)->test(DonnerAvis::class, ['reservation' => $r->id])->call('publier')->assertSee('une fois le séjour terminé');
    expect(Avis::count())->toBe(0);
});

// ---------------------------------------------------------------- Litiges (F-A5)
it('un client ouvre un litige : la réservation passe « En litige », les deux parties sont prévenues, plus de check-in', function () {
    $faux = fauxGeniusPay();
    $r = reservationAPayer(['arrivee' => CarbonImmutable::today(), 'nuits' => 2]);
    payerEtConfirmer($this, $faux, $r);
    Mail::fake();

    $litige = app(LitigeService::class)->ouvrir($r->fresh(), $r->client, 'La climatisation ne marche pas du tout.');

    expect($r->fresh()->statut)->toBe(StatutReservation::Litige)->and($litige->statut_avant)->toBe('confirmee')->and($litige->partie_adverse_id)->toBe($r->unite->residence->gerant_id);
    Mail::assertSent(MessageGesimo::class, 2);
    expect(fn () => app(ArriveeService::class)->enregistrerArrivee($r->fresh(), $r->unite->residence->gerant))->toThrow(ReservationImpossible::class);
    // Pas deux litiges ouverts par la même personne.
    expect(fn () => app(LitigeService::class)->ouvrir($r->fresh(), $r->client, 'Un autre problème encore.'))->toThrow(ReservationImpossible::class, 'déjà signalé');
});

it('le gérant peut aussi ouvrir un litige ; un inconnu ne le peut pas ; le motif est obligatoire', function () {
    $faux = fauxGeniusPay();
    $r = reservationAPayer();
    payerEtConfirmer($this, $faux, $r);
    $gerant = $r->unite->residence->gerant;

    expect(fn () => app(LitigeService::class)->ouvrir($r->fresh(), $gerant, 'court'))->toThrow(ReservationImpossible::class, 'quelques mots');
    expect(fn () => app(LitigeService::class)->ouvrir($r->fresh(), creerUtilisateur(Role::Client), 'Un motif assez long'))->toThrow(AuthorizationException::class);
    $this->actingAs(creerUtilisateur(Role::Gerant))->get(route('gerant.probleme', $r))->assertNotFound();

    $litige = app(LitigeService::class)->ouvrir($r->fresh(), $gerant, 'Le client a abîmé un meuble.');
    expect($litige->partie_adverse_id)->toBe($r->client_id);
    Livewire::actingAs($gerant)->test(OuvrirLitige::class, ['reservation' => $r->id])->assertOk();
});

it('un litige sur un séjour terminé ne change pas son statut', function () {
    $faux = fauxGeniusPay();
    $r = reservationTerminee($this, $faux);

    app(LitigeService::class)->ouvrir($r, $r->client, 'Objet perdu dans le logement.');
    expect($r->fresh()->statut)->toBe(StatutReservation::Terminee);
});

it('l\'administrateur clôt le litige : la réservation retrouve son statut, les deux parties reçoivent la décision', function () {
    $faux = fauxGeniusPay();
    $r = reservationAPayer();
    payerEtConfirmer($this, $faux, $r);
    $litige = app(LitigeService::class)->ouvrir($r->fresh(), $r->client, 'La clé ne fonctionne pas du tout.');
    Mail::fake();

    Livewire::actingAs(creerUtilisateur(Role::Admin))->test(LitigesAdmin::class)
        ->call('clore', $litige->id)->assertSee('Écrivez la décision')
        ->set("decisions.{$litige->id}", 'Clé remplacée par le gérant.')->call('clore', $litige->id)->assertSee('Litige clos');

    expect($r->fresh()->statut)->toBe(StatutReservation::Confirmee)->and($litige->fresh()->statut)->toBe('resolu')->and($litige->fresh()->decision)->toBe('Clé remplacée par le gérant.');
    Mail::assertSent(MessageGesimo::class, 2);
    Mail::assertSent(MessageGesimo::class, fn ($m) => str_contains($m->texte, 'Clé remplacée'));
    // Déjà clos : pas de deuxième clôture.
    expect(fn () => app(LitigeService::class)->clore($litige->fresh(), creerUtilisateur(Role::Admin), 'Encore une décision'))->toThrow(ReservationImpossible::class, 'déjà clos');
});

it('avec deux litiges ouverts, la réservation ne repart qu\'à la clôture du dernier', function () {
    $faux = fauxGeniusPay();
    $r = reservationAPayer();
    payerEtConfirmer($this, $faux, $r);
    $admin = creerUtilisateur(Role::Admin);
    $a = app(LitigeService::class)->ouvrir($r->fresh(), $r->client, 'Premier problème signalé ici.');
    $b = app(LitigeService::class)->ouvrir($r->fresh(), $r->unite->residence->gerant, 'Second problème signalé ici.');

    app(LitigeService::class)->clore($a, $admin, 'Réglé par téléphone');
    expect($r->fresh()->statut)->toBe(StatutReservation::Litige);
    app(LitigeService::class)->clore($b, $admin, 'Réglé aussi');
    expect($r->fresh()->statut)->toBe(StatutReservation::Confirmee);
});

it('seuls les administrateurs voient et traitent les litiges', function () {
    foreach ([Role::Client, Role::Gerant, Role::Personnel] as $role) {
        $this->actingAs(creerUtilisateur($role))->get(route('admin.litiges'))->assertForbidden();
    }
    $this->actingAs(creerUtilisateur(Role::Admin))->get(route('admin.litiges'))->assertOk()->assertSee('Aucun litige ouvert');
    $r = reservationAPayer();
    expect(fn () => app(LitigeService::class)->clore(new Litige(['id' => 1]), creerUtilisateur(Role::Client), 'Décision quelconque'))->toThrow(AuthorizationException::class);
});
