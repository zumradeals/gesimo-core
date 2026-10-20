<?php

use App\Noyau\Enums\Role;
use App\Noyau\Livewire\Installation as Page;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Installation;
use App\Parametres\Services\Parametres;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function remplirCompte($page, string $code)
{
    return $page->set('code', $code)->set('nom', 'Koné Djakaridja')->set('telephone', '07 00 00 00 99')->set('email', 'proprio@exemple.ci')
        ->set('motDePasse', 'mot-de-passe-solide')->set('confirmation', 'mot-de-passe-solide');
}

it('sans code d\'installation généré sur le serveur, personne ne peut créer le compte', function () {
    remplirCompte(Livewire::test(Page::class), 'AAAA-BBBB-CCCC')->call('creerCompte')->assertSee('Aucun code d\'installation');
    expect(Utilisateur::count())->toBe(0);
});

it('le code d\'installation est affiché une seule fois ; seule son empreinte est gardée', function () {
    $this->artisan('gesimo:installation')->expectsOutputToContain('Code d\'installation')->assertSuccessful();
    $empreinte = Parametres::obtenir(Installation::CODE);
    expect($empreinte)->toStartWith('$2y$')
        ->and(DB::table('parametre')->where('cle', Installation::CODE)->value('est_secret'))->toBeTrue()
        ->and(DB::table('parametre')->where('cle', Installation::CODE)->value('valeur'))->not->toContain('$2y$'); // chiffré en plus
});

it('un mauvais code est refusé, cinq essais bloquent, le bon code crée le super-administrateur connecté', function () {
    $code = Installation::genererCode();
    $page = Livewire::test(Page::class);

    foreach (range(1, 5) as $i) {
        remplirCompte($page, 'FAUX-CODE-0000')->call('creerCompte')->assertSee('pas le bon');
    }
    remplirCompte($page, $code)->call('creerCompte')->assertHasErrors('code'); // même le bon code est refusé pendant la pause
    expect(Utilisateur::count())->toBe(0);

    RateLimiter::clear('installation-code:127.0.0.1');
    remplirCompte(Livewire::test(Page::class), strtolower($code))->call('creerCompte')->assertSet('etape', 2);
    $u = Utilisateur::sole();
    expect($u->estSuperAdmin())->toBeTrue()->and($u->telephone)->toBe('0700000099')->and(auth()->id())->toBe($u->id);
    expect(DB::table('journal_audit')->where('action', 'compte.cree')->exists())->toBeTrue();
});

it('une fois un super-administrateur créé, l\'assistant ne crée plus de compte et exige sa connexion', function () {
    $code = Installation::genererCode();
    remplirCompte(Livewire::test(Page::class), $code)->call('creerCompte');
    auth()->logout();

    $this->get('/installation')->assertRedirect(route('connexion'));
    expect(fn () => remplirCompte(Livewire::test(Page::class), $code)->call('creerCompte'))->not->toThrow(ValidationException::class);
    expect(Utilisateur::count())->toBe(1);
    // Un client connecté n'entre pas non plus.
    $this->actingAs(creerUtilisateur(Role::Client))->get('/installation')->assertRedirect();
});

it('les étapes enregistrent les réglages (secrets chiffrés) sans demander le mot de passe, et refusent une valeur impossible', function () {
    $su = creerUtilisateur(Role::SuperAdmin);
    $this->actingAs($su);
    $page = Livewire::test(Page::class)->assertSet('etape', 2);

    $page->set('valeurs.general__nom', 'GESIMO Test')->set('valeurs.contact__email', 'equipe@exemple.ci')->call('etapeGenerale')->assertSet('etape', 3);
    $page->set('valeurs.email__hote', 'smtp.exemple.ci')->set('secrets.email__mot_de_passe', 'MDP-SMTP-SECRET')->call('etapeEmail')->assertSet('etape', 4);
    $page->set('valeurs.commission__pourcentage', '-4')->call('etapePaiement')->assertSet('etape', 4)->assertSee('pas de valeur négative');
    $page->set('valeurs.commission__pourcentage', '10')->set('valeurs.paiement__frais_pourcentage', '2')
        ->set('valeurs.geniuspay__sandbox__cle_publique', 'pk_sandbox_x')->set('secrets.geniuspay__sandbox__cle_secrete', 'sk_sandbox_SECRET-INSTALL')->call('etapePaiement')->assertSet('etape', 5)
        ->assertDontSee('SECRET-INSTALL');

    expect(Parametres::obtenir('general.nom'))->toBe('GESIMO Test')->and(Parametres::obtenir('email.hote'))->toBe('smtp.exemple.ci')
        ->and(Parametres::obtenir('geniuspay.sandbox.cle_secrete'))->toBe('sk_sandbox_SECRET-INSTALL')
        ->and(DB::table('parametre')->where('cle', 'email.mot_de_passe')->value('valeur'))->not->toContain('MDP-SMTP');
    expect(json_encode(DB::table('parametre_historique')->get()))->not->toContain('SECRET-INSTALL')->not->toContain('MDP-SMTP');
});

it('l\'assistant ne peut plus être relancé une fois terminé', function () {
    $su = creerUtilisateur(Role::SuperAdmin);
    $this->actingAs($su);
    Livewire::test(Page::class)->call('terminer')->assertRedirect(route('admin.tableau'));

    expect(Installation::estTerminee())->toBeTrue()->and(Parametres::estDefini(Installation::CODE))->toBeFalse();
    $this->get('/installation')->assertNotFound();
    Livewire::test(Page::class)->assertNotFound();
    auth()->logout();
    $this->get('/installation')->assertNotFound();
    $this->artisan('gesimo:installation')->expectsOutputToContain('déjà terminée')->assertFailed();
});

it('seul un super-administrateur connecté peut agir dans les étapes suivantes', function () {
    $this->actingAs(creerUtilisateur(Role::SuperAdmin));
    $page = Livewire::test(Page::class);
    auth()->logout();
    $this->actingAs(creerUtilisateur(Role::Client));
    $page->set('valeurs.general__nom', 'Piraté')->call('etapeGenerale')->assertForbidden();
    expect(Parametres::obtenir('general.nom'))->toBeNull();
});

it('le tableau de bord rappelle au super-administrateur de terminer l\'installation', function () {
    $this->actingAs(creerUtilisateur(Role::SuperAdmin))->get(route('admin.tableau'))->assertSee('Terminer l\'installation', false);
    Installation::terminer(auth()->user());
    $this->get(route('admin.tableau'))->assertDontSee('Terminer l\'installation', false);
});
