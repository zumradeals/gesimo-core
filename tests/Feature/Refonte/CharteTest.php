<?php

use App\Noyau\Enums\Role;

/** Socle de la charte orange (docs/contrats/socle-charte-v1.md) : toutes les pages portent la même base. */
it('toutes les pages portent la classe de la charte, quel que soit l\'espace', function () {
    $unite = creerLogement();
    foreach (['accueil', 'recherche', 'connexion', 'inscription', 'contact', 'faq', 'a-propos', 'devenir-partenaire', 'conditions'] as $route) {
        $this->get(route($route))->assertOk()->assertSee('<body class="charte', false);
    }
    $this->get(route('logement', $unite))->assertOk()->assertSee('<body class="charte', false);

    $this->actingAs(creerUtilisateur(Role::Client))->get(route('compte'))->assertOk()->assertSee('<body class="charte', false);
    $this->actingAs(creerUtilisateur(Role::Gerant))->get(route('gerant.calendrier'))->assertOk()->assertSee('<body class="charte', false);
    $this->actingAs(creerUtilisateur(Role::Admin))->get(route('admin.tableau'))->assertOk()->assertSee('<body class="charte', false);
    $this->actingAs(creerUtilisateur(Role::Gerant))->get(route('admin.tableau'))->assertForbidden()->assertSee('<body class="charte', false);
});

it('l\'application installable prend les couleurs de la charte', function () {
    $m = $this->get(route('pwa.manifeste'))->assertOk()->json();
    expect($m['theme_color'])->toBe('#ff6a00')->and($m['background_color'])->toBe('#fff8f0');
    $this->get(route('accueil'))->assertSee('name="theme-color" content="#FF6A00"', false);
    $this->get(route('connexion'))->assertSee('name="theme-color" content="#FF6A00"', false);
});

it('les anciens verts de la marque ne reviennent pas dans les feuilles de style', function () {
    $interdits = ['#0f766e', '#107c73', '#14958a', '#0b5a54', '#0c5e58', '#0d2f2c', '#102f2b', '#172d2a'];
    foreach (glob(base_path('resources/css/*.css')) as $fichier) {
        $css = mb_strtolower(file_get_contents($fichier));
        foreach ($interdits as $vert) {
            expect(str_contains($css, $vert))->toBeFalse(basename($fichier)." contient encore {$vert}");
        }
    }
    // Les jetons Tailwind de la marque sont en orange.
    $app = file_get_contents(base_path('resources/css/app.css'));
    expect($app)->toContain('--color-marque-500: #ff6a00;')->toContain('--color-marque-600: #b94700;');
});

it('aucun plafond de largeur ne laisse un vide à droite de l\'espace client, gérant et administration', function () {
    $site = file_get_contents(base_path('resources/css/site.css'));
    expect($site)->not->toContain('.adm-main{padding:28px 34px 40px;max-width:');
    expect(file_get_contents(base_path('resources/css/mes-reservations.css')))->not->toMatch('/\.mr-page \{[^}]*max-width/');
});
