<?php

use App\Noyau\Enums\Role;
use App\Noyau\Livewire\Installation;
use Livewire\Livewire;

// Lot 8 : administration, argent et réglages (contrat docs/contrats/administration-argent-reglages-v3.md). Aucune règle d'argent ne change.

it('les écrans d\'argent et de réglages prennent la présentation commune et gardent leur titre', function () {
    $admin = creerUtilisateur(Role::SuperAdmin);

    $ecrans = [
        route('admin.paiements') => 'Paiements', route('admin.reversements') => 'Reversements', route('admin.controle') => 'Contrôle',
        route('admin.mises-en-avant') => 'Mises en avant', route('admin.journal') => 'Journal d\'audit', route('admin.messages') => 'Modèles d\'e-mails',
        route('admin.reglages') => 'Réglages de la plateforme', route('admin.reglages', ['rubrique' => 'reservation']) => 'Réservation',
        route('admin.reglages', ['rubrique' => 'tests']) => 'Tester les connexions', route('admin.reglages', ['rubrique' => 'historique']) => 'Historique des modifications',
    ];
    foreach ($ecrans as $url => $titre) {
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('mr-page', false)->assertSee($titre, false)->assertSee('espace-admin', false);
    }
});

it('les écrans d\'argent restent réservés à l\'administration', function () {
    foreach ([route('admin.paiements'), route('admin.reversements'), route('admin.controle'), route('admin.journal')] as $url) {
        $this->actingAs(creerUtilisateur(Role::Gerant))->get($url)->assertForbidden();
        $this->actingAs(creerUtilisateur(Role::Client))->get($url)->assertForbidden();
    }
});

it('les tableaux d\'argent deviennent des cartes sur téléphone grâce aux intitulés de colonne', function () {
    $admin = creerUtilisateur(Role::Admin);

    $this->actingAs($admin)->get(route('admin.paiements'))->assertOk()->assertSee('tab-r', false);
    $this->actingAs($admin)->get(route('admin.journal'))->assertOk()->assertSee('tab-r', false);
    expect(file_get_contents(resource_path('css/mes-reservations.css')))->toContain('attr(data-l)');
});

it('Paiements garde l\'avertissement : les paiements ne se modifient jamais', function () {
    $this->actingAs(creerUtilisateur(Role::Admin))->get(route('admin.paiements'))->assertOk()->assertSee('Les paiements ne se modifient jamais', false);
});

it('Reversements rappelle que l\'argent est envoyé hors de la plateforme', function () {
    $this->actingAs(creerUtilisateur(Role::Admin))->get(route('admin.reversements'))->assertOk()->assertSee('Avelis ne verse rien automatiquement', false)->assertSee('Historique des reversements', false);
});

it('Contrôle garde son calcul au franc près', function () {
    $this->actingAs(creerUtilisateur(Role::Admin))->get(route('admin.controle'))->assertOk()->assertSee('Tout concorde', false)->assertSee('Calcul', false);
});

it('l\'installation accueille sans émoji et garde ses cinq étapes', function () {
    $page = Livewire::test(Installation::class)->assertSee('Bienvenue sur Avelis Séjours')->assertDontSee('👋')->assertSee('Compte')->assertSee('Paiement');
    expect($page->html())->toContain('gq-etapes');
});

it('les réglages gardent leurs secrets masqués', function () {
    $html = $this->actingAs(creerUtilisateur(Role::SuperAdmin))->get(route('admin.reglages', ['rubrique' => 'paiement']))->assertOk()->getContent();
    expect($html)->not->toContain('mot-de-passe-de-test');
});
