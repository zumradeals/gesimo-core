<?php

use App\Noyau\Enums\Role;
use App\Noyau\Livewire\AccepterInvitation;
use App\Noyau\Livewire\Connexion;
use App\Noyau\Livewire\ConnexionParLien;
use App\Noyau\Livewire\Inscription;
use App\Noyau\Mail\LienConnexion;
use App\Noyau\Services\Connexion as ServiceConnexion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Entrée du visiteur (docs/contrats/entree-visiteur-v3.md) : connexion, inscription, lien, invitation, erreurs. */
it('la connexion montre les deux moitiés, l\'aide du téléphone et le mot de passe oublié', function () {
    $this->get(route('connexion'))->assertOk()
        ->assertSee('en-entree', false)->assertSee('Content de vous revoir')
        ->assertSee('Retrouvez vos réservations et vos reçus')->assertSee('Paiement sécurisé par Mobile Money ou carte')
        ->assertSee('10 chiffres, sans espace ni indicatif.')->assertSee('Afficher')
        ->assertSee('Mot de passe oublié ? Recevoir un lien par e-mail')->assertSee('Pas encore de compte ?')
        ->assertSee('images/sejours/salon-balcon.webp', false)->assertDontSee('Vos dates sont conservées');
});

it('la connexion dit simplement ce qui manque', function () {
    Livewire::test(Connexion::class)->call('seConnecter')
        ->assertSee('Indiquez votre numéro de téléphone.')->assertSee('Indiquez votre mot de passe.');
    Livewire::test(Connexion::class)->call('envoyerLien')->assertSee('Indiquez votre numéro de téléphone.');
    Livewire::test(Connexion::class)->set('telephone', '0700000000')->set('motDePasse', 'faux-mot-de-passe')->call('seConnecter')
        ->assertSee('Téléphone ou mot de passe incorrect.');
});

it('la connexion garde le message « dates conservées » pour un visiteur qui vient d\'une réservation', function () {
    $this->withSession(['url.intended' => url('/reserver/1?arrivee=2027-01-10&depart=2027-01-12')])->get(route('connexion'))->assertSee('Vos dates sont conservées');
});

it('l\'inscription propose deux grandes cartes et rappelle le contrôle de la pièce d\'un gérant', function () {
    $this->get(route('inscription'))->assertOk()
        ->assertSee('Rejoignez Avelis Séjours')->assertSee('Réserver un séjour')->assertSee('Je cherche un logement meublé en Côte d\'Ivoire.', false)
        ->assertSee('Proposer ma résidence')->assertSee('8 caractères minimum.')->assertSee('10 chiffres, sans espace ni indicatif.')
        ->assertDontSee('votre pièce d\'identité est demandée', false);

    Livewire::test(Inscription::class)->assertDontSee('contrôlée avant la publication')
        ->set('profil', 'gerant')->assertSee('contrôlée avant la publication de votre première annonce');
    Livewire::withQueryParams(['profil' => 'gerant'])->test(Inscription::class)->assertSet('profil', 'gerant')->assertSee('contrôlée avant la publication');
});

it('l\'inscription garde ses messages d\'erreur existants', function () {
    $existant = creerUtilisateur(Role::Client);
    Livewire::test(Inscription::class)->set('nom', 'Awa')->set('telephone', $existant->telephone)->set('email', 'neuf@example.test')->set('motDePasse', 'court')->set('profil', 'client')->call('inscrire')
        ->assertHasErrors(['telephone', 'motDePasse'])->assertSee('Ce numéro de téléphone a déjà un compte. Connectez-vous.');
});

it('le lien de connexion montre un bouton, ou une carte « Lien expiré » avec le retour à la connexion', function () {
    Mail::fake();
    $u = creerUtilisateur(Role::Client);
    app(ServiceConnexion::class)->envoyerLienEmail($u->telephone, '10.1.1.1');
    Mail::assertQueued(LienConnexion::class, function ($m) {
        $GLOBALS['lien_v3'] = $m->lien;

        return true;
    });

    Livewire::test(ConnexionParLien::class, ['jeton' => basename($GLOBALS['lien_v3'])])->assertSee('Bienvenue')->assertSee('Me connecter')->assertSee('Ce lien ne marche qu\'une fois', false);
    Livewire::test(ConnexionParLien::class, ['jeton' => 'faux-lien'])->assertSee('Lien expiré')->assertSee('Revenir à la connexion')->assertSee(route('connexion'), false);
});

it('l\'invitation expirée propose de retourner à l\'accueil', function () {
    Livewire::test(AccepterInvitation::class, ['jeton' => 'faux-lien'])->assertSee('Invitation expirée')->assertSee('Aller à l\'accueil', false)->assertSee(route('accueil'), false);
});

// ----------------------------------------------------------------------------------------------------
// Pages d'erreur
// ----------------------------------------------------------------------------------------------------

it('une page qui n\'existe pas affiche la page 404 du site', function () {
    $this->get('/cette-page-n-existe-pas')->assertNotFound()
        ->assertSee('Erreur 404')->assertSee('Cette page n\'existe pas')->assertSee('Aller à l\'accueil', false)->assertSee('Voir les logements')
        ->assertSee('<body class="charte', false)->assertSee('site-h', false)->assertSee('site-f', false);
});

it('un compte sans droit voit la page 403 avec son nom et un retour utile', function () {
    $client = creerUtilisateur(Role::Client);
    $this->actingAs($client)->get(route('admin.tableau'))->assertForbidden()
        ->assertSee('Erreur 403')->assertSee('Cette page n\'est pas accessible avec ce compte')->assertSee($client->nom)->assertSee('Retour à mon espace')->assertSee('Voir les logements');
    auth()->logout();
    $this->get(route('admin.tableau'))->assertRedirect(route('connexion'));
});

it('une session expirée affiche la page 419 avec recharger et se reconnecter', function () {
    Route::middleware('web')->get('/_test/419', fn () => abort(419));
    $this->get('/_test/419')->assertStatus(419)
        ->assertSee('Votre session a expiré')->assertSee('Recharger la page')->assertSee('Me reconnecter')->assertSee('vos réservations ne sont pas perdues', false);
});

it('une erreur du serveur affiche la page 500 sans détail technique', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/_test/500', fn () => throw new RuntimeException('secret-technique-à-ne-jamais-afficher'));
    $this->get('/_test/500')->assertStatus(500)
        ->assertSee('Un problème de notre côté')->assertSee('Réessayer')->assertSee('Nous contacter')->assertDontSee('secret-technique');
});

it('les pages 500 et 503 s\'affichent sans toucher la base de données, la session ni les réglages', function () {
    $requetes = 0;
    DB::listen(function () use (&$requetes) {
        $requetes++;
    });
    foreach (['errors.500', 'errors.503'] as $vue) {
        $html = view($vue, ['exception' => new HttpException(500)])->render();
        expect($html)->toContain('Avelis Séjours')->toContain('<body class="charte')->not->toContain('Se connecter')->not->toContain('Mon compte');
    }
    expect($requetes)->toBe(0);
    expect(view('errors.503', ['exception' => new HttpException(503)])->render())->toContain('Nous revenons très vite')->toContain('Vos réservations sont conservées');
});

it('la page hors connexion garde le texte rassurant aux couleurs de la charte', function () {
    $this->get(route('pwa.hors-connexion'))->assertOk()
        ->assertSee('Vous êtes hors connexion')->assertSee('Rien n\'a été envoyé', false)->assertSee('#ffeadb', false)->assertSee('#fff8f0', false);
});

it('les anciennes pages d\'entrée vertes ne sont plus utilisées', function () {
    foreach (['connexion', 'inscription', 'connexion-par-lien', 'accepter-invitation'] as $vue) {
        $source = file_get_contents(base_path("resources/views/livewire/noyau/{$vue}.blade.php"));
        expect($source)->not->toContain('class="auth"')->not->toContain('auth-vis')->not->toContain('onglets-a');
    }
});
