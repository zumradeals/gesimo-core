<?php

use App\Parametres\Services\Parametres;

// Lot 4 : pages d'information (contrat docs/contrats/pages-information-v3.md).

it('Contact met le formulaire en premier et renvoie vers l\'Aide au lieu de répéter les questions', function () {
    $page = $this->get(route('contact'))->assertOk();
    $page->assertSee('Une réponse rapide ?', false)->assertSee('Voir les questions fréquentes', false)->assertSee(route('faq'), false);
    $page->assertDontSee('Comment payer ?', false);
    $page->assertSeeInOrder(['Une question ? Écrivez-nous', 'Nos coordonnées', 'Une réponse rapide ?'], false);
});

it('l\'Aide garde les six questions et les coordonnées', function () {
    $this->get(route('faq'))->assertOk()->assertSee('Comment payer ?', false)->assertSee('Quand le gérant est-il payé ?', false)->assertSee('Nos coordonnées', false);
});

it('les trois textes de lecture ont un sommaire qui pointe vers leurs sections', function () {
    $this->get(route('conditions'))->assertOk()
        ->assertSee('Sur cette page', false)->assertSee('href="#sec-3"', false)->assertSee('id="sec-3"', false)->assertSee('Brouillon de travail', false);
    $this->get(route('confidentialite'))->assertOk()->assertSee('href="#sec-4"', false)->assertSee('id="sec-4"', false)->assertSee('Vos droits', false);
    $this->get(route('mentions-legales'))->assertOk()->assertSee('href="#sec-paiement"', false)->assertSee('id="sec-paiement"', false);
});

it('une page modifiée dans les Paramètres garde son texte libre', function () {
    Parametres::definir('general.conditions', "Mon texte libre.\nDeuxième ligne.");

    $this->get(route('conditions'))->assertOk()->assertSee('Mon texte libre.', false)->assertDontSee('Rôle d\'Avelis', false);
});

it('Devenir partenaire annonce le taux, ou dit qu\'il sera communiqué, et a une barre d\'action pour téléphone', function () {
    $page = $this->get(route('devenir-partenaire'))->assertOk();
    $page->assertSee('pa-barre', false)->assertSee('Devenir gérant partenaire', false);
    $page->assertSee('Le taux de commission sera communiqué à l’inscription.', false);
});
