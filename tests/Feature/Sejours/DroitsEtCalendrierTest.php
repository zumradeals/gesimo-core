<?php

use App\Noyau\Enums\Role;
use App\Sejours\Livewire\CalendrierGerant;
use App\Sejours\Livewire\MesReservations;
use App\Sejours\Livewire\Recapitulatif;
use App\Sejours\Livewire\ReservationTelephone;
use App\Sejours\Models\BlocageCalendrier;
use App\Sejours\Models\MembreResidence;
use App\Sejours\Models\Reservation;
use Livewire\Livewire;

it('un client ne voit que ses réservations et ne peut pas toucher à celles d\'un autre', function () {
    $moi = creerUtilisateur(Role::Client);
    $autre = creerUtilisateur(Role::Client);
    $vendredi = prochainVendredi();
    $maChambre = creerLogement();
    $maChambre->update(['nom' => 'Ma chambre à moi']);
    $chambreVoisin = creerLogement();
    $chambreVoisin->update(['nom' => 'Chambre du voisin']);
    insererReservationSql($maChambre, $vendredi->toDateString(), $vendredi->addDay()->toDateString(), ['client_id' => $moi->id]);
    $laSienne = insererReservationSql($chambreVoisin, $vendredi->toDateString(), $vendredi->addDay()->toDateString(), ['client_id' => $autre->id, 'statut' => 'en_attente', 'expire_le' => now()->addHour()]);

    Livewire::actingAs($moi)->test(MesReservations::class)->assertSee('Ma chambre à moi')->assertDontSee('Chambre du voisin');
    Livewire::actingAs($moi)->test(MesReservations::class)->call('annuler', $laSienne)->assertNotFound(); // introuvable : on ne révèle même pas son existence
    expect(Reservation::find($laSienne)->statut->value)->toBe('en_attente');
});

it('un client affiche et annule sa réservation depuis « Mes réservations »', function () {
    $client = creerUtilisateur(Role::Client);
    $unite = creerLogement();
    $vendredi = prochainVendredi();

    Livewire::actingAs($client)->withQueryParams(['arrivee' => $vendredi->toDateString(), 'depart' => $vendredi->addDays(3)->toDateString(), 'personnes' => 2])
        ->test(Recapitulatif::class, ['unite' => $unite])->call('garder')->assertRedirect(route('mes-reservations'));

    $reservation = Reservation::firstOrFail();
    expect($reservation->client_id)->toBe($client->id);

    Livewire::actingAs($client)->test(MesReservations::class)->assertSee('En attente')->assertSee('Dates gardées encore')
        ->call('annuler', $reservation->id)->assertSee('Annulée');
    expect($reservation->fresh()->statut->value)->toBe('annulee');
});

it('le récapitulatif affiche une erreur claire si quelqu\'un a pris les dates entre-temps', function () {
    $client = creerUtilisateur(Role::Client);
    $unite = creerLogement();
    $vendredi = prochainVendredi();
    $composant = Livewire::actingAs($client)->withQueryParams(['arrivee' => $vendredi->toDateString(), 'depart' => $vendredi->addDays(3)->toDateString(), 'personnes' => 2])
        ->test(Recapitulatif::class, ['unite' => $unite]);

    insererReservationSql($unite, $vendredi->addDay()->toDateString(), $vendredi->addDays(2)->toDateString()); // un autre client réserve entre-temps

    $composant->call('garder')->assertSee('Ce logement n\'est pas libre du');
    expect(Reservation::where('client_id', $client->id)->count())->toBe(0);
});

it('un gérant ne voit dans le calendrier que ses résidences, même avec une adresse trafiquée', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $voisin = creerUtilisateur(Role::Gerant);
    $miennes = creerLogement(15000, 20000, ['gerant_id' => $gerant->id, 'nom' => 'Chez moi']);
    $siennes = creerLogement(15000, 20000, ['gerant_id' => $voisin->id, 'nom' => 'Chez le voisin']);
    $vendredi = prochainVendredi();
    insererReservationSql($siennes, $vendredi->toDateString(), $vendredi->addDay()->toDateString(), ['statut' => 'hors_plateforme', 'origine' => 'hors_plateforme', 'client_id' => null, 'nom_client' => 'Secret du voisin', 'montant_total' => 0, 'acompte' => 0, 'solde' => 0]);

    Livewire::actingAs($gerant)->withQueryParams(['residenceId' => $siennes->residence_id, 'uniteId' => $siennes->id, 'mois' => $vendredi->format('Y-m')])
        ->test(CalendrierGerant::class)
        ->assertSee('Chez moi')->assertDontSee('Chez le voisin')->assertDontSee('Secret du voisin');
    expect($miennes->residence->gerant_id)->toBe($gerant->id);
});

it('le gérant bloque des dates depuis le calendrier et voit un message clair en cas de conflit', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $unite = creerLogement(15000, 20000, ['gerant_id' => $gerant->id]);
    $vendredi = prochainVendredi();
    $page = Livewire::actingAs($gerant)->withQueryParams(['mois' => $vendredi->format('Y-m')])->test(CalendrierGerant::class);

    $page->set('debut', $vendredi->toDateString())->set('fin', $vendredi->addDay()->toDateString())->set('motif', 'Travaux')->call('bloquer')
        ->assertSee('Dates bloquées.')->assertSee('Travaux');
    expect(BlocageCalendrier::where('unite_id', $unite->id)->count())->toBe(1);

    $page->set('debut', $vendredi->addDay()->toDateString())->set('fin', $vendredi->addDays(3)->toDateString())->call('bloquer')
        ->assertSee('Impossible : ces dates sont déjà prises');
    expect(BlocageCalendrier::count())->toBe(1);

    $page->call('debloquer', BlocageCalendrier::first()->id)->assertSee('Blocage retiré.');
    expect(BlocageCalendrier::count())->toBe(0);
});

it('la réservation par téléphone apparaît dans le calendrier et bloque les dates', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $unite = creerLogement(15000, 20000, ['gerant_id' => $gerant->id]);
    $vendredi = prochainVendredi();

    Livewire::actingAs($gerant)->withQueryParams(['unite' => $unite->id])->test(ReservationTelephone::class)
        ->set('arrivee', $vendredi->toDateString())->set('depart', $vendredi->addDays(2)->toDateString())
        ->set('nomClient', 'M. Traoré')->set('telephoneClient', '05 11 22 33 44')
        ->call('enregistrer')->assertRedirect();

    $reservation = Reservation::firstOrFail();
    expect($reservation->origine)->toBe('hors_plateforme')->and($reservation->telephone_client)->toBe('0511223344')->and($reservation->montant_total)->toBe(0);

    Livewire::actingAs($gerant)->withQueryParams(['mois' => $vendredi->format('Y-m')])->test(CalendrierGerant::class)->assertSee('M. Traoré')->assertSee('Tél.');

    Livewire::actingAs($gerant)->withQueryParams(['unite' => $unite->id])->test(ReservationTelephone::class)
        ->set('arrivee', $vendredi->addDay()->toDateString())->set('depart', $vendredi->addDays(3)->toDateString())->set('nomClient', 'Autre')
        ->call('enregistrer')->assertSee('Ce logement n\'est pas libre du');

    Livewire::actingAs($gerant)->withQueryParams(['unite' => $unite->id])->test(ReservationTelephone::class)
        ->set('arrivee', $vendredi->addDays(10)->toDateString())->set('depart', $vendredi->addDays(11)->toDateString())->set('nomClient', '')
        ->call('enregistrer')->assertSee('Le nom du client est obligatoire.');
});

it('le personnel n\'a que les droits donnés par le gérant', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $unite = creerLogement(15000, 20000, ['gerant_id' => $gerant->id, 'nom' => 'Chambre du gérant']);
    $vendredi = prochainVendredi();

    $calendrier = creerUtilisateur(Role::Personnel);
    MembreResidence::create(['residence_id' => $unite->residence_id, 'utilisateur_id' => $calendrier->id, 'peut_gerer_calendrier' => true]);
    $arrivees = creerUtilisateur(Role::Personnel);
    MembreResidence::create(['residence_id' => $unite->residence_id, 'utilisateur_id' => $arrivees->id, 'peut_gerer_checkin_checkout' => true]);
    $etranger = creerUtilisateur(Role::Personnel);

    // Avec le droit « calendrier » : il voit la résidence et bloque des dates.
    $page = Livewire::actingAs($calendrier)->withQueryParams(['mois' => $vendredi->format('Y-m')])->test(CalendrierGerant::class);
    $page->assertSee('Chambre du gérant')->set('debut', $vendredi->toDateString())->set('fin', $vendredi->toDateString())->call('bloquer')->assertSee('Dates bloquées.');

    // Sans ce droit (seulement arrivées/départs), ou sans rattachement : rien à voir, aucun blocage possible.
    foreach ([$arrivees, $etranger] as $personne) {
        Livewire::actingAs($personne)->test(CalendrierGerant::class)->assertDontSee('Chambre du gérant')->assertSee('Aucune résidence à afficher.');
        Livewire::actingAs($personne)->test(CalendrierGerant::class)->set('debut', $vendredi->addDays(5)->toDateString())->set('fin', $vendredi->addDays(5)->toDateString())->call('bloquer')->assertForbidden();
    }
    expect(BlocageCalendrier::count())->toBe(1);

    // Jamais de saisie par téléphone, de gestion des résidences ni de personnel pour le personnel.
    foreach (['gerant.telephone', 'gerant.residences', 'gerant.personnel', 'admin.residences', 'mes-reservations'] as $route) {
        $this->actingAs($calendrier)->get(route($route))->assertForbidden();
    }
    $this->actingAs($calendrier)->get(route('gerant.calendrier'))->assertOk();
});

it('le personnel ne peut pas retirer un blocage ni annuler une réservation de la résidence d\'un autre', function () {
    $unite = creerLogement();
    $autre = creerLogement();
    $personnel = creerUtilisateur(Role::Personnel);
    MembreResidence::create(['residence_id' => $unite->residence_id, 'utilisateur_id' => $personnel->id, 'peut_gerer_calendrier' => true]);
    $blocage = BlocageCalendrier::create(['unite_id' => $autre->id, 'date_debut' => prochainVendredi(), 'date_fin' => prochainVendredi()->addDay()]);

    Livewire::actingAs($personnel)->test(CalendrierGerant::class)->call('debloquer', $blocage->id)->assertNotFound();
    expect(BlocageCalendrier::count())->toBe(1);
});

it('chaque rôle voit son propre menu : barre d\'onglets en bas sur téléphone, barre du haut sur ordinateur', function () {
    $this->get('/')->assertOk()->assertSee('Se connecter')->assertSee('aria-label="Menu principal"', false)->assertDontSee('Mes réservations');
    $this->actingAs(creerUtilisateur(Role::Client))->get('/')->assertSee('Mes réservations')->assertSee('Chercher')->assertDontSee('Calendrier');
    $this->actingAs(creerUtilisateur(Role::Gerant))->get('/gerant/residences')->assertSee('Calendrier')->assertSee('Résidences')->assertSee('Arrivées')->assertSee('Argent')->assertDontSee('Mes réservations');
    $this->actingAs(creerUtilisateur(Role::Admin))->get('/admin/residences')->assertSee('À vérifier')->assertSee('Paiements')->assertSee('Reversements')->assertSee('Contrôle');
});

it('toutes les pages s\'affichent sans erreur pour la bonne personne', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $unite = creerLogement(15000, 20000, ['gerant_id' => $gerant->id]);
    $client = creerUtilisateur(Role::Client);
    $vendredi = prochainVendredi();
    $q = ['arrivee' => $vendredi->toDateString(), 'depart' => $vendredi->addDays(2)->toDateString(), 'personnes' => 2];

    foreach (['accueil', 'recherche', 'connexion', 'inscription'] as $route) {
        $this->get(route($route))->assertOk();
    }
    $this->get(route('logement', ['unite' => $unite->id] + $q))->assertOk()->assertSee('Réserver');
    foreach (['mes-reservations', 'compte'] as $route) {
        $this->actingAs($client)->get(route($route))->assertOk();
    }
    $this->actingAs($client)->get(route('reserver', ['unite' => $unite->id] + $q))->assertOk()->assertSee('Garder ces dates');
    foreach (['gerant.residences', 'gerant.residence.nouvelle', 'gerant.calendrier', 'gerant.telephone', 'gerant.personnel'] as $route) {
        $this->actingAs($gerant)->get(route($route))->assertOk();
    }
    $this->actingAs($gerant)->get(route('gerant.residence', $unite->residence))->assertOk();
});

it('un visiteur est invité à se connecter avant de réserver', function () {
    $unite = creerLogement();

    $this->get(route('reserver', $unite))->assertRedirect(route('connexion'));
    $this->get(route('mes-reservations'))->assertRedirect(route('connexion'));
    $this->get(route('gerant.calendrier'))->assertRedirect(route('connexion'));
});
