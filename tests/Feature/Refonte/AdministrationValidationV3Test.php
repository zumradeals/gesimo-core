<?php

use App\Noyau\Enums\Role;
use App\Sejours\Livewire\Admin\ValiderResidence;
use App\Sejours\Services\PiecesVerification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Lot 7 : administration, validation et litiges (contrat docs/contrats/administration-validation-v3.md).

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

it('les écrans de validation et de litiges prennent la présentation commune et gardent leur titre', function () {
    $admin = creerUtilisateur(Role::Admin);
    $gerant = creerUtilisateur(Role::Gerant);
    $r = creerResidence($gerant, ['statut_verification' => 'en_attente', 'soumise_le' => now()]);

    $ecrans = [
        route('admin.tableau') => 'Bonjour', route('admin.residences') => 'Résidences à valider', route('admin.residence', $r) => 'Résidence de test',
        route('admin.gerant', $gerant->profilGerant) => 'Dossier gérant', route('admin.reservations') => 'Toutes les réservations', route('admin.litiges') => 'Litiges',
    ];
    foreach ($ecrans as $url => $titre) {
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('mr-page', false)->assertSee($titre, false)->assertSee('espace-admin', false);
    }
});

it('les écrans d\'administration restent réservés à l\'administration', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    foreach ([route('admin.tableau'), route('admin.residences'), route('admin.litiges'), route('admin.reservations')] as $url) {
        $this->actingAs($gerant)->get($url)->assertForbidden();
    }
});

it('le tableau de bord n\'a plus d\'émoji et propose Traiter et Contrôler', function () {
    $admin = creerUtilisateur(Role::Admin);
    $r = creerResidence(creerUtilisateur(Role::Gerant), ['nom' => 'Villa Marcory', 'statut_verification' => 'en_attente', 'soumise_le' => now()]);

    $this->actingAs($admin)->get(route('admin.tableau'))->assertOk()->assertDontSee('👋', false)->assertSee('Villa Marcory')->assertSee('Contrôler', false);
});

it('Résidences à valider parle de contrat de bail obligatoire, plus de certification facultative', function () {
    $admin = creerUtilisateur(Role::Admin);
    $gerant = creerUtilisateur(Role::Gerant);
    $r = creerResidence($gerant, ['statut_verification' => 'en_attente', 'soumise_le' => now()]);
    $this->actingAs($gerant);
    PiecesVerification::enregistrer($r, ['justificatif_droit_gestion' => UploadedFile::fake()->image('bail.jpg')]);

    $page = $this->actingAs($admin)->get(route('admin.residences'))->assertOk();
    $page->assertSee('Contrats de bail à contrôler', false)->assertSee('obligatoire pour mettre une résidence en ligne', false)
        ->assertDontSee('certification complémentaire', false)->assertDontSee('Leur contrôle est indépendant', false);
});

it('Valider une résidence montre l\'état des deux documents avant la décision', function () {
    $admin = creerUtilisateur(Role::Admin);
    $gerant = creerUtilisateur(Role::Gerant);
    $r = creerResidence($gerant, ['statut_verification' => 'en_attente', 'soumise_le' => now()]);

    $page = Livewire::actingAs($admin)->test(ValiderResidence::class, ['residence' => $r])
        ->assertSee('Pièce d’identité du gérant')->assertSee('Validée')->assertSee('Contrat de bail')->assertSee('Absent')->assertSee('Obligatoire')
        ->assertDontSee('facultatifs')->assertDontSee('non accordée');
    $page->call('valider')->assertHasErrors(['decision']);
});

it('Dossier gérant explique que le certificat n\'est plus demandé', function () {
    $admin = creerUtilisateur(Role::Admin);
    $gerant = creerUtilisateur(Role::Gerant);

    $this->actingAs($admin)->get(route('admin.gerant', $gerant->profilGerant))->assertOk()
        ->assertSee('Ce que ce contrôle change', false)->assertSee('n’est plus demandé', false)->assertDontSee('Contrôles indépendants', false);
});

it('le menu du bas de l\'administration propose Tableau, Résidences, Réservations et Litiges', function () {
    $this->actingAs(creerUtilisateur(Role::Admin))->get(route('admin.tableau'))->assertOk()
        ->assertSee(route('admin.residences'), false)->assertSee(route('admin.litiges'), false)->assertSee('Tableau', false);
});
