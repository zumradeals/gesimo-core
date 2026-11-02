<?php

use App\Noyau\Enums\Role;
use App\Noyau\Livewire\Profil;
use App\Noyau\Mail\LienConnexion;
use App\Noyau\Mail\MessageGesimo;
use App\Noyau\Messagerie\CanalEmail;
use App\Noyau\Models\Notification;
use App\Paiement\Livewire\AnnulerReservation;
use App\Parametres\Services\Parametres;
use App\Sejours\Livewire\DonnerAvis;
use App\Sejours\Livewire\OuvrirLitige;
use App\Sejours\Models\Reservation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Lot 3 : fin du parcours voyageur (contrat docs/contrats/espace-voyageur-v3.md).

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    configurerGeniusPay();
});

/** Réservation payée et confirmée, sans pièce d'identité déposée. */
function resaLot3Confirmee($test): Reservation
{
    $r = reservationAPayer(['arrivee' => prochainVendredi()]);
    payerEtConfirmer($test, fauxGeniusPay(), $r);

    return $r->fresh();
}

it('Mon espace met la pièce d\'identité à fournir en premier, puis plus rien', function () {
    $r = resaLot3Confirmee($this);

    $page = $this->actingAs($r->client)->get(route('compte'))->assertOk();
    $page->assertSee('À faire en premier', false)->assertSee('Ajoutez votre pièce d\'identité', false)->assertSee('Votre prochain séjour', false);
    $page->assertDontSee('👋', false);
});

it('Mon espace propose de payer quand des dates sont gardées', function () {
    $r = reservationAPayer();

    $this->actingAs($r->client)->get(route('compte'))->assertOk()->assertSee('Payez pour garder vos dates', false)->assertSee(route('payer', $r), false);
});

it('Mon espace sans séjour invite à chercher un logement, sans bandeau', function () {
    $client = creerUtilisateur(Role::Client);

    $this->actingAs($client)->get(route('compte'))->assertOk()
        ->assertSee('pas encore de séjour à venir', false)->assertSee('Rien à faire pour le moment', false)->assertDontSee('À faire en premier', false);
});

it('Mon espace montre les raccourcis du gérant', function () {
    $gerant = creerUtilisateur(Role::Gerant);

    $this->actingAs($gerant)->get(route('compte'))->assertOk()
        ->assertSee('Mon personnel', false)->assertSee('Mises en avant', false)->assertSee('Aide du gérant', false)->assertDontSee('Votre prochain séjour', false);
});

it('Mon profil garde ses règles et ajoute « Afficher » aux mots de passe', function () {
    $client = creerUtilisateur(Role::Client);

    $this->actingAs($client)->get(route('profil'))->assertOk()
        ->assertSee('10 chiffres, sans espace ni indicatif.', false)->assertSee('Afficher', false)->assertSee('Qui peut voir ma pièce ?', false);

    Livewire::actingAs($client)->test(Profil::class)
        ->set('ancienMotDePasse', 'faux')->set('nouveauMotDePasse', 'nouveau-mot-de-passe')->call('changerMotDePasse')
        ->assertSee('L\'ancien mot de passe est incorrect.');
});

it('Annuler met le montant remboursé en évidence', function () {
    $r = resaLot3Confirmee($this);

    Livewire::actingAs($r->client)->test(AnnulerReservation::class, ['reservation' => $r->id])
        ->assertSee('Ce que vous récupérez', false)->assertSee('Remboursé :', false)->assertSee('définitive', false)->assertSee('Garder ma réservation', false);
});

it('l\'avis dit avant de publier qu\'il ne peut plus être modifié et nomme la note', function () {
    $r = reservationTerminee($this, fauxGeniusPay());

    Livewire::actingAs($r->client)->test(DonnerAvis::class, ['reservation' => $r->id])
        ->assertSee('il ne peut plus être modifié', false)
        ->call('choisir', 5)->assertSee('5 / 5 · Excellent', false)
        ->call('choisir', 1)->assertSee('1 / 5 · Décevant', false);
});

it('Signaler un problème explique les trois étapes et l\'absence de remboursement automatique', function () {
    $r = resaLot3Confirmee($this);

    Livewire::actingAs($r->client)->test(OuvrirLitige::class, ['reservation' => $r->id])
        ->assertSee('Et ensuite ?', false)->assertSee('Vous recevez une réponse sous 48 h.', false)->assertSee('Aucun remboursement n\'est automatique', false)
        ->set('motif', 'court')->call('envoyer')->assertSee('10 caractères au minimum', false);
});

it('le reçu est aux couleurs d\'Avelis et n\'affiche la ligne légale que si elle est renseignée', function () {
    $r = resaLot3Confirmee($this);
    $page = $this->actingAs($r->client)->get(route('reservation.recu', $r))->assertOk();
    $page->assertSee('Reçu de paiement', false)->assertSee('#ff6a00', false)->assertDontSee('RCCM', false);

    Parametres::definir('general.raison_sociale', 'SARL Exemple');
    Parametres::definir('general.rccm', 'CI-ABJ-2026-B-0001');
    $this->actingAs($r->client)->get(route('reservation.recu', $r))->assertOk()->assertSee('SARL Exemple · RCCM CI-ABJ-2026-B-0001', false);
});

it('les e-mails utilisent le gabarit orange avec logo, pied de page et sans ancien vert', function () {
    $html = (new MessageGesimo('Réservation confirmée : Appartement', "Bonjour Awa,\n\nVotre réservation est confirmée."))->render();

    expect($html)->toContain('#ff6a00')->toContain('#17243b')->toContain('Avelis Séjours')->toContain('Message automatique')->toContain('Bonjour Awa,')
        ->not->toContain('#0f766e')->not->toContain('Voir ma réservation');
});

it('le lien de connexion et l\'invitation passent par le même gabarit', function () {
    $lien = (new LienConnexion('Awa', 'https://exemple.test/lien', 15))->render();
    expect($lien)->toContain('#ff6a00')->toContain('Me connecter')->not->toContain('#0f766e');
});

it('les messages de confirmation et de rappel au voyageur ont un grand bouton', function () {
    Mail::fake();
    $r = resaLot3Confirmee($this);

    $notification = Notification::where('modele', 'confirmation')->where('destinataire_id', $r->client_id)->sole();
    (new CanalEmail)->envoyer($notification);

    Mail::assertSent(MessageGesimo::class, fn (MessageGesimo $m) => $m->bouton['texte'] === 'Voir ma réservation' && str_contains($m->bouton['lien'], '/reservation/'.$r->id));
});

it('le message d\'annulation envoyé au gérant n\'a pas de bouton', function () {
    Mail::fake();
    $r = resaLot3Confirmee($this);
    $notification = Notification::create(['destinataire_id' => $r->unite->residence->gerant_id, 'sujet' => 'x', 'contenu' => 'y', 'modele' => 'confirmation', 'reservation_id' => $r->id]);
    (new CanalEmail)->envoyer($notification);

    Mail::assertSent(MessageGesimo::class, fn (MessageGesimo $m) => $m->bouton === null);
});
