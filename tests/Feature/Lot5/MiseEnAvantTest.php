<?php

use App\Noyau\Enums\Role;
use App\Paiement\Exceptions\PaiementImpossible;
use App\Paiement\Livewire\AcheterMiseEnAvant;
use App\Paiement\Livewire\Controle;
use App\Paiement\Livewire\MisesEnAvantAdmin;
use App\Paiement\Livewire\MisesEnAvantGerant;
use App\Paiement\Models\AlerteAdmin;
use App\Paiement\Models\MiseEnAvant;
use App\Paiement\Models\Paiement;
use App\Paiement\Services\MiseEnAvantService;
use App\Paiement\Services\PaiementService;
use App\Paiement\Services\Rapprochement;
use App\Parametres\Services\Parametres;
use App\Sejours\Livewire\FicheLogement;
use App\Sejours\Livewire\Recherche;
use App\Sejours\Services\Disponibilites;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    configurerGeniusPay();
    Parametres::definir('mise_en_avant.offres', json_encode([['nom' => 'Une semaine', 'jours' => 7, 'prix' => 10000], ['nom' => 'Un mois', 'jours' => 30, 'prix' => 30000]]));
});

/** Le gérant d'une résidence vérifiée achète une offre et la paie (faux GeniusPay + message signé). */
function acheterEtPayer($test, object $faux, $gerant, $residence, int $offre = 0): MiseEnAvant
{
    $mea = app(MiseEnAvantService::class)->creerAchat($gerant, $residence, $offre);
    $paiement = app(PaiementService::class)->demarrerMiseEnAvant($mea->load('residence'), $gerant);
    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'completed', 'amount' => $paiement->montant];
    recevoirWebhook($test, 'payment.success', $paiement->reference_geniuspay, $paiement->montant)->assertOk();

    return $mea->fresh();
}

it('le gérant paie une mise en avant : elle devient active pour la durée de l\'offre, et rien n\'est compté deux fois', function () {
    $faux = fauxGeniusPay();
    $unite = creerLogement();
    $residence = $unite->residence;
    $gerant = $residence->gerant;

    $mea = app(MiseEnAvantService::class)->creerAchat($gerant, $residence, 0);
    expect($mea->statut)->toBe('en_attente_paiement')->and($mea->prix)->toBe(10000)->and($mea->jours)->toBe(7);
    $paiement = app(PaiementService::class)->demarrerMiseEnAvant($mea->load('residence'), $gerant);
    expect($paiement->mise_en_avant_id)->toBe($mea->id)->and($paiement->reservation_id)->toBeNull()->and($paiement->montant)->toBe(10000);
    // Le navigateur n'est pas cru : sans message signé vérifié, rien n'est activé.
    expect($mea->fresh()->statut)->toBe('en_attente_paiement');

    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'completed', 'amount' => 10000];
    recevoirWebhook($this, 'payment.success', $paiement->reference_geniuspay, 10000)->assertOk();
    recevoirWebhook($this, 'payment.success', $paiement->reference_geniuspay, 10000)->assertOk(); // rejoué

    $mea = $mea->fresh();
    expect($mea->statut)->toBe('active')->and($mea->date_debut->toDateString())->toBe(today()->toDateString())->and($mea->date_fin->toDateString())->toBe(today()->addDays(6)->toDateString());
    // Revenu de GESIMO : une seule écriture, pas de commission, pas de part de gérant, pas d'encaissement de réservation.
    expect(DB::table('ecriture_comptable')->where('type', 'mise_en_avant')->count())->toBe(1)->and((int) DB::table('ecriture_comptable')->where('type', 'mise_en_avant')->sum('montant'))->toBe(10000)
        ->and(DB::table('ecriture_comptable')->whereIn('type', ['commission', 'part_gerant', 'encaissement'])->count())->toBe(0);
    expect(Rapprochement::class)->toBeString();
    $r = app(Rapprochement::class)->calculer();
    expect($r['anomalies'])->toBeEmpty()->and($r['totaux']['mises_en_avant'])->toBe(10000)->and($r['detenus'])->toBe(0);
    expect(codeRefusBase(fn () => DB::table('ecriture_comptable')->insert(['paiement_id' => $paiement->id, 'mise_en_avant_id' => $mea->id, 'type' => 'mise_en_avant', 'montant' => 10000])))->not->toBeNull(); // doublon refusé par la base
});

it('une résidence non vérifiée, l\'offre d\'un autre gérant ou une offre inconnue sont refusées', function () {
    $unite = creerLogement();
    $gerant = $unite->residence->gerant;
    $brouillon = creerResidence($gerant, ['statut_verification' => 'en_attente']);
    $service = app(MiseEnAvantService::class);

    expect(fn () => $service->creerAchat($gerant, $brouillon, 0))->toThrow(PaiementImpossible::class, 'vérifiée');
    expect(fn () => $service->creerAchat($gerant, $unite->residence, 9))->toThrow(PaiementImpossible::class, 'n\'existe plus');
    expect(fn () => $service->creerAchat(creerUtilisateur(Role::Gerant), $unite->residence, 0))->toThrow(AuthorizationException::class);
});

it('le prix et la durée sont ceux du jour de l\'achat, même si les offres changent ensuite', function () {
    $faux = fauxGeniusPay();
    $unite = creerLogement();
    $mea = app(MiseEnAvantService::class)->creerAchat($unite->residence->gerant, $unite->residence, 1);
    Parametres::definir('mise_en_avant.offres', json_encode([['nom' => 'Un mois', 'jours' => 30, 'prix' => 99000]]));

    $paiement = app(PaiementService::class)->demarrerMiseEnAvant($mea->load('residence'), $unite->residence->gerant);
    expect($paiement->montant)->toBe(30000)->and($mea->fresh()->prix)->toBe(30000);
});

it('une nouvelle mise en avant commence juste après celle qui est en cours', function () {
    $faux = fauxGeniusPay();
    $unite = creerLogement();
    $g = $unite->residence->gerant;
    $premiere = acheterEtPayer($this, $faux, $g, $unite->residence, 0);
    $seconde = acheterEtPayer($this, $faux, $g, $unite->residence, 1);

    expect($seconde->date_debut->toDateString())->toBe($premiere->date_fin->addDay()->toDateString())->and($seconde->etat())->toBe('Programmée');
    expect(MiseEnAvant::enCours()->pluck('id')->all())->toBe([$premiere->id]);
});

it('l\'annonce passe en tête de recherche avec « Sponsorisé », puis redevient normale automatiquement à la fin', function () {
    $faux = fauxGeniusPay();
    $moinsCher = creerLogement(10000, 10000, ['nom' => 'Résidence A', 'commune' => 'Cocody']);
    $plusCher = creerLogement(30000, 30000, ['nom' => 'Résidence B', 'commune' => 'Cocody']);
    $arrivee = CarbonImmutable::today()->addDays(20);
    $depart = $arrivee->addDays(2);
    $ordre = fn () => app(Disponibilites::class)->rechercher($arrivee, $depart)->map(fn ($l) => [$l['unite']->id, $l['sponsorise']])->all();

    // Sans mise en avant : du moins cher au plus cher.
    expect($ordre())->toBe([[$moinsCher->id, false], [$plusCher->id, false]]);

    acheterEtPayer($this, $faux, $plusCher->residence->gerant, $plusCher->residence, 0);
    expect($ordre())->toBe([[$plusCher->id, true], [$moinsCher->id, false]]);
    Livewire::test(Recherche::class)->set('arrivee', $arrivee->toDateString())->set('depart', $depart->toDateString())->call('chercher')->assertSee('Sponsorisé');
    Livewire::test(FicheLogement::class, ['unite' => $plusCher])->assertSee('Sponsorisé');
    Livewire::test(FicheLogement::class, ['unite' => $moinsCher])->assertDontSee('Sponsorisé');

    // 7 jours plus tard : plus mise en avant (la visibilité suit les dates), puis rangée « Terminée » par la tâche planifiée.
    $this->travelTo(now()->addDays(8));
    expect($ordre())->toBe([[$moinsCher->id, false], [$plusCher->id, false]]);
    Livewire::test(FicheLogement::class, ['unite' => $plusCher])->assertDontSee('Sponsorisé');
    expect(app(MiseEnAvantService::class)->ranger()['terminees'])->toBe(1)->and(MiseEnAvant::sole()->statut)->toBe('terminee');
});

it('la dernière journée de la période est encore mise en avant, le lendemain non', function () {
    $faux = fauxGeniusPay();
    $unite = creerLogement();
    $mea = acheterEtPayer($this, $faux, $unite->residence->gerant, $unite->residence, 0);

    $this->travelTo($mea->date_fin->setTime(23, 30));
    expect(MiseEnAvantService::residencesEnAvant())->toBe([$unite->residence_id]);
    $this->travelTo($mea->date_fin->addDay()->setTime(0, 5));
    expect(MiseEnAvantService::residencesEnAvant())->toBe([]);
});

it('l\'admin suspend une mise en avant : l\'annonce redevient normale tout de suite ; il peut la reprendre', function () {
    $faux = fauxGeniusPay();
    $unite = creerLogement();
    $mea = acheterEtPayer($this, $faux, $unite->residence->gerant, $unite->residence, 0);
    $admin = creerUtilisateur(Role::Admin);

    expect(MiseEnAvantService::residencesEnAvant())->toBe([$unite->residence_id]);
    Livewire::actingAs($admin)->test(MisesEnAvantAdmin::class)->assertSee($unite->residence->nom)
        ->call('demanderSuspension', $mea->id)->call('suspendre')->assertSee('Indiquez pourquoi')
        ->set('motif', 'Annonce trompeuse signalée')->call('suspendre')->assertSee('redevient normale');
    expect($mea->fresh()->statut)->toBe('suspendue')->and(MiseEnAvantService::residencesEnAvant())->toBe([])->and($mea->fresh()->suspendue_par)->toBe($admin->id);
    expect(DB::table('journal_audit')->where('action', 'mise_en_avant.suspendue')->exists())->toBeTrue();

    Livewire::actingAs($unite->residence->gerant)->test(MisesEnAvantGerant::class)->assertSee('Suspendue par GESIMO')->assertSee('n\'est plus mise en avant', false);

    Livewire::actingAs($admin)->test(MisesEnAvantAdmin::class)->call('reprendre', $mea->id)->assertSee('reprise');
    expect($mea->fresh()->statut)->toBe('active')->and(MiseEnAvantService::residencesEnAvant())->toBe([$unite->residence_id]);
    // Reprise : pas de prolongation.
    expect($mea->fresh()->date_fin->equalTo($mea->date_fin))->toBeTrue();
});

it('seul un administrateur peut suspendre ou reprendre', function () {
    $faux = fauxGeniusPay();
    $unite = creerLogement();
    $mea = acheterEtPayer($this, $faux, $unite->residence->gerant, $unite->residence, 0);

    foreach ([Role::Client, Role::Gerant, Role::Personnel] as $role) {
        $this->actingAs(creerUtilisateur($role))->get(route('admin.mises-en-avant'))->assertForbidden();
        expect(fn () => app(MiseEnAvantService::class)->suspendre($mea, creerUtilisateur($role), 'Un motif valable'))->toThrow(AuthorizationException::class);
    }
    expect($mea->fresh()->statut)->toBe('active');
});

it('un paiement qui échoue n\'active rien ; un achat jamais payé est abandonné après deux jours', function () {
    $faux = fauxGeniusPay();
    $unite = creerLogement();
    $g = $unite->residence->gerant;
    $mea = app(MiseEnAvantService::class)->creerAchat($g, $unite->residence, 0);
    $paiement = app(PaiementService::class)->demarrerMiseEnAvant($mea->load('residence'), $g);
    recevoirWebhook($this, 'payment.failed', $paiement->reference_geniuspay, 10000)->assertOk();
    expect($paiement->fresh()->statut)->toBe('echoue')->and($mea->fresh()->statut)->toBe('en_attente_paiement')->and(MiseEnAvantService::residencesEnAvant())->toBe([]);

    $this->travelTo(now()->addDays(3));
    expect(app(MiseEnAvantService::class)->ranger()['abandonnees'])->toBe(1)->and($mea->fresh()->statut)->toBe('annulee');
});

it('un second paiement pour la même mise en avant alerte l\'admin, sans rien activer de plus', function () {
    $faux = fauxGeniusPay();
    $unite = creerLogement();
    $g = $unite->residence->gerant;
    $mea = acheterEtPayer($this, $faux, $g, $unite->residence, 0);

    $deuxieme = Paiement::create(['mise_en_avant_id' => $mea->id, 'reference_geniuspay' => 'SANDBOX-DOUBLE', 'montant' => 10000, 'frais' => 0]);
    $faux->etats['SANDBOX-DOUBLE'] = ['status' => 'completed', 'amount' => 10000];
    recevoirWebhook($this, 'payment.success', 'SANDBOX-DOUBLE', 10000)->assertOk();

    expect(MiseEnAvant::count())->toBe(1)->and(AlerteAdmin::where('type', 'double_paiement')->exists())->toBeTrue()->and($mea->fresh()->date_fin->toDateString())->toBe($mea->date_fin->toDateString());
});

it('les écrans du gérant : choix de l\'offre, paiement via GeniusPay, suivi', function () {
    $faux = fauxGeniusPay();
    $unite = creerLogement();
    $g = $unite->residence->gerant;

    Livewire::actingAs($g)->test(MisesEnAvantGerant::class)->assertSee('pas encore mis votre résidence en avant', false);
    $page = Livewire::actingAs($g)->test(AcheterMiseEnAvant::class)->assertSee('Une semaine')->assertSee('remboursable', false)->set('offre', 1)->call('payer');
    $page->assertRedirect();
    $mea = MiseEnAvant::sole();
    expect($mea->prix)->toBe(30000)->and($faux->creations)->toHaveCount(1)->and($faux->creations[0]['corps']['amount'])->toBe(30000);

    // Retour de paiement : le serveur vérifie auprès de GeniusPay.
    $paiement = Paiement::sole();
    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'completed', 'amount' => 30000];
    $this->actingAs($g)->get(route('gerant.mise-en-avant.retour', $mea))->assertOk();
    expect($mea->fresh()->statut)->toBe('active');
    $this->actingAs($g)->get(route('gerant.mise-en-avant.retour', $mea))->assertSee('Paiement reçu');
    Livewire::actingAs($g)->test(MisesEnAvantGerant::class)->assertSee('En cours')->assertSee('Un mois');

    // Un autre gérant ne voit pas cette mise en avant.
    $this->actingAs(creerUtilisateur(Role::Gerant))->get(route('gerant.mise-en-avant.retour', $mea))->assertNotFound();
    $this->actingAs(creerUtilisateur(Role::Client))->get(route('gerant.mises-en-avant'))->assertForbidden();
});

it('sans clés de paiement, l\'achat est refusé proprement', function () {
    Parametres::definir('geniuspay.sandbox.cle_secrete', null, null, true);
    $unite = creerLogement();
    $g = $unite->residence->gerant;
    Livewire::actingAs($g)->test(AcheterMiseEnAvant::class)->assertSee('pas encore disponible')->call('payer')->assertSee('pas encore disponible');
    expect(Paiement::count())->toBe(0);
});

it('la base de données fige le prix et les dates d\'une mise en avant payée', function () {
    $faux = fauxGeniusPay();
    $unite = creerLogement();
    $mea = acheterEtPayer($this, $faux, $unite->residence->gerant, $unite->residence, 0);

    expect(codeRefusBase(fn () => DB::table('mise_en_avant')->where('id', $mea->id)->update(['prix' => 1])))->not->toBeNull();
    expect(codeRefusBase(fn () => DB::table('mise_en_avant')->where('id', $mea->id)->update(['date_fin' => today()->addYear()->toDateString()])))->not->toBeNull();
    expect(codeRefusBase(fn () => DB::table('mise_en_avant')->where('id', $mea->id)->update(['statut' => 'suspendue'])))->toBeNull(); // le statut, lui, peut avancer
});

it('les écrans d\'argent de l\'admin s\'affichent avec des paiements de mise en avant', function () {
    $faux = fauxGeniusPay();
    $unite = creerLogement();
    acheterEtPayer($this, $faux, $unite->residence->gerant, $unite->residence, 0);
    $admin = creerUtilisateur(Role::Admin);

    foreach (['admin.paiements', 'admin.controle', 'admin.reversements', 'admin.tableau', 'admin.mises-en-avant'] as $route) {
        $this->actingAs($admin)->get(route($route))->assertOk();
    }
    Livewire::actingAs($admin)->test(Controle::class)->assertSee('Tout concorde');
});
