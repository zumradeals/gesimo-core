<?php

use App\Noyau\Enums\Role;
use App\Paiement\Livewire\Controle;
use App\Paiement\Livewire\PaiementsEtEcritures;
use App\Paiement\Livewire\ReversementsAdmin;
use App\Paiement\Livewire\SommesDues;
use Livewire\Livewire;

beforeEach(fn () => configurerGeniusPay());

it('un client ne peut pas ouvrir les pages de paiement d\'un autre client', function () {
    $reservation = reservationAPayer();
    $autre = creerUtilisateur(Role::Client);

    foreach (['payer', 'paiement.retour', 'reservation.confirmation', 'reservation.annuler', 'reservation.recu'] as $route) {
        expect($this->actingAs($autre)->get(route($route, $reservation))->status())->toBeIn([403, 404]);
    }
});

it('le reçu n\'existe qu\'après un paiement réussi et reste réservé au client', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();

    $this->actingAs($reservation->client)->get(route('reservation.recu', $reservation))->assertStatus(404);

    payerEtConfirmer($this, $faux, $reservation);

    $this->actingAs($reservation->client)->get(route('reservation.recu', $reservation))->assertOk();
    $this->actingAs(creerUtilisateur(Role::Gerant))->get(route('reservation.recu', $reservation))->assertForbidden();
});

it('les écrans d\'argent de l\'administrateur sont interdits aux autres rôles', function () {
    foreach (['admin.paiements', 'admin.reversements', 'admin.controle'] as $route) {
        foreach ([Role::Client, Role::Gerant, Role::Personnel] as $role) {
            $this->actingAs(creerUtilisateur($role))->get(route($route))->assertForbidden();
        }
        $this->actingAs(creerUtilisateur(Role::Admin))->get(route($route))->assertOk();
    }
});

it('le gérant ne voit que ses propres sommes, l\'administrateur voit tout', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    payerEtConfirmer($this, $faux, $reservation);
    $gerant = $reservation->unite->residence->gerant;
    $autreGerant = creerUtilisateur(Role::Gerant);
    creerResidence($autreGerant);

    $this->actingAs($gerant)->get(route('gerant.argent'))->assertOk();
    Livewire::actingAs($gerant)->test(SommesDues::class)->assertSee($reservation->client->nom ?? '', escape: false);

    $vue = Livewire::actingAs($autreGerant)->test(SommesDues::class);
    $vue->assertDontSee('Réservation n° '.$reservation->id);

    foreach ([PaiementsEtEcritures::class, ReversementsAdmin::class, Controle::class] as $page) {
        Livewire::actingAs(creerUtilisateur(Role::Admin))->test($page)->assertOk();
    }
});

it('un client n\'accède pas à l\'écran des arrivées', function () {
    $this->actingAs(creerUtilisateur(Role::Client))->get(route('gerant.arrivees'))->assertForbidden();
    $this->actingAs(creerUtilisateur(Role::Gerant))->get(route('gerant.arrivees'))->assertOk();
});
