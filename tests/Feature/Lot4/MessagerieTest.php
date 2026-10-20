<?php

use App\Noyau\Enums\Role;
use App\Noyau\Livewire\Admin\ModelesEmails;
use App\Noyau\Mail\MessageGesimo;
use App\Noyau\Messagerie\ModelesMessages;
use App\Noyau\Models\Notification;
use App\Paiement\Models\Reversement;
use App\Paiement\Services\AnnulationService;
use App\Paiement\Services\ArriveeService;
use App\Paiement\Services\ReversementService;
use App\Parametres\Services\Parametres;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    configurerGeniusPay();
    Mail::fake();
});

it('envoie un e-mail de confirmation quand le paiement est vérifié, et un seul même si le message est rejoué', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $paiement = payerEtConfirmer($this, $faux, $reservation);
    recevoirWebhook($this, 'payment.success', $paiement->reference_geniuspay, $paiement->montant)->assertOk(); // rejoué

    Mail::assertSent(MessageGesimo::class, 1);
    Mail::assertSent(MessageGesimo::class, fn ($m) => $m->hasTo($reservation->client->email) && str_contains($m->sujet, 'Réservation confirmée'));
    $notification = Notification::where('modele', 'confirmation')->sole();
    expect($notification->statut)->toBe('envoye')->and($notification->envoye_le)->not->toBeNull();
});

it('le texte de l\'e-mail vient des Paramètres, et les mots entre accolades sont remplacés', function () {
    ModelesMessages::definir('confirmation', 'Bravo {nom} pour {logement}', "Réservation {numero} : {montant} payés.\n{xyz}");
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    payerEtConfirmer($this, $faux, $reservation);

    Mail::assertSent(MessageGesimo::class, fn ($m) => str_starts_with($m->sujet, 'Bravo '.$reservation->client->nom.' pour ') && str_contains($m->texte, "Réservation {$reservation->id} :"));
    expect(Parametres::obtenir('message.confirmation.sujet'))->toBe('Bravo {nom} pour {logement}');
});

it('un message qui ne peut pas partir est noté « échoué » sans bloquer la confirmation', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Serveur de messagerie injoignable'));
    payerEtConfirmer($this, $faux, $reservation);

    expect($reservation->fresh()->statut->value)->toBe('confirmee')
        ->and(Notification::where('modele', 'confirmation')->sole()->statut)->toBe('echoue')
        ->and(Notification::where('modele', 'confirmation')->sole()->erreur)->toContain('injoignable');
});

it('le rappel d\'arrivée part la veille, une seule fois', function () {
    $faux = fauxGeniusPay();
    $demain = CarbonImmutable::tomorrow();
    $reservation = reservationAPayer(['arrivee' => $demain, 'nuits' => 2]);
    payerEtConfirmer($this, $faux, $reservation);
    $lointaine = reservationAPayer(['arrivee' => prochainVendredi()->addDays(28)]);
    payerEtConfirmer($this, $faux, $lointaine);
    Mail::fake();

    $this->artisan('gesimo:rappels-arrivee')->assertSuccessful();
    $this->artisan('gesimo:rappels-arrivee')->assertSuccessful();

    Mail::assertSent(MessageGesimo::class, 1);
    Mail::assertSent(MessageGesimo::class, fn ($m) => $m->hasTo($reservation->client->email) && str_contains($m->sujet, 'demain'));
});

it('une annulation prévient le client et le gérant', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    payerEtConfirmer($this, $faux, $reservation);
    Mail::fake();

    app(AnnulationService::class)->annuler($reservation->fresh(), $reservation->client);

    Mail::assertSent(MessageGesimo::class, 2);
    Mail::assertSent(MessageGesimo::class, fn ($m) => $m->hasTo($reservation->unite->residence->gerant->email));
});

it('le reversement effectué prévient le gérant', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer(['arrivee' => CarbonImmutable::today(), 'nuits' => 2]);
    payerEtConfirmer($this, $faux, $reservation);
    $gerant = $reservation->unite->residence->gerant;
    app(ArriveeService::class)->enregistrerArrivee($reservation->fresh(), $gerant);
    $reversement = Reversement::where('reservation_id', $reservation->id)->sole();
    Mail::fake();

    app(ReversementService::class)->enregistrer(creerUtilisateur(Role::Admin), $reversement, 'MM-1', now());

    Mail::assertSent(MessageGesimo::class, fn ($m) => $m->hasTo($gerant->email) && str_contains($m->sujet, 'Reversement effectué'));
});

it('seul le super-administrateur modifie les modèles ; un mot inconnu est refusé', function () {
    $this->actingAs(creerUtilisateur(Role::Admin))->get(route('admin.messages'))->assertForbidden();
    $this->actingAs(creerUtilisateur(Role::Client))->get(route('admin.messages'))->assertForbidden();

    Livewire::actingAs(creerUtilisateur(Role::SuperAdmin))->test(ModelesEmails::class)
        ->call('modifier', 'rappel_arrivee')
        ->set('corps', 'Bonjour {nom}, venez à {inconnu}.')->call('enregistrer')->assertSee('n\'existe pas')
        ->set('corps', 'Bonjour {nom}, à demain à {logement} !')->call('enregistrer')->assertSee('Modèle enregistré.');
    expect(ModelesMessages::obtenir('rappel_arrivee')['corps'])->toBe('Bonjour {nom}, à demain à {logement} !');
});

it('aucun secret dans les messages ni dans leur journal d\'envoi', function () {
    $faux = fauxGeniusPay();
    payerEtConfirmer($this, $faux, reservationAPayer());
    $tout = json_encode(Notification::all()->toArray());
    expect($tout)->not->toContain('whsec_')->and($tout)->not->toContain('sk_');
});
