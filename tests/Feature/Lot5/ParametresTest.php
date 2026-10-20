<?php

use App\Noyau\Enums\Role;
use App\Noyau\Exceptions\ConnexionRefusee;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Connexion;
use App\Paiement\Services\ReglagesPaiement;
use App\Parametres\Livewire\EspaceParametres;
use App\Parametres\Models\ParametreHistorique;
use App\Parametres\Services\Parametres;
use App\Parametres\Services\Reglages;
use App\Parametres\Services\TestsConnexion;
use App\Sejours\Services\ReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function superAdmin(): Utilisateur
{
    $u = creerUtilisateur(Role::SuperAdmin);
    $u->update(['mot_de_passe' => 'mot-de-passe-secret-1']);

    return $u->fresh();
}

function page(Utilisateur $u, string $rubrique)
{
    return Livewire::actingAs($u)->test(EspaceParametres::class, ['rubrique' => $rubrique]);
}

it('seul le super-administrateur accède aux Paramètres ; tout autre rôle est refusé côté serveur', function () {
    foreach (['', '/general', '/paiement', '/securite', '/tests', '/historique'] as $fin) {
        foreach ([Role::Client, Role::Gerant, Role::Personnel, Role::Admin] as $role) {
            $this->actingAs(creerUtilisateur($role))->get('/admin/reglages'.$fin)->assertForbidden();
        }
        $this->actingAs(superAdmin())->get('/admin/reglages'.$fin)->assertOk();
    }
    $this->actingAs(superAdmin())->get('/admin/reglages/inexistante')->assertNotFound();
    // Même en appelant directement le composant : refusé.
    expect(fn () => Livewire::actingAs(creerUtilisateur(Role::Admin))->test(EspaceParametres::class)->assertForbidden())->not->toThrow(Throwable::class);
});

it('enregistre la rubrique Réservation et applique le changement tout de suite, sans redémarrage', function () {
    $su = superAdmin();
    page($su, 'reservation')->set('valeurs.reservation__duree_blocage_minutes', '90')->set('valeurs.annulation__delai_gratuit_heures', '48')->call('enregistrer')->assertSee('Réglages enregistrés');

    expect(Parametres::obtenir('reservation.duree_blocage_minutes'))->toBe('90')
        ->and(ReservationService::dureeGardeMinutes())->toBe(90)
        ->and(ReglagesPaiement::delaiAnnulationGratuiteHeures())->toBe(48);
});

it('refuse les valeurs impossibles avec un message clair, sans rien enregistrer', function () {
    $su = superAdmin();
    configurerGeniusPay('sandbox', 10);
    Parametres::definir('paiement.frais_pourcentage', '2');

    page($su, 'reservation')->set('valeurs.reservation__duree_blocage_minutes', '0')->call('enregistrer')->assertSee('entre 15 et 1440');
    page($su, 'reservation')->set('valeurs.reservation__duree_blocage_minutes', 'abc')->call('enregistrer')->assertSee('nombre entier');
    page($su, 'reservation')->set('valeurs.reservation__acompte_defaut_pourcentage', '5')->call('enregistrer')->assertSee('ne peut pas être inférieur à 12');
    page($su, 'reservation')->set('valeurs.annulation__delai_gratuit_heures', '-3')->call('enregistrer')->assertSee('nombre entier');
    page($su, 'reservation')->set('valeurs.annulation__heure_arrivee', '25:99')->call('enregistrer')->assertSee('format 14:00');
    page($su, 'paiement')->set('valeurs.commission__pourcentage', '-5')->set('motDePasse', 'mot-de-passe-secret-1')->call('enregistrer')->assertSee('pas de valeur négative');
    page($su, 'paiement')->set('valeurs.commission__pourcentage', '101')->set('motDePasse', 'mot-de-passe-secret-1')->call('enregistrer')->assertSee('entre 0 et 100');
    page($su, 'securite')->set('valeurs.securite__session_minutes', '5')->set('motDePasse', 'mot-de-passe-secret-1')->call('enregistrer')->assertSee('entre 15 et 1440');
    page($su, 'securite')->set('valeurs.securite__connexion_essais_max', '2')->set('motDePasse', 'mot-de-passe-secret-1')->call('enregistrer')->assertSee('entre 3 et 10');
    page($su, 'notifications')->set('valeurs.email__port', '70000')->call('enregistrer')->assertSee('entre 1 et 65535');
    page($su, 'notifications')->set('valeurs.contact__email', 'pas-une-adresse')->call('enregistrer');
    page($su, 'general')->set('valeurs.contact__email', 'pas-une-adresse')->call('enregistrer')->assertSee('adresse e-mail n\'est pas valide');
    page($su, 'general')->set('valeurs.general__nom', '')->call('enregistrer')->assertSee('ne peut pas être vide');
    page($su, 'mise_en_avant')->set('valeurs.mise_en_avant__offres', [['nom' => 'Semaine', 'jours' => '7', 'prix' => '100']])->call('enregistrer')->assertSee('au moins 200 FCFA');
    page($su, 'mise_en_avant')->set('valeurs.mise_en_avant__offres', [['nom' => 'Semaine', 'jours' => '0', 'prix' => '10000']])->call('enregistrer')->assertSee('entre 1 et 365');

    expect(Parametres::obtenir('reservation.duree_blocage_minutes'))->toBeNull()->and(Parametres::obtenir('commission.pourcentage'))->toBe('10')->and(Parametres::obtenir('mise_en_avant.offres'))->toBeNull();
});

it('un acompte par défaut sous le plancher est refusé, et la commission ne peut pas monter au-dessus de l\'acompte par défaut', function () {
    $su = superAdmin();
    configurerGeniusPay('sandbox', 10);
    Parametres::definir('paiement.frais_pourcentage', '2');
    Parametres::definir('reservation.acompte_defaut_pourcentage', '20');

    // commission 25 % + frais 2 % = plancher 27 % > acompte par défaut 20 % : refusé.
    page($su, 'paiement')->set('valeurs.commission__pourcentage', '25')->set('motDePasse', 'mot-de-passe-secret-1')->call('enregistrer')->assertSee('acompte minimum devient 27');
    expect(Parametres::obtenir('commission.pourcentage'))->toBe('10');
    page($su, 'reservation')->set('valeurs.reservation__acompte_defaut_pourcentage', '12')->call('enregistrer')->assertSee('Réglages enregistrés');
});

it('les réglages sensibles exigent de ressaisir le mot de passe ; un mauvais mot de passe ne modifie rien', function () {
    $su = superAdmin();
    page($su, 'paiement')->set('valeurs.commission__pourcentage', '12')->call('enregistrer')->assertSee('Mot de passe incorrect');
    page($su, 'paiement')->set('valeurs.commission__pourcentage', '12')->set('motDePasse', 'mauvais')->call('enregistrer')->assertSee('Mot de passe incorrect');
    expect(Parametres::obtenir('commission.pourcentage'))->toBeNull();

    page($su, 'paiement')->set('valeurs.commission__pourcentage', '12')->set('motDePasse', 'mot-de-passe-secret-1')->call('enregistrer')->assertSee('Réglages enregistrés');
    expect(Parametres::obtenir('commission.pourcentage'))->toBe('12');

    // Une rubrique non sensible n'exige pas le mot de passe.
    page($su, 'reservation')->set('valeurs.reservation__duree_blocage_minutes', '100')->call('enregistrer')->assertSee('Réglages enregistrés');
    // Les essais de mot de passe sont limités.
    foreach (range(1, 6) as $i) {
        $essai = page($su, 'securite')->set('valeurs.securite__session_minutes', '200')->set('motDePasse', 'faux')->call('enregistrer');
    }
    $essai->assertSee('Trop d\'essais');
    expect(Parametres::obtenir('securite.session_minutes'))->toBeNull();
});

it('un secret n\'est jamais renvoyé au navigateur, ni écrit dans l\'historique ou les journaux', function () {
    $su = superAdmin();
    $secret = 'sk_sandbox_TRES-SECRET-0987654321';
    $composant = page($su, 'paiement')->call('remplacer', 'geniuspay.sandbox.cle_secrete')
        ->set('secrets.geniuspay__sandbox__cle_secrete', $secret)->set('motDePasse', 'mot-de-passe-secret-1')->call('enregistrer')->assertSee('Réglages enregistrés');

    // Chiffré en base ; valeur brute illisible.
    expect(DB::table('parametre')->where('cle', 'geniuspay.sandbox.cle_secrete')->value('valeur'))->not->toContain('TRES-SECRET');
    expect(Parametres::obtenir('geniuspay.sandbox.cle_secrete'))->toBe($secret);
    // Ni dans la page HTML ni dans l'état envoyé au navigateur (après enregistrement, après rechargement).
    $composant->assertDontSee($secret)->assertSee('Enregistré');
    $html = $this->actingAs($su)->get('/admin/reglages/paiement')->getContent();
    expect($html)->not->toContain('TRES-SECRET')->not->toContain('0987654321');
    expect(json_encode($composant->snapshot))->not->toContain('TRES-SECRET');
    // Ni dans l'historique, ni dans le journal d'audit.
    expect(json_encode(DB::table('parametre_historique')->get()))->not->toContain('TRES-SECRET');
    expect(json_encode(DB::table('journal_audit')->get()))->not->toContain('TRES-SECRET');
    $h = ParametreHistorique::latest('id')->first();
    expect($h->est_secret)->toBeTrue()->and($h->ancienne_valeur)->toBeNull()->and($h->nouvelle_valeur)->toBeNull();
    // Un secret laissé vide reste inchangé.
    page($su, 'paiement')->set('motDePasse', 'mot-de-passe-secret-1')->call('enregistrer');
    expect(Parametres::obtenir('geniuspay.sandbox.cle_secrete'))->toBe($secret);
});

it('l\'historique est complet (qui, quand, avant, après) et ne peut ni se modifier ni se supprimer', function () {
    $su = superAdmin();
    page($su, 'reservation')->set('valeurs.reservation__duree_blocage_minutes', '90')->call('enregistrer');
    page($su, 'reservation')->set('valeurs.reservation__duree_blocage_minutes', '60')->call('enregistrer');

    $lignes = ParametreHistorique::with('parametre')->get();
    expect($lignes)->toHaveCount(2)->and($lignes[0]->ancienne_valeur)->toBeNull()->and($lignes[0]->nouvelle_valeur)->toBe('90')
        ->and($lignes[1]->ancienne_valeur)->toBe('90')->and($lignes[1]->nouvelle_valeur)->toBe('60')
        ->and($lignes[1]->modifie_par)->toBe($su->id)->and($lignes[1]->created_at)->not->toBeNull();
    page($su, 'historique')->assertSee('reservation.duree_blocage_minutes')->assertSee($su->nom)->assertSee('60');

    expect(codeRefusBase(fn () => DB::table('parametre_historique')->update(['nouvelle_valeur' => 'triche'])))->not->toBeNull();
    expect(codeRefusBase(fn () => DB::table('parametre_historique')->delete()))->not->toBeNull();
    expect(codeRefusBase(fn () => DB::statement('TRUNCATE parametre_historique')))->not->toBeNull();
    // Un réglage inchangé n'ajoute rien.
    page($su, 'reservation')->set('valeurs.reservation__duree_blocage_minutes', '60')->call('enregistrer')->assertSee('Aucun changement');
    expect(ParametreHistorique::count())->toBe(2);
});

it('changer la commission ne touche pas aux réservations existantes et s\'applique aux nouvelles', function () {
    $su = superAdmin();
    configurerGeniusPay('sandbox', 10);
    $faux = fauxGeniusPay();
    $avant = reservationAPayer();
    $enAttenteAvantChangement = reservationAPayer();
    payerEtConfirmer($this, $faux, $avant);

    page($su, 'paiement')->set('valeurs.commission__pourcentage', '15')->set('motDePasse', 'mot-de-passe-secret-1')->call('enregistrer')->assertSee('Réglages enregistrés');

    // L'ancienne réservation confirmée garde 10 %, et celle créée avant le changement mais payée après le garde aussi.
    expect((float) $avant->fresh()->commission_pourcentage)->toBe(10.0)->and((float) $enAttenteAvantChangement->fresh()->commission_pourcentage)->toBe(10.0);
    payerEtConfirmer($this, $faux, $enAttenteAvantChangement);
    expect((float) $enAttenteAvantChangement->fresh()->commission_pourcentage)->toBe(10.0);
    // Une réservation créée après le changement prend 15 %.
    $apres = reservationAPayer();
    expect((float) $apres->commission_pourcentage)->toBe(15.0);
    payerEtConfirmer($this, $faux, $apres);
    expect((float) $apres->fresh()->commission_pourcentage)->toBe(15.0);
});

it('la durée de blocage d\'une réservation déjà créée ne change pas quand le réglage change', function () {
    $su = superAdmin();
    $r = reservationAPayer();
    $expiration = $r->expire_le;
    page($su, 'reservation')->set('valeurs.reservation__duree_blocage_minutes', '30')->call('enregistrer');

    expect($r->fresh()->expire_le->equalTo($expiration))->toBeTrue()->and(reservationAPayer()->expire_le->lessThan($expiration))->toBeTrue();
});

it('la durée de session et les tentatives de connexion se règlent dans les Paramètres, sans redémarrage', function () {
    $su = superAdmin();
    page($su, 'securite')->set('valeurs.securite__session_minutes', '45')->set('valeurs.securite__connexion_essais_max', '3')->set('valeurs.securite__connexion_pause_minutes', '10')->set('motDePasse', 'mot-de-passe-secret-1')->call('enregistrer')->assertSee('Réglages enregistrés');

    $this->get('/a-propos')->assertOk();
    expect(config('session.lifetime'))->toBe(45)->and(Connexion::essaisMax())->toBe(3)->and(Connexion::pauseSecondes())->toBe(600);

    // 3 échecs suffisent maintenant pour déclencher la pause.
    $cible = creerUtilisateur(Role::Client);
    foreach (range(1, 3) as $i) {
        try {
            app(Connexion::class)->parMotDePasse($cible->telephone, 'faux-mot-de-passe', '10.0.0.9');
        } catch (ConnexionRefusee) {
        }
    }
    expect(fn () => app(Connexion::class)->parMotDePasse($cible->telephone, 'faux-mot-de-passe', '10.0.0.9'))->toThrow(ConnexionRefusee::class, 'Trop d\'essais');
});

it('le SMS ne peut pas être activé sans fournisseur ; il est désactivé par défaut', function () {
    $su = superAdmin();
    expect(Reglages::valeur('sms.actif'))->toBe('0');
    page($su, 'notifications')->set('valeurs.sms__actif', '1')->call('enregistrer')->assertSee('Aucun fournisseur SMS');
    expect(Parametres::obtenir('sms.actif'))->toBeNull();
});

it('le mode réel exige les trois clés réelles', function () {
    $su = superAdmin();
    configurerGeniusPay('sandbox');
    page($su, 'paiement')->set('valeurs.geniuspay__mode', 'live')->set('motDePasse', 'mot-de-passe-secret-1')->call('enregistrer')->assertSee('trois clés réelles');
    expect(Parametres::obtenir('geniuspay.mode'))->toBe('sandbox');
});

it('test de connexion e-mail : réussite, échec, et jamais de secret dans le résultat', function () {
    $su = superAdmin();
    Mail::fake();
    $tests = app(TestsConnexion::class);

    expect($tests->email($su))->toMatchArray(['ok' => false])->and($tests->email($su)['message'])->toContain('Complétez d\'abord');

    Parametres::definir('email.hote', 'smtp.exemple.ci');
    Parametres::definir('email.mot_de_passe', 'MOT-DE-PASSE-SMTP-123', null, true);
    $ok = $tests->email($su);
    expect($ok['ok'])->toBeTrue()->and($ok['message'])->toContain($su->email);

    Mail::shouldReceive('to')->andThrow(new RuntimeException('Authentication failed for MOT-DE-PASSE-SMTP-123 on smtp.exemple.ci'));
    $ko = $tests->email($su);
    expect($ko['ok'])->toBeFalse()->and($ko['message'])->not->toContain('MOT-DE-PASSE-SMTP-123')->and($ko['message'])->toContain('***');
});

it('test de connexion paiement : réussite, clés refusées, fournisseur injoignable, réglage manquant', function () {
    $su = superAdmin();
    $tests = app(TestsConnexion::class);
    expect($tests->paiement($su)['message'])->toContain('Complétez d\'abord');

    configurerGeniusPay('sandbox');
    $faux = fauxGeniusPay();
    $ok = $tests->paiement($su);
    expect($ok['ok'])->toBeTrue()->and($faux->appelsSolde)->toBe(1)->and($ok['message'])->not->toContain(CLE_SECRETE_TEST);

    $faux->statutSolde = 401;
    expect($tests->paiement($su))->toMatchArray(['ok' => false])->and($tests->paiement($su)['message'])->toContain('refusé les clés');
    $faux->statutSolde = 503;
    expect($tests->paiement($su)['message'])->toContain('ne répond pas');

    foreach (DB::table('journal_audit')->where('action', 'reglages.test_connexion')->get() as $l) {
        expect($l->details)->not->toContain(CLE_SECRETE_TEST);
    }
});

it('les tests de connexion sont limités à 10 par heure', function () {
    $su = superAdmin();
    configurerGeniusPay('sandbox');
    fauxGeniusPay();
    $tests = app(TestsConnexion::class);
    foreach (range(1, 10) as $i) {
        $tests->paiement($su);
    }
    expect($tests->paiement($su)['message'])->toContain('Trop de tests');
});

it('aucun réglage fonctionnel ne dépend d\'un fichier .env en dehors de la base et de la clé maître', function () {
    // 1. Le code de l'application ne lit jamais l'environnement.
    $fautes = [];
    foreach (['app', 'routes', 'database/seeders'] as $dossier) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dossier))) as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.php') && preg_match('/\b(env|getenv)\(|\$_ENV|\$_SERVER\[\'[A-Z_]+\'\]/', file_get_contents($f->getPathname()))) {
                $fautes[] = str_replace(base_path().'/', '', $f->getPathname());
            }
        }
    }
    expect($fautes)->toBe([]);

    // 2. .env.example ne contient que les valeurs permises (APP_ENV, APP_DEBUG, APP_KEY, DB_URL).
    $cles = collect(file(base_path('.env.example'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))->reject(fn ($l) => str_starts_with($l, '#'))->map(fn ($l) => explode('=', $l)[0])->sort()->values()->all();
    expect($cles)->toBe(['APP_DEBUG', 'APP_ENV', 'APP_KEY', 'DB_URL']);
    expect(file_get_contents(base_path('.env.example')))->not->toMatch('/(sk_|pk_|whsec_)[A-Za-z0-9]/');
});

it('un visiteur non connecté est renvoyé vers la connexion', function () {
    $this->get('/admin/reglages')->assertRedirect(route('connexion'));
    $this->get('/admin/mises-en-avant')->assertRedirect(route('connexion'));
});
