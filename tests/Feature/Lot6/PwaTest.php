<?php

use App\Noyau\Enums\Role;
use App\Paiement\Exceptions\PaiementImpossible;
use App\Paiement\Livewire\PagePaiement;
use App\Paiement\Models\Paiement;
use App\Paiement\Services\PaiementService;
use App\Parametres\Services\Parametres;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Livewire\Recherche;
use App\Sejours\Models\Photo;
use App\Sejours\Models\Reservation;
use App\Sejours\Services\Photos;
use App\Sejours\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => configurerGeniusPay());

it('le manifeste rend l\'application installable : nom, démarrage, icônes réelles aux bonnes tailles', function () {
    Parametres::definir('general.nom', 'Ma Plateforme Séjours');
    $r = $this->get('/manifest.webmanifest')->assertOk();
    expect($r->headers->get('Content-Type'))->toContain('application/manifest+json');
    $m = $r->json();

    expect($m['name'])->toBe('Ma Plateforme Séjours')->and($m['short_name'])->toBe('Ma')->and($m['display'])->toBe('standalone')
        ->and($m['start_url'])->toStartWith('/')->and($m['scope'])->toBe('/')->and($m['lang'])->toBe('fr')->and($m['theme_color'])->toBe('#0f766e');
    $tailles = [];
    foreach ($m['icons'] as $icone) {
        $fichier = public_path(ltrim($icone['src'], '/'));
        expect(file_exists($fichier))->toBeTrue();
        [$l, $h, $type] = getimagesize($fichier);
        expect($type)->toBe(IMAGETYPE_PNG)->and("{$l}x{$h}")->toBe($icone['sizes']);
        $tailles[$icone['purpose']][] = $l;
    }
    expect($tailles['any'])->toContain(192, 512)->and($tailles['maskable'])->toContain(512);
    expect(getimagesize(public_path('icons/apple-touch-icon.png'))[0])->toBe(180);
});

it('toutes les pages annoncent le manifeste, l\'icône iPhone et la couleur de l\'application', function () {
    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->toContain('rel="manifest"')->toContain('/manifest.webmanifest')->toContain('apple-touch-icon')->toContain('name="theme-color"')->toContain('apple-mobile-web-app-capable');
});

it('le service worker est servi à la racine, jamais mis en cache, avec un numéro de version qui change à chaque mise en ligne', function () {
    $r = $this->get('/sw.js')->assertOk();
    expect($r->headers->get('Content-Type'))->toContain('javascript')->and($r->headers->get('Service-Worker-Allowed'))->toBe('/')->and($r->headers->get('Cache-Control'))->toContain('no-cache');
    $v1 = $r->getContent();
    expect($v1)->not->toContain('__VERSION__')->and($v1)->toMatch("/const VERSION = '[0-9a-f]{12}';/");
    expect($this->get('/sw.js')->getContent())->toBe($v1); // stable tant que rien ne change

    // Le site compilé peut être absent (avant « npm run build ») : on le simule le temps de l'essai, puis on remet les lieux comme avant.
    $manifest = public_path('build/manifest.json');
    $dossierCree = ! is_dir(dirname($manifest)) && mkdir(dirname($manifest), 0775, true);
    $existait = is_file($manifest);
    $avant = $existait ? file_get_contents($manifest) : '{}';
    try {
        file_put_contents($manifest, $avant);
        $v1 = $this->get('/sw.js')->getContent();
        file_put_contents($manifest, $avant."\n");
        expect($this->get('/sw.js')->getContent())->not->toBe($v1); // nouveau site compilé = nouvelle version
    } finally {
        $existait ? file_put_contents($manifest, $avant) : @unlink($manifest);
        if ($dossierCree) {
            @rmdir(dirname($manifest));
        }
    }
});

it('la page « hors connexion » existe sans aucune donnée personnelle ni jeton', function () {
    $client = creerUtilisateur(Role::Client);
    $html = $this->actingAs($client)->get('/hors-connexion')->assertOk()->assertSee('hors connexion', false)->getContent();
    expect($html)->not->toContain($client->nom)->not->toContain($client->email)->not->toContain('name="csrf-token"')->not->toContain('_token')->not->toContain('wire:id');
});

it('le serveur n\'autorise l\'enregistrement hors connexion que des pages publiques (visiteur) et de « Mes réservations » (client)', function () {
    $unite = creerLogement();

    // Visiteur : pages publiques marquées « public ».
    foreach (['/', '/faq', '/a-propos', '/conditions', '/logement/'.$unite->id] as $url) {
        $r = $this->get($url)->assertOk();
        expect($r->headers->get('X-Gesimo-Cache'))->toBe('public');
    }
    // Pages avec jeton ou formulaire de compte : jamais.
    foreach (['/connexion', '/inscription'] as $url) {
        $r = $this->get($url)->assertOk();
        expect($r->headers->get('X-Gesimo-Cache'))->toBeNull()->and($r->headers->get('Cache-Control'))->toContain('no-store');
    }

    // Client : « Mes réservations » et le détail sont marqués « prive » ; paiement, reçu, profil ne le sont jamais.
    $faux = fauxGeniusPay();
    $r = reservationAPayer();
    payerEtConfirmer($this, $faux, $r);
    $client = $r->client;
    foreach (['/mes-reservations', '/reservation/'.$r->id, '/faq'] as $url) {
        $rep = $this->actingAs($client)->get($url)->assertOk();
        expect($rep->headers->get('X-Gesimo-Cache'))->toBe('prive');
    }
    foreach (['/reservation/'.$r->id.'/recu', '/reservation/'.$r->id.'/confirmation', '/compte', '/compte/profil', '/reservation/'.$r->id.'/annuler'] as $url) {
        $rep = $this->actingAs($client)->get($url);
        expect($rep->headers->get('X-Gesimo-Cache'))->toBeNull()->and($rep->headers->get('Cache-Control'))->toContain('no-store');
    }
    $paiement = $r->paiements()->first();
    $rep = $this->actingAs($client)->get(route('paiement.retour', $r));
    expect($rep->headers->get('X-Gesimo-Cache'))->toBeNull();
});

it('un gérant, un membre du personnel ou un admin n\'ont jamais de copie hors connexion (données de tiers)', function () {
    $unite = creerLogement();
    foreach ([Role::Gerant, Role::Personnel, Role::Admin, Role::SuperAdmin] as $role) {
        $u = creerUtilisateur($role);
        foreach (['/', '/faq', '/mes-reservations'] as $url) {
            $rep = $this->actingAs($u)->get($url);
            expect($rep->headers->get('X-Gesimo-Cache'))->toBeNull();
        }
    }
    $gerant = $unite->residence->gerant;
    foreach (['/gerant/arrivees', '/gerant/argent', '/gerant/reservations'] as $url) {
        $rep = $this->actingAs($gerant)->get($url)->assertOk();
        expect($rep->headers->get('X-Gesimo-Cache'))->toBeNull()->and($rep->headers->get('Cache-Control'))->toContain('no-store');
    }
    $rep = $this->actingAs(creerUtilisateur(Role::Admin))->get('/admin/paiements')->assertOk();
    expect($rep->headers->get('Cache-Control'))->toContain('no-store');
});

it('les réponses de l\'application (Livewire) ne sont jamais mises en cache', function () {
    $r = reservationAPayer();
    $rep = $this->actingAs($r->client)->get(route('payer', $r));
    expect($rep->headers->get('Cache-Control'))->toContain('no-store')->and($rep->headers->get('X-Gesimo-Cache'))->toBeNull();
});

it('le service worker se comporte bien : réseau coupé, déconnexion, jamais de données sensibles (essais en faux navigateur)', function () {
    $node = trim((string) shell_exec('command -v node'));
    if ($node === '') {
        $this->markTestSkipped('Node.js n\'est pas installé.');
    }
    $r = Process::path(base_path())->run([$node, 'tests/Js/sw.test.mjs']);
    expect($r->successful())->toBeTrue($r->output().$r->errorOutput());
    expect($r->output())->toContain('Service worker : tous les essais réussissent.')->toContain('après déconnexion, aucune donnée personnelle');
});

it('un double appui sur « Réserver » ne crée qu\'une réservation', function () {
    $unite = creerLogement();
    $client = creerUtilisateur(Role::Client);
    $service = app(ReservationService::class);
    $a = CarbonImmutable::today()->addDays(30);

    $premiere = $service->garderDates($client, $unite, $a, $a->addDays(2), 2);
    $seconde = $service->garderDates($client, $unite, $a, $a->addDays(2), 2);
    expect($seconde->id)->toBe($premiere->id)->and(Reservation::count())->toBe(1);

    // Un autre client, lui, reçoit bien « dates plus libres ».
    expect(fn () => $service->garderDates(creerUtilisateur(Role::Client), $unite, $a, $a->addDays(2), 2))->toThrow(ReservationImpossible::class);
    // Une réservation expirée n'est pas reprise.
    $premiere->update(['statut' => 'expiree']);
    $nouvelle = $service->garderDates($client, $unite, $a, $a->addDays(2), 2);
    expect($nouvelle->id)->not->toBe($premiere->id);
});

it('un paiement en cours n\'est jamais dupliqué : double appui, rechargement, retour en arrière', function () {
    $faux = fauxGeniusPay();
    $r = reservationAPayer();

    $page = Livewire::actingAs($r->client)->test(PagePaiement::class, ['reservation' => $r->id]);
    $page->call('payer')->assertRedirect();
    $page->call('payer')->assertRedirect(); // double appui
    Livewire::actingAs($r->client)->test(PagePaiement::class, ['reservation' => $r->id])->call('payer')->assertRedirect(); // rechargement / retour en arrière
    app(PaiementService::class)->demarrer($r->fresh(), $r->client);

    expect(Paiement::count())->toBe(1)->and($faux->creations)->toHaveCount(1);
    $urls = Paiement::pluck('checkout_url')->unique();
    expect($urls)->toHaveCount(1);
});

it('pendant qu\'un paiement se prépare, un second appui attend et reprend le même paiement ; si le verrou ne se libère pas, message clair', function () {
    $faux = fauxGeniusPay();
    $r = reservationAPayer();

    // Un autre processus prépare déjà le paiement de cette réservation : le second appui ne crée rien et prévient.
    $verrou = Cache::lock('paiement-resa-'.$r->id, 60);
    expect($verrou->get())->toBeTrue();
    PaiementService::$attenteVerrouSecondes = 1;
    expect(fn () => app(PaiementService::class)->demarrer($r->fresh(), $r->client))->toThrow(PaiementImpossible::class, 'déjà en cours de préparation');
    expect(Paiement::count())->toBe(0)->and($faux->creations)->toBeEmpty();
    $verrou->release();
    PaiementService::$attenteVerrouSecondes = 15;

    $p = app(PaiementService::class)->demarrer($r->fresh(), $r->client);
    expect($p->exists)->toBeTrue()->and(Paiement::count())->toBe(1);
})->skip(fn () => ! config('cache.default') || in_array(config('cache.default'), ['null'], true), 'cache sans verrou');

it('un lien de paiement expiré est remplacé par un nouveau, sans toucher à l\'ancien (un paiement tardif reste honoré)', function () {
    $faux = fauxGeniusPay();
    $r = reservationAPayer();
    $ancien = app(PaiementService::class)->demarrer($r, $r->client);
    $ancien->forceFill(['lien_expire_le' => now()->subMinute()])->save();

    $nouveau = app(PaiementService::class)->demarrer($r->fresh(), $r->client);
    expect($nouveau->id)->not->toBe($ancien->id)->and($ancien->fresh()->statut)->toBe('en_attente');
    // Le client paie quand même l'ancien lien : le message signé confirme la réservation, une seule fois.
    $faux->etats[$ancien->reference_geniuspay] = ['status' => 'completed', 'amount' => $ancien->montant];
    recevoirWebhook($this, 'payment.success', $ancien->reference_geniuspay, $ancien->montant)->assertOk();
    expect($r->fresh()->statut->value)->toBe('confirmee');
});

it('les photos ont une version légère pour les listes, supprimée avec la photo', function () {
    Storage::fake('public');
    $unite = creerLogement();
    $photo = Photos::enregistrer(UploadedFile::fake()->image('grande.jpg', 3000, 2000), $unite->residence, $unite);

    $mini = Photo::cheminMiniature($photo->chemin);
    Storage::disk('public')->assertExists($photo->chemin);
    Storage::disk('public')->assertExists($mini);
    expect(getimagesizefromstring(Storage::disk('public')->get($mini))[0])->toBe(480)
        ->and(Storage::disk('public')->size($mini))->toBeLessThan(Storage::disk('public')->size($photo->chemin))
        ->and(getimagesizefromstring(Storage::disk('public')->get($photo->chemin))[0])->toBe(1600);
    expect($photo->urlMiniature())->toContain('_m.jpg');

    // Anciennes photos sans miniature : l'image d'origine sert de repli.
    Storage::disk('public')->delete($mini);
    expect($photo->fresh()->urlMiniature())->toBe($photo->url());

    Photos::supprimer($photo);
    Storage::disk('public')->assertMissing($photo->chemin);
});

it('les images des listes sont chargées en différé, avec leurs dimensions et un texte alternatif', function () {
    Storage::fake('public');
    $unite = creerLogement();
    Photos::enregistrer(UploadedFile::fake()->image('a.jpg', 1200, 800), $unite->residence, $unite);
    $arrivee = CarbonImmutable::today()->addDays(10);

    $html = $this->get('/recherche?arrivee='.$arrivee->toDateString().'&depart='.$arrivee->addDays(2)->toDateString().'&personnes=2')->getContent();
    Livewire::test(Recherche::class)->set('arrivee', $arrivee->toDateString())->set('depart', $arrivee->addDays(2)->toDateString())->call('chercher')
        ->assertSeeHtml('loading="lazy"')->assertSeeHtml('decoding="async"')->assertSeeHtml('width="480"')->assertSeeHtml('alt="Photo de '.$unite->nom.'"')->assertSeeHtml('srcset=');
});
