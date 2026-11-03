<?php

use App\Noyau\Enums\Role;
use App\Sejours\Livewire\EditerResidence;
use Livewire\Livewire;

// Lot 6 : gérant, gestion (contrat docs/contrats/gerant-gestion-v3.md). Contenu inchangé, présentation à la charte orange.

it('les écrans de gestion du gérant prennent la présentation commune et gardent leur titre', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $r = creerResidence($gerant);
    $r->unites()->create(['nom' => 'Studio', 'type' => 'studio', 'capacite' => 2]);

    $ecrans = [
        route('gerant.residences') => 'Mes résidences', route('gerant.residence', $r) => 'Résidence de test', route('gerant.profil') => 'Mon profil gérant',
        route('gerant.argent') => 'Argent', route('gerant.mises-en-avant') => 'Mes mises en avant', route('gerant.personnel') => 'Mon personnel', route('gerant.aide') => 'Aide du gérant',
    ];
    foreach ($ecrans as $url => $titre) {
        $this->actingAs($gerant)->get($url)->assertOk()->assertSee('mr-page', false)->assertSee($titre, false)->assertSee('espace-gerant', false);
    }
});

it('Mes résidences propose Modifier et Accès à la résidence pour chaque résidence', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $r = creerResidence($gerant, ['nom' => 'Villa Marcory']);

    $this->actingAs($gerant)->get(route('gerant.residences'))->assertOk()->assertSee('Villa Marcory')
        ->assertSee(route('gerant.residence', $r), false)->assertSee(route('gerant.residence.acces', $r), false);
});

it('Mes résidences sans résidence invite à ajouter la première', function () {
    $this->actingAs(creerUtilisateur(Role::Gerant))->get(route('gerant.residences'))->assertOk()->assertSee('Vous n\'avez pas encore de résidence', false)->assertSee(route('gerant.residence.nouvelle'), false);
});

it('Mon profil gérant n\'a plus d\'onglet documents et renvoie vers Ma vérification', function () {
    $gerant = creerUtilisateur(Role::Gerant);

    $page = $this->actingAs($gerant)->get(route('gerant.profil'))->assertOk();
    $page->assertSee('Vos informations', false)->assertSee('Votre présentation publique', false)->assertSee(route('gerant.verification'), false)
        ->assertDontSee('Mes documents', false)->assertDontSee('Certificat de résidence', false)->assertDontSee('Choisir mon document', false);
});

it('Mon profil gérant indique la situation : identité validée, puis gérant vérifié', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $this->actingAs($gerant)->get(route('gerant.profil'))->assertSee('Pièce d’identité validée', false)->assertDontSee('✓ Gérant vérifié', false);

    $r = creerResidence($gerant, ['badge_verifie' => true, 'gestion_verifiee_le' => now()]);
    $r->documents()->create(['type' => 'justificatif_droit_gestion', 'chemin' => 'bail.pdf', 'statut' => 'accepte']);
    $this->actingAs($gerant)->get(route('gerant.profil'))->assertSee('✓ Gérant vérifié', false);
});

it('l\'éditeur de résidence garde ses trois étapes et ses messages', function () {
    $gerant = creerUtilisateur(Role::Gerant);

    Livewire::actingAs($gerant)->test(EditerResidence::class)->assertSee('Ma résidence')->assertSee('Mes logements')->assertSee('Vérification et envoi')
        ->set('nom', '')->call('enregistrerResidence')->assertHasErrors(['nom']);
});

it('Argent, Mises en avant et Personnel restent réservés au gérant', function () {
    foreach ([route('gerant.argent'), route('gerant.mises-en-avant'), route('gerant.personnel'), route('gerant.profil')] as $url) {
        $this->actingAs(creerUtilisateur(Role::Client))->get($url)->assertForbidden();
    }
});

it('l\'aide du gérant donne les textes à jour : deux documents et accès à la résidence', function () {
    $this->actingAs(creerUtilisateur(Role::Gerant))->get(route('gerant.aide'))->assertOk()
        ->assertSee('Quels documents faut-il pour publier', false)->assertSee('Accès à la résidence', false)->assertSee('Écrire à l\'équipe', false);
});
