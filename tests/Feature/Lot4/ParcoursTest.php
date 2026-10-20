<?php

use App\Noyau\Enums\Role;
use App\Paiement\Livewire\ArriveesDuJour;
use App\Paiement\Livewire\Confirmation;
use App\Paiement\Livewire\PagePaiement;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Livewire\Admin\ValiderResidence;
use App\Sejours\Livewire\DonnerAvis;
use App\Sejours\Livewire\EditerResidence;
use App\Sejours\Livewire\FicheLogement;
use App\Sejours\Livewire\MesReservations;
use App\Sejours\Livewire\Recapitulatif;
use App\Sejours\Livewire\Recherche;
use App\Sejours\Models\Checkin;
use App\Sejours\Models\Residence;
use App\Sejours\Services\Disponibilites;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    configurerGeniusPay();
    Mail::fake();
});

it('parcours client complet : recherche, réservation, paiement, confirmation, check-in, check-out, avis', function () {
    $faux = fauxGeniusPay();
    $unite = creerLogement(15000, 15000);
    $gerant = $unite->residence->gerant;
    $client = creerUtilisateur(Role::Client);
    $aujourdhui = CarbonImmutable::today();
    $depart = $aujourdhui->addDays(2);

    // 1. Recherche puis fiche : le logement est libre, le prix total est annoncé.
    Livewire::test(Recherche::class)->set('arrivee', $aujourdhui->toDateString())->set('depart', $depart->toDateString())->set('personnes', 2)->call('chercher')->assertSee($unite->nom)->assertSee('30');
    Livewire::test(FicheLogement::class, ['unite' => $unite])->set('arrivee', $aujourdhui->toDateString())->set('depart', $depart->toDateString())->assertSee('Pas encore d\'avis', false);

    // 2. Réservation : dates gardées, on est envoyé vers le paiement.
    Livewire::actingAs($client)->test(Recapitulatif::class, ['unite' => $unite])
        ->set('arrivee', $aujourdhui->toDateString())->set('depart', $depart->toDateString())->set('personnes', 2)->call('garder')->assertRedirect();
    $reservation = $client->reservations()->firstOrFail();
    expect($reservation->statut)->toBe(StatutReservation::EnAttente);

    // 3. Paiement : l'écran envoie vers GeniusPay ; la réservation n'est confirmée que par le message signé vérifié.
    Livewire::actingAs($client)->test(PagePaiement::class, ['reservation' => $reservation->id])->call('payer')->assertRedirect();
    $paiement = $reservation->paiements()->firstOrFail();
    expect($reservation->fresh()->statut)->toBe(StatutReservation::EnAttente);
    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'completed', 'amount' => $paiement->montant];
    recevoirWebhook($this, 'payment.success', $paiement->reference_geniuspay, $paiement->montant)->assertOk();

    // 4. Confirmation visible, réservation listée.
    expect($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee);
    Livewire::actingAs($client)->test(Confirmation::class, ['reservation' => $reservation->id])->assertSee('confirmée');
    Livewire::actingAs($client)->test(MesReservations::class)->assertSee($unite->nom);

    // 5. Le gérant enregistre l'arrivée (pièce d'identité obligatoire), puis le départ.
    Livewire::actingAs($gerant)->test(ArriveesDuJour::class)
        ->call('arrivee', $reservation->id)->assertSee('numéro de la pièce')
        ->set("pieces.{$reservation->id}.type", 'cni')->set("pieces.{$reservation->id}.numero", 'CI0012345')->call('arrivee', $reservation->id)->assertSee('Arrivée enregistrée')
        ->call('depart', $reservation->id)->assertSee('Départ enregistré');
    expect($reservation->fresh()->statut)->toBe(StatutReservation::Terminee);
    $checkin = Checkin::firstOrFail();
    expect($checkin->piece_numero)->toBe('CI0012345')
        ->and(DB::table('checkin')->value('piece_numero'))->not->toContain('CI0012345'); // chiffré en base

    // 6. Avis du client, visible ensuite sur la fiche.
    Livewire::actingAs($client)->test(DonnerAvis::class, ['reservation' => $reservation->id])->call('choisir', 5)->set('commentaire', 'Parfait')->call('publier')->assertSee('publié');
    Livewire::test(FicheLogement::class, ['unite' => $unite])->assertSee('Parfait');
});

it('parcours gérant complet : création de la résidence, validation admin, réservation reçue, check-in', function () {
    Storage::fake('public');
    Storage::fake('local');
    $faux = fauxGeniusPay();
    $gerant = creerUtilisateur(Role::Gerant);
    $admin = creerUtilisateur(Role::Admin);

    $page = Livewire::actingAs($gerant)->test(EditerResidence::class)
        ->set('nom', 'Villa Soleil')->set('commune', 'Marcory')->set('reglement', 'Pas de fêtes.')->call('enregistrerResidence');
    $residence = Residence::where('nom', 'Villa Soleil')->firstOrFail();
    $page = Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->call('nouveauLogement')->set('uNom', 'Studio')->set('uCapacite', '2')->set('uPrix', '20000')
        ->set('nouvellesPhotos', [UploadedFile::fake()->image('a.jpg')])->call('enregistrerLogement')
        ->set('pieceGerant', UploadedFile::fake()->create('cni.pdf', 200, 'application/pdf'))->set('justificatif', UploadedFile::fake()->create('titre.pdf', 200, 'application/pdf'))
        ->call('envoyerPieces')->call('soumettre')->assertSee('Envoyée pour vérification');

    // Invisible avant la validation de l'admin.
    expect(app(Disponibilites::class)->rechercher(CarbonImmutable::today(), CarbonImmutable::today()->addDay()))->toBeEmpty();
    Livewire::actingAs($admin)->test(ValiderResidence::class, ['residence' => $residence])->set('badge', true)->call('valider')->assertSee('mise en ligne');

    // Un client réserve et paie : le gérant reçoit la réservation.
    $unite = $residence->unites()->firstOrFail();
    $reservation = reservationAPayer(['unite' => $unite, 'arrivee' => CarbonImmutable::today(), 'nuits' => 1]);
    payerEtConfirmer($this, $faux, $reservation);

    Livewire::actingAs($gerant)->test(ArriveesDuJour::class)->assertSee($reservation->client->nom)
        ->set("pieces.{$reservation->id}.numero", 'PA998877')->call('arrivee', $reservation->id)->assertSee('Arrivée enregistrée');
    expect($reservation->fresh()->statut)->toBe(StatutReservation::ArriveeEnregistree);
});
