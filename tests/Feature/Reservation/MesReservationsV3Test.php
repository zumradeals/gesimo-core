<?php

use App\Noyau\Enums\Role;
use App\Noyau\Models\JournalAudit;
use App\Paiement\Services\AnnulationService;
use App\Paiement\Services\ArriveeService;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Enums\TypePiece;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Livewire\DetailReservation;
use App\Sejours\Livewire\EditerResidence;
use App\Sejours\Livewire\FicheLogement;
use App\Sejours\Livewire\MesReservations;
use App\Sejours\Models\Avis;
use App\Sejours\Services\PiecesVoyageur;
use App\Sejours\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    configurerGeniusPay();
});

function resaMr($test, array $voyageur = [], ?CarbonImmutable $arrivee = null, $client = null, ?object $faux = null, bool $payee = true, array $residence = [])
{
    $faux ??= fauxGeniusPay();
    $client ??= creerUtilisateur(Role::Client);
    $unite = creerLogement(15000, 20000, $residence);
    $arrivee ??= prochainVendredi();
    $r = app(ReservationService::class)->garderDates($client, $unite, $arrivee, $arrivee->addDays(2), 2, $voyageur + ['nom' => 'Awa Koné', 'telephone' => '0700000000', 'motif' => 'vacances', 'accord_le' => now()]);
    if ($payee) {
        payerEtConfirmer($test, $faux, $r);
    }

    return $r->fresh();
}

function pieceMr($client): void
{
    PiecesVoyageur::enregistrer($client, TypePiece::Cni, UploadedFile::fake()->image('cni.jpg'));
}

// ====================================================================================================
// Liste
// ====================================================================================================

it('la liste met le paiement à finir en premier, avec un seul bouton principal et le temps restant', function () {
    $client = creerUtilisateur(Role::Client);
    $r = resaMr($this, client: $client, payee: false);

    Livewire::actingAs($client)->test(MesReservations::class)
        ->assertSee('À faire en premier')->assertSee('Payez pour garder vos dates')->assertSee('Dates gardées encore')
        ->assertSee('En attente de paiement')->assertSee(route('payer', $r), false)->assertSee('Annuler cette réservation');
});

it('la liste propose d\'ajouter la pièce d\'identité, avec le compte à rebours, puis la reconnaît', function () {
    $client = creerUtilisateur(Role::Client);
    $r = resaMr($this, client: $client, arrivee: CarbonImmutable::today()->addDays(3));

    $page = Livewire::actingAs($client)->test(MesReservations::class)
        ->assertSee('Ajoutez votre pièce d\'identité', false)->assertSee('Arrivée dans 3 jours')->assertSee('Pièce d\'identité à ajouter avant votre arrivée', false)
        ->assertSee(route('reservation.detail', $r).'#piece-identite', false)
        ->assertDontSee('Annuler')->assertDontSee('Reçu'); // un seul bouton principal : annuler et reçu sont dans le détail

    pieceMr($client);
    Livewire::actingAs($client)->test(MesReservations::class)->assertDontSee('À faire en premier')->assertSee('Pièce d\'identité fournie', false)->assertSee('Voir le détail');
});

it('la liste annonce « demain » et « aujourd\'hui », et « séjour en cours » après l\'arrivée', function () {
    $faux = fauxGeniusPay();
    $client = creerUtilisateur(Role::Client);
    resaMr($this, client: $client, arrivee: CarbonImmutable::today()->addDay(), faux: $faux);
    Livewire::actingAs($client)->test(MesReservations::class)->assertSee('Arrivée demain');

    $presente = resaMr($this, client: $client, arrivee: CarbonImmutable::today(), faux: $faux);
    Livewire::actingAs($client)->test(MesReservations::class)->assertSee('Arrivée aujourd\'hui');
    app(ArriveeService::class)->enregistrerArrivee($presente, $presente->unite->residence->gerant, 'cni', 'CI0099887');
    Livewire::actingAs($client)->test(MesReservations::class)->assertSee('Séjour en cours');
});

it('les séjours passés proposent l\'avis, réserver à nouveau et le remboursement d\'une annulation', function () {
    $faux = fauxGeniusPay();
    $client = creerUtilisateur(Role::Client);
    $terminee = reservationTerminee($this, $faux);
    $client = $terminee->client;
    $annulee = resaMr($this, client: $client, faux: $faux);
    app(AnnulationService::class)->annuler($annulee->load('unite.residence'), $client);

    Livewire::actingAs($client)->test(MesReservations::class)->set('onglet', 'passees')
        ->assertSee('Votre avis compte')->assertSee('Donner mon avis')->assertSee('Réserver à nouveau')
        ->assertSee(route('logement', $terminee->unite), false)->assertSee('Remboursé :');

    Avis::create(['reservation_id' => $terminee->id, 'client_id' => $client->id, 'note' => 5, 'commentaire' => 'Parfait']);
    Livewire::actingAs($client)->test(MesReservations::class)->set('onglet', 'passees')->assertDontSee('Votre avis compte')->assertSee('Votre avis :');
});

it('la liste vide propose de chercher un séjour', function () {
    Livewire::actingAs(creerUtilisateur(Role::Client))->test(MesReservations::class)->assertSee('Aucune réservation à venir')->assertSee(route('recherche'), false)
        ->set('onglet', 'passees')->assertSee('Aucun séjour passé');
});

// ====================================================================================================
// Détail
// ====================================================================================================

it('le détail d\'une réservation confirmée rassemble ce qu\'il faut avant l\'arrivée', function () {
    $client = creerUtilisateur(Role::Client);
    $r = resaMr($this, ['motif' => 'affaires', 'heure_arrivee' => '18h_22h', 'message' => 'J\'arrive à 20 h'], CarbonImmutable::today()->addDays(10), $client, residence: ['reglement' => 'Pas de fête après 22 h']);
    $gerant = $r->unite->residence->gerant;

    $this->actingAs($client)->get(route('reservation.detail', $r))->assertOk()
        ->assertSee('Avant votre arrivée')->assertSee('Ajoutez votre pièce d\'identité')->assertSee('id="piece-identite"', false)
        ->assertSee('Voyage d\'affaires, travail')->assertSee('Entre 18 h et 22 h')->assertSee('J\'arrive à 20 h')
        ->assertSee('à partir de 14 h')->assertSee('avant 11 h')->assertSee('tel:'.$gerant->telephone, false)
        ->assertSee('Pas de fête après 22 h')->assertSee('Télécharger le reçu')->assertSee('Arrivée dans 10 jours');

    pieceMr($client);
    $this->actingAs($client)->get(route('reservation.detail', $r))->assertOk()->assertSee('Pièce d\'identité fournie')->assertDontSee('id="piece-identite"', false);
});

it('le détail annonce la date limite d\'annulation et le remboursement avant que le voyageur clique', function () {
    $r = resaMr($this, arrivee: prochainVendredi());
    $limite = prochainVendredi()->subDay()->translatedFormat('j M');

    $this->actingAs($r->client)->get(route('reservation.detail', $r))->assertOk()
        ->assertSee("Annulation gratuite jusqu'au <b>{$limite} à 14 h</b>", false)->assertSee('Si vous annulez maintenant, vous êtes remboursé de')
        ->assertSee(route('reservation.annuler', $r), false);
});

it('le détail d\'une réservation en attente montre le temps restant et cache les coordonnées du gérant', function () {
    $r = resaMr($this, payee: false, residence: ['adresse' => 'Rue des Jardins, immeuble Palmiers']);
    $gerant = $r->unite->residence->gerant;

    $this->actingAs($r->client)->get(route('reservation.detail', $r))->assertOk()
        ->assertSee('pour payer')->assertSee('Dates gardées pour vous')->assertSee(route('payer', $r), false)
        ->assertDontSee($gerant->telephone)->assertDontSee('Rue des Jardins')->assertDontSee('Avant votre arrivée');
});

it('personne d\'autre que le voyageur n\'ouvre le détail de sa réservation', function () {
    $r = resaMr($this);
    $this->get(route('reservation.detail', $r))->assertRedirect(route('connexion'));
    $this->actingAs(creerUtilisateur(Role::Client))->get(route('reservation.detail', $r))->assertNotFound();
});

it('le voyageur modifie l\'heure d\'arrivée et son message jusqu\'à la veille, et le gérant les voit', function () {
    $client = creerUtilisateur(Role::Client);
    $r = resaMr($this, ['heure_arrivee' => 'inconnue'], CarbonImmutable::today()->addDays(4), $client);

    $montant = $r->montant_total;
    Livewire::actingAs($client)->test(DetailReservation::class, ['reservation' => $r->id])
        ->assertSee('Modifier l\'heure d\'arrivée ou mon message', false)->assertSee('jusqu\'à la veille de votre arrivée', false)
        ->set('edition', true)->set('heureArrivee', '18h_22h')->set('message', '  Arrivée par avion  ')->call('enregistrerInformations')
        ->assertSet('edition', false)->assertSee('Vos informations sont enregistrées')->assertSee('Entre 18 h et 22 h')->assertSee('Arrivée par avion');

    $r = $r->fresh();
    expect($r->heure_arrivee_prevue)->toBe('18h_22h')->and($r->message_gerant)->toBe('Arrivée par avion')
        ->and($r->montant_total)->toBe($montant)->and($r->statut)->toBe(StatutReservation::Confirmee);
    expect(JournalAudit::where('action', 'reservation.infos_modifiees')->where('utilisateur_id', $client->id)->count())->toBe(1);
});

it('la modification est refusée le jour de l\'arrivée, pour une autre personne et avec des valeurs invalides', function () {
    $client = creerUtilisateur(Role::Client);
    $r = resaMr($this, arrivee: CarbonImmutable::today()->addDays(2), client: $client);
    $service = app(ReservationService::class);

    expect(fn () => $service->modifierInformations($r, creerUtilisateur(Role::Client), '18h_22h', null))->toThrow(AuthorizationException::class);
    expect(fn () => $service->modifierInformations($r, $client, 'minuit_pile', null))->toThrow(ReservationImpossible::class, 'liste');
    expect(fn () => $service->modifierInformations($r, $client, '18h_22h', str_repeat('a', 501)))->toThrow(ReservationImpossible::class, 'trop long');

    $this->travelTo(CarbonImmutable::today()->addDays(2)->setTime(8, 0)); // jour de l'arrivée : la veille est passée
    expect(ReservationService::informationsModifiables($r))->toBeFalse();
    expect(fn () => $service->modifierInformations($r, $client, '18h_22h', null))->toThrow(ReservationImpossible::class, 'veille');
    Livewire::actingAs($client)->test(DetailReservation::class, ['reservation' => $r->id])->assertDontSee('Modifier l\'heure d', false);
    $this->travelBack();
});

it('une réservation annulée ou prise par téléphone ne se modifie pas', function () {
    $client = creerUtilisateur(Role::Client);
    $r = resaMr($this, client: $client);
    app(AnnulationService::class)->annuler($r->load('unite.residence'), $client);
    expect(ReservationService::informationsModifiables($r->fresh()))->toBeFalse();
});

it('un séjour terminé propose l\'avis et de réserver à nouveau depuis le détail', function () {
    $terminee = reservationTerminee($this, fauxGeniusPay());
    $this->actingAs($terminee->client)->get(route('reservation.detail', $terminee))->assertOk()
        ->assertSee('Donner mon avis')->assertSee('Réserver à nouveau')->assertSee(route('logement', $terminee->unite), false);
});

// ====================================================================================================
// Adresse écrite
// ====================================================================================================

it('le gérant renseigne l\'adresse précise de sa résidence', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = dossierGerantComplet($gerant);

    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->assertSee('Adresse précise')->assertSee('jamais affichée publiquement')
        ->set('adresse', '  Rue des Jardins, immeuble Palmiers, porte 4 ')->call('allerEtape', 2)->assertHasNoErrors();
    expect($residence->fresh()->adresse)->toBe('Rue des Jardins, immeuble Palmiers, porte 4');

    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])->set('adresse', str_repeat('a', 256))->call('allerEtape', 2)->assertHasErrors(['adresse']);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])->set('adresse', '')->call('allerEtape', 2);
    expect($residence->fresh()->adresse)->toBeNull();
});

it('l\'adresse précise n\'est donnée qu\'après la confirmation et jamais sur les pages publiques', function () {
    $r = resaMr($this, residence: ['adresse' => 'Rue des Jardins, immeuble Palmiers']);
    $unite = $r->unite;

    // Pages publiques : jamais.
    $this->get(route('logement', $unite))->assertOk()->assertDontSee('Rue des Jardins');
    $this->get(route('recherche'))->assertOk()->assertDontSee('Rue des Jardins');
    Livewire::test(FicheLogement::class, ['unite' => $unite])->assertDontSee('Rue des Jardins');
    $this->get(route('hebergeur', $unite->residence->gerant->profilGerant->slug))->assertOk()->assertDontSee('Rue des Jardins');

    // Après confirmation : détail et page de confirmation.
    $this->actingAs($r->client)->get(route('reservation.detail', $r))->assertSee('Rue des Jardins')->assertSee('Adresse');
    $this->actingAs($r->client)->get(route('reservation.confirmation', $r))->assertSee('Rue des Jardins');
    // Un autre voyageur ne la voit pas.
    $this->actingAs(creerUtilisateur(Role::Client))->get(route('reservation.detail', $r))->assertNotFound();
});

it('sans adresse précise, le détail le dit simplement et garde la carte', function () {
    $r = resaMr($this, residence: ['latitude' => 5.35, 'longitude' => -4.0]);
    $this->actingAs($r->client)->get(route('reservation.detail', $r))->assertOk()
        ->assertSee('Le gérant vous précisera l\'adresse exacte', false)->assertSee('google.com/maps?q=5.35', false)->assertSee('Voir sur la carte');
});

// ====================================================================================================
// Habillage de l'espace client
// ====================================================================================================

it('l\'espace client prend la charte orange, pas les autres espaces', function () {
    $client = creerUtilisateur(Role::Client);
    $this->actingAs($client)->get(route('mes-reservations'))->assertOk()->assertSee('espace-client', false)->assertSee('mr-page', false);
    $this->actingAs($client)->get(route('profil'))->assertOk()->assertSee('espace-client', false);
    $this->actingAs(creerUtilisateur(Role::Gerant))->get(route('gerant.calendrier'))->assertOk()->assertDontSee('espace-client', false);
    $this->actingAs(creerUtilisateur(Role::Admin))->get(route('admin.tableau'))->assertOk()->assertDontSee('espace-client', false);
});
