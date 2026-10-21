<?php

use App\Noyau\Enums\Role;
use App\Parametres\Livewire\EspaceParametres;
use App\Parametres\Services\Marque;
use App\Parametres\Services\Parametres;
use App\Sejours\Livewire\EditerResidence;
use App\Sejours\Livewire\Recherche;
use App\Sejours\Models\Avis;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function superAdminSite()
{
    $u = creerUtilisateur(Role::SuperAdmin);
    $u->update(['mot_de_passe' => 'mot-de-passe-secret-1']);

    return $u->fresh();
}

function reglagesGeneral($u)
{
    return Livewire::actingAs($u)->test(EspaceParametres::class, ['rubrique' => 'general']);
}

beforeEach(function () {
    Storage::fake('public');
    configurerGeniusPay('sandbox', 10.0);
});

it('la page d\'accueil masque les sections sans contenu réel : aucun faux logement, commune ni avis', function () {
    $this->get('/')->assertOk()
        ->assertSee('Trouvez votre résidence meublée')->assertSee('Comment ça marche')->assertSee('Devenir gérant partenaire')
        ->assertDontSee('Logements à la une')->assertDontSee('Choisissez votre commune')->assertDontSee('Ce que disent nos voyageurs');
});

it('la page d\'accueil montre les vrais logements, communes et avis', function () {
    $unite = creerLogement(18000, null, ['commune' => 'Cocody']);
    $faux = fauxGeniusPay();
    $reservation = reservationTerminee($this, $faux);
    Avis::create(['reservation_id' => $reservation->id, 'client_id' => $reservation->client_id, 'note' => 5, 'commentaire' => 'Séjour parfait, merci beaucoup.']);

    $this->get('/')->assertOk()
        ->assertSee('Logements à la une')->assertSee($unite->nom)->assertSee("18\u{202F}000")
        ->assertSee('Choisissez votre commune')->assertSee('Cocody')
        ->assertSee('Ce que disent nos voyageurs')->assertSee('Séjour parfait, merci beaucoup.');
});

it('un avis de moins de 4 étoiles n\'apparaît pas sur l\'accueil', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationTerminee($this, $faux);
    Avis::create(['reservation_id' => $reservation->id, 'client_id' => $reservation->client_id, 'note' => 2, 'commentaire' => 'Moyen du tout.']);

    $this->get('/')->assertDontSee('Moyen du tout.')->assertDontSee('Ce que disent nos voyageurs');
});

it('les coordonnées, réseaux sociaux et informations légales saisis dans Réglages apparaissent dans le pied de page', function () {
    $this->get('/')->assertDontSee('RCCM')->assertDontSee('wa.me')->assertDontSee('aria-label="Facebook"', false);

    reglagesGeneral(superAdminSite())
        ->set('valeurs.general__slogan', 'Votre séjour, notre métier')
        ->set('valeurs.general__telephone', '+225 07 00 00 00 00')
        ->set('valeurs.general__whatsapp', '+225 05 11 22 33 44')
        ->set('valeurs.contact__email', 'equipe@exemple.ci')
        ->set('valeurs.general__adresse', 'Cocody, Abidjan')
        ->set('valeurs.general__raison_sociale', 'Exemple SARL')
        ->set('valeurs.general__rccm', 'CI-ABJ-2026-B-1234')
        ->set('valeurs.general__nif', '2600000A')
        ->set('valeurs.social__facebook', 'https://www.facebook.com/exemple')
        ->set('valeurs.social__instagram', 'https://www.instagram.com/exemple')
        ->call('enregistrer')->assertHasNoErrors()->assertSee('Réglages enregistrés');

    $this->get('/a-propos')->assertOk()
        ->assertSee('Votre séjour, notre métier')->assertSee('+225 07 00 00 00 00')->assertSee('tel:+2250700000000', false)
        ->assertSee('https://wa.me/2250511223344', false)->assertSee('equipe@exemple.ci')->assertSee('Cocody, Abidjan')
        ->assertSee('Exemple SARL · RCCM CI-ABJ-2026-B-1234 · NIF 2600000A')
        ->assertSee('href="https://www.facebook.com/exemple"', false)->assertSee('href="https://www.instagram.com/exemple"', false)
        ->assertDontSee('aria-label="LinkedIn"', false);
});

it('un lien de réseau social doit être une adresse https complète : rien d\'autre n\'est enregistré', function () {
    foreach (['http://facebook.com/x', 'javascript:alert(1)', 'facebook.com/x', 'https://', 'https://exemple.ci/a b'] as $mauvais) {
        reglagesGeneral(superAdminSite())->set('valeurs.social__facebook', $mauvais)->call('enregistrer')->assertSet('erreurs', fn ($e) => str_starts_with($e['social.facebook'] ?? '', 'Indiquez une adresse complète commençant par https://'));
    }
    expect(Parametres::obtenir('social.facebook'))->toBeNull();

    reglagesGeneral(superAdminSite())->set('valeurs.social__facebook', '')->set('valeurs.general__telephone', 'abc')->call('enregistrer')->assertSet('erreurs', fn ($e) => ($e['general.telephone'] ?? null) === 'Le numéro de téléphone n\'est pas valide.');
});

it('seul le super-administrateur peut changer ces informations', function () {
    foreach ([Role::Client, Role::Gerant, Role::Personnel, Role::Admin] as $role) {
        $this->actingAs(creerUtilisateur($role))->get('/admin/reglages/general')->assertForbidden();
    }
});

it('l\'image d\'accueil est contrôlée, réduite à 1600 px et utilisée dans le héros', function () {
    $su = superAdminSite();
    reglagesGeneral($su)->set('imageAccueil', UploadedFile::fake()->create('mal.svg', 10, 'image/svg+xml'))->call('enregistrer')->assertHasErrors('imageAccueil');
    reglagesGeneral($su)->set('imageAccueil', UploadedFile::fake()->image('enorme.jpg', 3000, 2000)->size(5000))->call('enregistrer')->assertHasErrors('imageAccueil');
    expect(Marque::imageAccueilUrl())->toBeNull();

    reglagesGeneral($su)->set('imageAccueil', UploadedFile::fake()->image('accueil.jpg', 3000, 2000)->size(1500))->call('enregistrer')->assertHasNoErrors();
    $chemin = Parametres::obtenir('general.image_accueil');
    Storage::disk('public')->assertExists($chemin);
    expect($chemin)->toStartWith('accueil/')->and($chemin)->toEndWith('.jpg');
    [$largeur, $hauteur] = getimagesizefromstring(Storage::disk('public')->get($chemin));
    expect(max($largeur, $hauteur))->toBeLessThanOrEqual(1600);

    $this->get('/')->assertSee(Marque::imageAccueilUrl(), false);

    reglagesGeneral($su)->call('retirerImageAccueil');
    Storage::disk('public')->assertMissing($chemin);
    $this->get('/')->assertDontSee('accueil/', false);
});

it('les mentions légales montrent seulement ce qui est renseigné et se remplacent par un texte libre', function () {
    $this->get('/mentions-legales')->assertOk()->assertSee('Mentions légales')->assertDontSee('Registre du commerce');

    $su = superAdminSite();
    reglagesGeneral($su)->set('valeurs.general__rccm', 'CI-ABJ-2026-B-1234')->set('valeurs.general__raison_sociale', 'Exemple SARL')->call('enregistrer');
    $this->get('/mentions-legales')->assertSee('Exemple SARL')->assertSee('CI-ABJ-2026-B-1234')->assertSee('Registre du commerce');

    reglagesGeneral($su)->set('valeurs.general__mentions_legales', "Éditeur : Exemple SARL.\nDirecteur : Test <b>gras</b>")->call('enregistrer');
    $this->get('/mentions-legales')->assertSee('Éditeur : Exemple SARL.')->assertSee('&lt;b&gt;gras&lt;/b&gt;', false)->assertDontSee('<b>gras</b>', false);
});

it('« Devenir gérant partenaire » calcule un exemple chiffré avec le taux réglé', function () {
    configurerGeniusPay('sandbox', 12.5);
    $this->get('/devenir-partenaire')->assertSee('12,5 %')->assertSee('Exemple sur une réservation de')->assertSee("6\u{202F}250")->assertSee("43\u{202F}750");

    Parametres::definir('commission.pourcentage', null);
    $this->get('/devenir-partenaire')->assertOk()->assertDontSee('Exemple sur une réservation');
});

it('chaque rôle reçoit le bon habillage : site, menu latéral d\'administration ou espace gérant', function () {
    // Visiteur : en-tête du site, pied de page, menu d'application seulement visible dans l'application installée.
    $this->get('/')->assertSee('class="site-h"', false)->assertSee('class="site-f"', false)->assertSee('barre-bas-visiteur', false)->assertSee('Créer un compte');

    // Client connecté : sur les pages publiques, le site avec « Mon compte » ; dans son espace, menu latéral et rien du site public.
    $client = creerUtilisateur(Role::Client);
    $this->actingAs($client)->get('/')->assertSee('class="site-h"', false)->assertSee('Mon compte')->assertDontSee('barre-bas-visiteur', false)->assertDontSee('Créer un compte');
    $this->actingAs($client)->get('/compte')->assertSee('class="adm-side"', false)->assertSee('Mon espace')->assertSee('Chercher un logement')->assertDontSee('class="site-h"', false)->assertDontSee('class="site-f"', false);

    // Administrateur : menu latéral, et « Réglages » seulement pour le super-administrateur.
    $this->actingAs(creerUtilisateur(Role::Admin))->get('/admin')->assertSee('class="adm-side"', false)->assertSee('Résidences à valider')->assertDontSee('Réglages')->assertDontSee('class="site-f"', false);
    $this->actingAs(superAdminSite())->get('/admin')->assertSee('class="adm-side"', false)->assertSee('Réglages')->assertSee('Modèles d\'e-mails');

    // Gérant : menu latéral à lui, mais le site public autour des pages publiques.
    $gerant = creerUtilisateur(Role::Gerant);
    $this->actingAs($gerant)->get('/gerant/arrivees')->assertOk()->assertSee('class="adm-side"', false)->assertSee('Espace gérant')->assertSee('Mises en avant')->assertDontSee('class="site-h"', false)->assertDontSee('class="site-f"', false);
    $this->actingAs($gerant)->get('/a-propos')->assertSee('class="site-h"', false);
});

it('la recherche trie par prix décroissant sans jamais passer un sponsorisé derrière', function () {
    creerLogement(10000, null);
    creerLogement(30000, null);
    $arrivee = prochainVendredi()->toDateString();
    $depart = prochainVendredi()->addDay()->toDateString();

    $html = Livewire::withQueryParams(['arrivee' => $arrivee, 'depart' => $depart, 'personnes' => 2])->test(Recherche::class)->assertSee('Trier par')->html();
    expect(strpos($html, '10'."\u{202F}000"))->toBeLessThan(strpos($html, '30'."\u{202F}000"));

    $html = Livewire::withQueryParams(['arrivee' => $arrivee, 'depart' => $depart, 'personnes' => 2, 'tri' => 'prix_desc'])->test(Recherche::class)->html();
    expect(strpos($html, '30'."\u{202F}000"))->toBeLessThan(strpos($html, '10'."\u{202F}000"));
});

it('le tableau de bord admin compare la semaine à la précédente et liste les dernières réservations', function () {
    $faux = fauxGeniusPay();
    $r = reservationAPayer();
    payerEtConfirmer($this, $faux, $r);

    $this->actingAs(creerUtilisateur(Role::Admin))->get('/admin')->assertOk()
        ->assertSee('Réservations (7 jours)')->assertSee('vs semaine passée')->assertSee('Dernières réservations')->assertSee('#'.$r->id)
        ->assertSee('Réservations par jour');
});

it('l\'assistant de résidence avance en 3 étapes et ne saute pas une étape tant que la résidence n\'existe pas', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $unite = creerLogement(15000, null, ['gerant_id' => $gerant->id]);
    $residence = $unite->residence;

    Livewire::actingAs($gerant)->test(EditerResidence::class)->call('allerEtape', 3)->assertSet('etape', 1);

    $page = Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->assertSet('etape', 1)->assertSee('1 Ma résidence')->assertSee('Étape suivante : mes logements');
    $page->call('allerEtape', 2)->assertSet('etape', 2)->assertSee('Mes logements')->assertSee($unite->nom);
    $page->call('allerEtape', 3)->assertSet('etape', 3)->assertSee('Envoyer pour vérification')->assertSee('Pièce d\'identité du gérant', false);
    $page->call('allerEtape', 9)->assertSet('etape', 1);
});

it('chaque espace connecté a la même structure : menu latéral, titre de page, aucun en-tête du site', function () {
    $client = creerUtilisateur(Role::Client);
    foreach (['/compte', '/mes-reservations', '/compte/profil'] as $url) {
        $this->actingAs($client)->get($url)->assertOk()->assertSee('class="adm-side"', false)->assertSee('barre-bas adm-bas', false)->assertDontSee('class="site-h"', false);
    }
});

it('un administrateur ou un gérant qui clique sur « Réserver » reçoit une explication claire et non une page vide', function () {
    $unite = creerLogement();
    $q = ['arrivee' => prochainVendredi()->toDateString(), 'depart' => prochainVendredi()->addDays(2)->toDateString(), 'personnes' => 2];

    foreach ([Role::Admin, Role::Gerant, Role::SuperAdmin] as $role) {
        $this->actingAs(creerUtilisateur($role))->get(route('reserver', ['unite' => $unite->id] + $q))
            ->assertForbidden()->assertSee('Seuls les comptes « Client » peuvent réserver', false)->assertSee('Me déconnecter pour utiliser un compte client');
    }
    // Un client réserve normalement.
    $this->actingAs(creerUtilisateur(Role::Client))->get(route('reserver', ['unite' => $unite->id] + $q))->assertOk();
});

it('un visiteur non connecté qui clique sur « Réserver » est envoyé vers la connexion', function () {
    $unite = creerLogement();
    $this->get(route('reserver', ['unite' => $unite->id, 'arrivee' => prochainVendredi()->toDateString(), 'depart' => prochainVendredi()->addDay()->toDateString(), 'personnes' => 2]))->assertRedirect(route('connexion'));
});
