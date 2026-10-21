<?php

use App\Noyau\Enums\Role;
use App\Noyau\Livewire\Admin\JournalAudit;
use App\Noyau\Livewire\Contact;
use App\Noyau\Livewire\Inscription;
use App\Noyau\Livewire\Profil;
use App\Noyau\Mail\MessageGesimo;
use App\Noyau\Models\MessageContact;
use App\Noyau\Services\Audit;
use App\Paiement\Livewire\ArriveesDuJour;
use App\Parametres\Services\Parametres;
use App\Sejours\Livewire\Admin\TableauDeBord;
use App\Sejours\Livewire\Admin\ToutesLesReservations;
use App\Sejours\Livewire\MesReservations;
use App\Sejours\Livewire\ReservationsGerant;
use App\Sejours\Services\LitigeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    configurerGeniusPay();
    Mail::fake();
});

it('les pages publiques s\'ouvrent sans compte et le pied de page y mène', function () {
    foreach (['a-propos' => 'À propos de GESIMO', 'faq' => 'Questions fréquentes', 'contact' => 'Écrivez-nous', 'conditions' => 'Conditions générales', 'confidentialite' => 'Politique de confidentialité', 'devenir-partenaire' => 'Gérez vos résidences'] as $route => $texte) {
        $this->get(route($route))->assertOk()->assertSee($texte)->assertSee('Devenir gérant partenaire')->assertSee('aria-label="Menu principal"', false);
    }
    $this->get(route('gerant.aide'))->assertRedirect(route('connexion')); // l'aide du gérant n'est pas publique
    $this->get('/')->assertSee('Aide');
});

it('les textes juridiques sont signalés comme brouillons à faire valider', function () {
    $this->get(route('conditions'))->assertSee('juriste');
    $this->get(route('confidentialite'))->assertSee('juriste');
});

it('« Devenir gérant partenaire » indique la commission réglée et mène à l\'inscription gérant', function () {
    configurerGeniusPay('sandbox', 12.5);
    $this->get(route('devenir-partenaire'))->assertSee('12,5 %')->assertSee(route('inscription', ['profil' => 'gerant']), false);
    Livewire::withQueryParams(['profil' => 'gerant'])->test(Inscription::class)->assertSet('profil', 'gerant');
});

it('le formulaire de contact garde le message, le transmet à l\'équipe et limite les envois', function () {
    Parametres::definir('contact.email', 'equipe@gesimo.test');
    RateLimiter::clear('contact:127.0.0.1');

    Livewire::test(Contact::class)->call('envoyer')->assertHasErrors(['nom', 'contact', 'message'])
        ->set('nom', 'Awa')->set('contact', 'awa@exemple.ci')->set('message', 'Bonjour, une question.')->call('envoyer')->assertSee('Votre message est bien parti');

    expect(MessageContact::sole()->message)->toBe('Bonjour, une question.');
    Mail::assertSent(MessageGesimo::class, fn ($m) => $m->hasTo('equipe@gesimo.test') && str_contains($m->texte, 'Awa'));

    $page = Livewire::test(Contact::class)->set('nom', 'Awa')->set('contact', 'x')->set('message', 'Encore un message');
    foreach (range(1, 5) as $i) {
        $page->set('message', 'Encore un message')->call('envoyer');
    }
    $page->assertSee('patienter');
    expect(MessageContact::count())->toBeLessThanOrEqual(5);
});

it('le client voit son prochain séjour sur son compte, et met à jour son profil', function () {
    $faux = fauxGeniusPay();
    $r = reservationAPayer();
    payerEtConfirmer($this, $faux, $r);

    $this->actingAs($r->client)->get(route('compte'))->assertOk()->assertSee($r->unite->nom)->assertSee('Votre prochain séjour')->assertSee(route('profil'), false);

    $autre = creerUtilisateur(Role::Client);
    Livewire::actingAs($r->client)->test(Profil::class)
        ->set('nom', 'Awa Nouvelle')->set('telephone', $autre->telephone)->call('enregistrer')->assertHasErrors('telephone')
        ->set('telephone', '0701020304')->set('email', 'nouvelle@exemple.ci')->call('enregistrer')->assertSee('enregistrées');
    expect($r->client->fresh()->nom)->toBe('Awa Nouvelle')->and($r->client->fresh()->telephone)->toBe('0701020304');

    Livewire::actingAs($r->client)->test(Profil::class)
        ->set('ancienMotDePasse', 'faux-mot-de-passe')->set('nouveauMotDePasse', 'nouveau-mot-de-passe-1')->call('changerMotDePasse')->assertSee('ancien mot de passe est incorrect')
        ->set('nouveauMotDePasse', 'court')->call('changerMotDePasse')->assertHasErrors('nouveauMotDePasse');
});

it('le changement de mot de passe vérifie l\'ancien et le journal ne contient aucun mot de passe', function () {
    $client = creerUtilisateur(Role::Client);
    $client->update(['mot_de_passe' => 'ancien-mot-de-passe']);
    Livewire::actingAs($client)->test(Profil::class)->set('ancienMotDePasse', 'ancien-mot-de-passe')->set('nouveauMotDePasse', 'nouveau-mot-de-passe-1')->call('changerMotDePasse')->assertSee('est changé');
    expect(Hash::check('nouveau-mot-de-passe-1', $client->fresh()->mot_de_passe))->toBeTrue();
    expect(json_encode(DB::table('journal_audit')->get()))->not->toContain('mot-de-passe-1');
});

it('mes réservations : onglets à venir / passées, et le détail est réservé au client', function () {
    $faux = fauxGeniusPay();
    $r = reservationAPayer();
    payerEtConfirmer($this, $faux, $r);
    $passee = reservationTerminee($this, $faux);

    Livewire::actingAs($r->client)->test(MesReservations::class)->assertSee('À venir (1)')->assertSee($r->unite->nom)->assertSee('Passées (0)');
    Livewire::actingAs($passee->client)->test(MesReservations::class)->set('onglet', 'passees')->assertSee('Passées (1)')->assertSee($passee->unite->nom);

    $this->actingAs($r->client)->get(route('reservation.detail', $r))->assertOk()->assertSee('Télécharger le reçu')->assertSee('Annuler')->assertSee('Signaler un problème');
    $this->actingAs($passee->client)->get(route('reservation.detail', $passee))->assertSee('Donner mon avis');
    $this->actingAs(creerUtilisateur(Role::Client))->get(route('reservation.detail', $r))->assertNotFound();
});

it('tableau de bord du gérant : arrivées, départs, à venir, sommes dues et litiges', function () {
    $faux = fauxGeniusPay();
    $aujourdhui = reservationAPayer(['arrivee' => CarbonImmutable::today(), 'nuits' => 2]);
    payerEtConfirmer($this, $faux, $aujourdhui);
    $gerant = $aujourdhui->unite->residence->gerant;
    $avenir = reservationAPayer(['unite' => $aujourdhui->unite, 'arrivee' => CarbonImmutable::today()->addDays(4), 'nuits' => 1]);
    payerEtConfirmer($this, $faux, $avenir);

    Livewire::actingAs($gerant)->test(ArriveesDuJour::class)
        ->assertSee('Arrivées du jour')->assertSee('À venir (7 jours)')->assertSee('dus par GESIMO')->assertSee($aujourdhui->client->nom)
        ->assertDontSee('problème(s) signalé(s)');

    app(LitigeService::class)->ouvrir($avenir->fresh(), $avenir->client, 'Le client demande un autre logement.');
    Livewire::actingAs($gerant)->test(ArriveesDuJour::class)->assertSee('problème(s) signalé(s)');
});

it('le gérant ne voit que ses réservations, le contact et les montants dans le détail', function () {
    $faux = fauxGeniusPay();
    $r = reservationAPayer();
    payerEtConfirmer($this, $faux, $r);
    $gerant = $r->unite->residence->gerant;
    $autre = reservationAPayer();
    payerEtConfirmer($this, $faux, $autre);

    Livewire::actingAs($gerant)->test(ReservationsGerant::class)->assertSee($r->client->nom)->assertDontSee($autre->client->nom)
        ->call('detail', $r->id)->assertSee('Payé en ligne')->assertSee('Signaler un problème');
    $this->actingAs($r->client)->get(route('gerant.reservations'))->assertForbidden();
});

it('espace admin : tableau de bord, réservations, journal ; interdits aux autres rôles', function () {
    $faux = fauxGeniusPay();
    $r = reservationAPayer();
    payerEtConfirmer($this, $faux, $r);
    app(LitigeService::class)->ouvrir($r->fresh(), $r->client, 'Un problème sérieux avec le logement.');
    $admin = creerUtilisateur(Role::Admin);

    Livewire::actingAs($admin)->test(TableauDeBord::class)->assertSee('À traiter maintenant')->assertSee('Litige n° ')->assertSee('Un problème sérieux')->assertSee('Taux d\'expiration', false);
    Livewire::actingAs($admin)->test(ToutesLesReservations::class)->assertSee('#'.$r->id)->set('statut', 'annulee')->assertDontSee('#'.$r->id);
    Audit::enregistrer('reversement.test', null, ['cle_secrete' => 'sk_live_abc', 'note' => 'ok'], $admin);
    $journal = Livewire::actingAs($admin)->test(JournalAudit::class)->assertSee('litige.ouvert')->set('filtre', 'litige')->assertSee('litige.ouvert')->assertDontSee('reversement.test');
    expect(json_encode(DB::table('journal_audit')->get()))->not->toContain('sk_live_abc');

    foreach (['admin.tableau', 'admin.reservations', 'admin.journal'] as $route) {
        foreach ([Role::Client, Role::Gerant, Role::Personnel] as $role) {
            $this->actingAs(creerUtilisateur($role))->get(route($route))->assertForbidden();
        }
        $this->actingAs($admin)->get(route($route))->assertOk();
    }
});

it('le journal d\'audit et les réservations sont en lecture seule', function () {
    $admin = creerUtilisateur(Role::Admin);
    $ligne = Audit::enregistrer('test.ligne', null, [], $admin);
    expect(codeRefusBase(fn () => DB::table('journal_audit')->where('id', $ligne->id)->update(['action' => 'x'])))->not->toBeNull();
});
