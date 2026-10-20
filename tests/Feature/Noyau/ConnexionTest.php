<?php

use App\Noyau\Enums\Role;
use App\Noyau\Exceptions\ConnexionRefusee;
use App\Noyau\Livewire\Connexion as ComposantConnexion;
use App\Noyau\Livewire\ConnexionParLien;
use App\Noyau\Livewire\Inscription;
use App\Noyau\Mail\LienConnexion;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Connexion;
use App\Noyau\Services\Sms\FournisseurSms;
use App\Parametres\Services\Parametres;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

function compteAvecMotDePasse(string $motDePasse = 'un-bon-mot-de-passe'): Utilisateur
{
    $u = creerUtilisateur(Role::Client);
    $u->update(['mot_de_passe' => $motDePasse]);

    return $u->fresh();
}

/** Faux fournisseur : il garde les SMS « envoyés » pour que le test puisse les lire. */
function fauxFournisseurSms(): object
{
    $faux = new class implements FournisseurSms
    {
        public array $envoyes = [];

        public function estConfigure(): bool
        {
            return true;
        }

        public function envoyer(string $telephone, string $message): void
        {
            $this->envoyes[] = [$telephone, $message];
        }

        public function dernierCode(): ?string
        {
            return $this->envoyes === [] ? null : (preg_match('/\b(\d{6})\b/', end($this->envoyes)[1], $m) ? $m[1] : null);
        }
    };
    app()->instance(FournisseurSms::class, $faux);

    return $faux;
}

// --- Téléphone + mot de passe -----------------------------------------------------------------

it('connecte avec le téléphone et le mot de passe, quelle que soit la façon d\'écrire le numéro', function () {
    $u = compteAvecMotDePasse();

    $connecte = app(Connexion::class)->parMotDePasse('+225 '.chunk_split($u->telephone, 2, ' '), 'un-bon-mot-de-passe', '10.0.0.1');

    expect($connecte->id)->toBe($u->id)->and(auth()->id())->toBe($u->id);
});

it('refuse avec le même message qu\'on se trompe de numéro ou de mot de passe', function () {
    $u = compteAvecMotDePasse();
    $essayer = function (string $tel, string $mdp) {
        try {
            app(Connexion::class)->parMotDePasse($tel, $mdp, '10.0.0.2');
        } catch (ConnexionRefusee $e) {
            return $e->getMessage();
        }
    };

    expect($essayer($u->telephone, 'faux'))->toBe('Téléphone ou mot de passe incorrect.')
        ->and($essayer('0100000000', 'un-bon-mot-de-passe'))->toBe('Téléphone ou mot de passe incorrect.');
});

it('limite les tentatives : après 5 échecs, même le bon mot de passe est refusé', function () {
    $u = compteAvecMotDePasse();
    $service = app(Connexion::class);

    foreach (range(1, 5) as $i) {
        expect(fn () => $service->parMotDePasse($u->telephone, 'faux', '10.0.0.3'))->toThrow(ConnexionRefusee::class, 'Téléphone ou mot de passe incorrect.');
    }

    expect(fn () => $service->parMotDePasse($u->telephone, 'un-bon-mot-de-passe', '10.0.0.3'))->toThrow(ConnexionRefusee::class, 'Trop d\'essais. Réessayez dans');
    expect(auth()->check())->toBeFalse();

    $this->travel(6)->minutes(); // la pause est terminée
    expect($service->parMotDePasse($u->telephone, 'un-bon-mot-de-passe', '10.0.0.3')->id)->toBe($u->id);
});

it('limite aussi les essais par numéro, même si l\'attaquant change d\'adresse à chaque fois', function () {
    $u = compteAvecMotDePasse();
    $service = app(Connexion::class);

    foreach (range(1, 20) as $i) {
        expect(fn () => $service->parMotDePasse($u->telephone, 'faux', "10.9.0.{$i}"))->toThrow(ConnexionRefusee::class, 'Téléphone ou mot de passe incorrect.');
    }

    expect(fn () => $service->parMotDePasse($u->telephone, 'un-bon-mot-de-passe', '10.9.9.9'))->toThrow(ConnexionRefusee::class, 'Trop d\'essais');
    expect(auth()->check())->toBeFalse();
});

it('refuse un compte suspendu, et un compte sans mot de passe', function () {
    $suspendu = compteAvecMotDePasse();
    $suspendu->update(['statut' => 'suspendu']);
    $sansMotDePasse = creerUtilisateur(Role::Client);
    $sansMotDePasse->forceFill(['mot_de_passe' => null])->save();

    expect(fn () => app(Connexion::class)->parMotDePasse($suspendu->telephone, 'un-bon-mot-de-passe', '10.0.0.4'))->toThrow(ConnexionRefusee::class, 'suspendu');
    expect(fn () => app(Connexion::class)->parMotDePasse($sansMotDePasse->telephone, '', '10.0.0.4'))->toThrow(ConnexionRefusee::class);
});

it('l\'écran de connexion fonctionne et envoie le gérant vers son calendrier', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $gerant->update(['mot_de_passe' => 'un-bon-mot-de-passe']);

    Livewire::test(ComposantConnexion::class)
        ->set('telephone', $gerant->telephone)->set('motDePasse', 'faux')->call('seConnecter')
        ->assertSee('Téléphone ou mot de passe incorrect.')
        ->set('motDePasse', 'un-bon-mot-de-passe')->call('seConnecter')
        ->assertRedirect(route('gerant.calendrier'));
    expect(auth()->id())->toBe($gerant->id);
});

// --- SMS désactivé par défaut : repli par e-mail --------------------------------------------------

it('le SMS est désactivé par défaut et l\'écran ne le propose pas', function () {
    expect(app(Connexion::class)->smsActif())->toBeFalse();

    Livewire::test(ComposantConnexion::class)
        ->assertDontSee('Envoyer le code')->assertSee('Recevoir un lien par e-mail')->assertSee('Mot de passe');
});

it('le SMS reste désactivé tant qu\'aucun fournisseur n\'est réellement configuré, même si le réglage est activé', function () {
    Parametres::definir('sms.actif', '1');

    expect(app(Connexion::class)->smsActif())->toBeFalse();
    expect(fn () => app(Connexion::class)->envoyerCodeSms('0701020304', '10.0.0.5'))->toThrow(ConnexionRefusee::class, 'pas disponible');
});

it('avec le SMS désactivé, aucun code ne permet d\'entrer (pas de code fixe)', function () {
    $u = compteAvecMotDePasse();

    foreach (['123456', '000000', '111111', '654321', '999999', '1234'] as $code) {
        expect(fn () => app(Connexion::class)->verifierCodeSms($u->telephone, $code, '10.0.0.6'))->toThrow(ConnexionRefusee::class);
        RateLimiter::clear('connexion:'.$u->telephone.'|10.0.0.6');
    }
    expect(auth()->check())->toBeFalse();
});

it('le code source ne contient aucun code de connexion fixe', function () {
    $fichiers = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())))
        ->filter(fn ($f) => $f->isFile() && $f->getExtension() === 'php');

    foreach ($fichiers as $fichier) {
        expect(file_get_contents($fichier->getPathname()))->not->toMatch('/[\'"](123456|000000|111111|0000|1234)[\'"]/', $fichier->getPathname());
    }
});

it('le lien de connexion par e-mail fonctionne une seule fois et expire', function () {
    Mail::fake();
    $u = compteAvecMotDePasse();

    app(Connexion::class)->envoyerLienEmail($u->telephone, '10.0.0.7');

    Mail::assertQueued(LienConnexion::class, function (LienConnexion $mail) use ($u) {
        $GLOBALS['lien_test'] = $mail->lien;

        return $mail->nom === $u->nom && $mail->minutes === 15;
    });
    $jeton = basename($GLOBALS['lien_test']);

    // L'ouverture du lien ne connecte pas (seul le bouton le fait) et la base ne garde que l'empreinte.
    Livewire::test(ConnexionParLien::class, ['jeton' => $jeton])->assertSee('Me connecter');
    expect(auth()->check())->toBeFalse();
    expect(DB::table('jeton_connexion')->value('empreinte'))->not->toContain($jeton);

    Livewire::test(ConnexionParLien::class, ['jeton' => $jeton])->call('entrer')->assertRedirect(route('accueil'));
    expect(auth()->id())->toBe($u->id);

    auth()->logout();
    Livewire::test(ConnexionParLien::class, ['jeton' => $jeton])->assertSee('Lien expiré')->call('entrer')->assertSee('n\'est plus valable');
    expect(auth()->check())->toBeFalse();
});

it('un lien de connexion expire après 15 minutes', function () {
    Mail::fake();
    $u = compteAvecMotDePasse();
    app(Connexion::class)->envoyerLienEmail($u->telephone, '10.0.0.8');
    Mail::assertQueued(LienConnexion::class, function ($mail) {
        $GLOBALS['lien_test'] = $mail->lien;

        return true;
    });

    $this->travel(16)->minutes();

    Livewire::test(ConnexionParLien::class, ['jeton' => basename($GLOBALS['lien_test'])])->assertSee('Lien expiré');
});

it('la demande de lien répond pareil pour un numéro inconnu, sans envoyer d\'e-mail', function () {
    Mail::fake();

    app(Connexion::class)->envoyerLienEmail('0100000000', '10.0.0.9');

    Mail::assertNothingQueued();
    Livewire::test(ComposantConnexion::class)->set('telephone', '0100000000')->call('envoyerLien')->assertSee('Si ce numéro a un compte');
});

it('limite les demandes de lien', function () {
    Mail::fake();
    $u = compteAvecMotDePasse();

    foreach (range(1, 2) as $i) {
        app(Connexion::class)->envoyerLienEmail($u->telephone, '10.0.0.10');
    }

    expect(fn () => app(Connexion::class)->envoyerLienEmail($u->telephone, '10.0.0.10'))->toThrow(ConnexionRefusee::class, 'Trop de demandes');
});

// --- Code SMS (quand un fournisseur est configuré et le SMS activé) ------------------------------------

it('connecte par code SMS : code aléatoire, à usage unique, qui expire et dont les essais sont limités', function () {
    $faux = fauxFournisseurSms();
    Parametres::definir('sms.actif', '1');
    $u = compteAvecMotDePasse();
    $service = app(Connexion::class);

    expect($service->smsActif())->toBeTrue();
    $service->envoyerCodeSms($u->telephone, '10.0.1.1');
    $code = $faux->dernierCode();

    expect($code)->toMatch('/^\d{6}$/');
    expect(DB::table('jeton_connexion')->where('type', 'code_sms')->value('empreinte'))->not->toContain($code); // jamais en clair

    expect(fn () => $service->verifierCodeSms($u->telephone, $code === '000001' ? '000002' : '000001', '10.0.1.1'))->toThrow(ConnexionRefusee::class);
    expect($service->verifierCodeSms($u->telephone, $code, '10.0.1.1')->id)->toBe($u->id);

    auth()->logout();
    expect(fn () => $service->verifierCodeSms($u->telephone, $code, '10.0.1.1'))->toThrow(ConnexionRefusee::class); // usage unique

    // Expiration
    RateLimiter::clear('sms:'.$u->telephone.'|10.0.1.1');
    $service->envoyerCodeSms($u->telephone, '10.0.1.1');
    $nouveau = $faux->dernierCode();
    $this->travel(11)->minutes();
    expect(fn () => $service->verifierCodeSms($u->telephone, $nouveau, '10.0.1.1'))->toThrow(ConnexionRefusee::class);
});

it('les codes SMS sont tirés au hasard et le code précédent est invalidé par le suivant', function () {
    $faux = fauxFournisseurSms();
    Parametres::definir('sms.actif', '1');
    $codes = [];

    foreach (range(1, 12) as $i) {
        $u = compteAvecMotDePasse();
        app(Connexion::class)->envoyerCodeSms($u->telephone, '10.0.2.'.$i);
        $codes[] = $faux->dernierCode();
    }
    expect(count(array_unique($codes)))->toBeGreaterThan(8);

    $u = compteAvecMotDePasse();
    $service = app(Connexion::class);
    $service->envoyerCodeSms($u->telephone, '10.0.3.1');
    $ancien = $faux->dernierCode();
    $this->travel(1)->minutes();
    RateLimiter::clear('sms:'.$u->telephone.'|10.0.3.1');
    $service->envoyerCodeSms($u->telephone, '10.0.3.1');
    $nouveau = $faux->dernierCode();

    if ($ancien !== $nouveau) {
        expect(fn () => $service->verifierCodeSms($u->telephone, $ancien, '10.0.3.1'))->toThrow(ConnexionRefusee::class);
    }
    expect($service->verifierCodeSms($u->telephone, $nouveau, '10.0.3.1')->id)->toBe($u->id);
});

it('bloque un code SMS après 5 mauvais essais', function () {
    $faux = fauxFournisseurSms();
    Parametres::definir('sms.actif', '1');
    $u = compteAvecMotDePasse();
    $service = app(Connexion::class);
    $service->envoyerCodeSms($u->telephone, '10.0.4.1');
    $bon = $faux->dernierCode();
    $faux1 = $bon === '111111' ? '222222' : '111111';

    foreach (range(1, 5) as $i) {
        expect(fn () => $service->verifierCodeSms($u->telephone, $faux1, '10.0.4.'.$i))->toThrow(ConnexionRefusee::class);
    }

    // Le bon code ne marche plus : trop d'essais sur ce code, depuis une autre adresse aussi.
    expect(fn () => $service->verifierCodeSms($u->telephone, $bon, '10.0.4.99'))->toThrow(ConnexionRefusee::class);
});

// --- Inscription -----------------------------------------------------------------------------------

it('inscrit un client avec un e-mail obligatoire, un mot de passe haché, et le connecte', function () {
    Livewire::test(Inscription::class)
        ->set('nom', 'Awa Koné')->set('telephone', '+225 07 01 02 03 04')->set('email', 'Awa@Exemple.com')->set('motDePasse', 'motdepasse-solide')->set('profil', 'client')
        ->call('inscrire')->assertRedirect(route('accueil'));

    $u = Utilisateur::where('telephone', '0701020304')->firstOrFail();
    expect($u->email)->toBe('awa@exemple.com')
        ->and($u->aRole(Role::Client))->toBeTrue()->and($u->aRole(Role::Gerant))->toBeFalse()
        ->and(DB::table('utilisateur')->where('id', $u->id)->value('mot_de_passe'))->not->toBe('motdepasse-solide')
        ->and(auth()->id())->toBe($u->id);
});

it('inscrit un gérant et le dirige vers l\'ajout de sa résidence', function () {
    Livewire::test(Inscription::class)
        ->set('nom', 'Kouadio')->set('telephone', '0555555555')->set('email', 'k@exemple.com')->set('motDePasse', 'motdepasse-solide')->set('profil', 'gerant')
        ->call('inscrire')->assertRedirect(route('gerant.residence.nouvelle'));

    expect(Utilisateur::where('telephone', '0555555555')->first()->aRole(Role::Gerant))->toBeTrue();
});

it('refuse une inscription sans e-mail, avec un mot de passe court, un numéro invalide ou déjà pris', function () {
    $existant = compteAvecMotDePasse();

    Livewire::test(Inscription::class)
        ->set('nom', 'X')->set('telephone', '123')->set('email', '')->set('motDePasse', 'court')->call('inscrire')
        ->assertHasErrors(['telephone', 'email', 'motDePasse']);

    Livewire::test(Inscription::class)
        ->set('nom', 'X')->set('telephone', $existant->telephone)->set('email', 'autre@exemple.com')->set('motDePasse', 'motdepasse-solide')->call('inscrire')
        ->assertSee('Ce numéro de téléphone a déjà un compte. Connectez-vous.');

    Livewire::test(Inscription::class)
        ->set('nom', 'X')->set('telephone', '0600000001')->set('email', $existant->email)->set('motDePasse', 'motdepasse-solide')->call('inscrire')
        ->assertSee('a déjà un compte');
});

it('la session est configurée de façon sûre', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax')
        ->and(config('session.encrypt'))->toBeTrue()
        ->and(config('session.driver'))->toBe('array'); // « database » en dehors des tests (phpunit.xml)
});
