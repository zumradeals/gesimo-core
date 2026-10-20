<?php

use App\Paiement\Services\Fournisseur\DemandePaiement;
use App\Paiement\Services\Fournisseur\EvenementPaiement;
use App\Paiement\Services\Fournisseur\FournisseurIndisponible;
use App\Paiement\Services\Fournisseur\FournisseurPaiement;
use App\Paiement\Services\Fournisseur\GeniusPay;
use App\Paiement\Services\Fournisseur\RequeteRefusee;
use App\Paiement\Services\Fournisseur\SignatureInvalide;
use App\Parametres\Services\Parametres;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function demandeTest(): DemandePaiement
{
    return new DemandePaiement(16500, 'Réservation 1', 'gesimo-resa-1-1', 'Awa Koné', 'awa@exemple.com', '+2250701020304', 'https://gesimo.test/retour', 'https://gesimo.test/retour', ['reservation_id' => 1]);
}

function fournisseur(): FournisseurPaiement
{
    return app(FournisseurPaiement::class);
}

it('est branché derrière une interface remplaçable', function () {
    expect(fournisseur())->toBeInstanceOf(GeniusPay::class)->and(fournisseur())->toBeInstanceOf(FournisseurPaiement::class);
});

it('n\'est utilisable qu\'une fois les clés du mode choisi saisies dans les Paramètres', function () {
    expect(fournisseur()->estConfigure())->toBeFalse();
    expect(fn () => fournisseur()->creerPaiement(demandeTest()))->toThrow(FournisseurIndisponible::class, 'pas configuré');

    Parametres::definir('geniuspay.sandbox.cle_publique', 'pk_sandbox_x', null, true);
    expect(fournisseur()->estConfigure())->toBeFalse(); // la clé secrète manque

    configurerGeniusPay('sandbox');
    expect(fournisseur()->estConfigure())->toBeTrue()->and(fournisseur()->mode())->toBe('sandbox');

    // Le mode réel a ses propres clés : tant qu'elles manquent, impossible de payer « en vrai ».
    Parametres::definir('geniuspay.mode', 'live');
    expect(fournisseur()->mode())->toBe('live')->and(fournisseur()->estConfigure())->toBeFalse();
});

it('refuse une adresse d\'API qui n\'est pas en HTTPS', function () {
    configurerGeniusPay();
    Parametres::definir('geniuspay.base_url', 'http://geniuspay.ci/api/v1/merchant');

    expect(fournisseur()->estConfigure())->toBeFalse();
});

it('crée un paiement exactement comme la documentation : en-têtes, idempotence, page de paiement au choix du client', function () {
    configurerGeniusPay();
    $faux = fauxGeniusPay();

    $cree = fournisseur()->creerPaiement(demandeTest());

    expect($cree->reference)->toBe('SANDBOX-0001')->and($cree->urlPaiement)->toBe('https://geniuspay.ci/checkout/SANDBOX-0001')
        ->and($cree->frais)->toBe(495)->and($cree->montantNet)->toBe(16005)->and($cree->expireLe)->not->toBeNull();

    $appel = $faux->creations[0];
    expect($appel['url'])->toStartWith('https://geniuspay.ci/api/v1/merchant/payments')
        ->and($appel['corps'])->toMatchArray(['amount' => 16500, 'external_reference' => 'gesimo-resa-1-1', 'success_url' => 'https://gesimo.test/retour'])
        ->and($appel['corps'])->not->toHaveKey('payment_method') // le client choisit sur la page GeniusPay
        ->and($appel['corps']['customer'])->toMatchArray(['name' => 'Awa Koné', 'phone' => '+2250701020304'])
        ->and($appel['entetes']['X-API-Key'][0])->toBe('pk_sandbox_PUBLIQUE-DE-TEST')
        ->and($appel['entetes']['X-API-Secret'][0])->toBe(CLE_SECRETE_TEST)
        ->and($appel['entetes']['Idempotency-Key'][0])->toBe('gesimo-resa-1-1');
});

it('refuse une réponse de création inattendue ou une page de paiement qui n\'est pas en HTTPS', function () {
    configurerGeniusPay();
    Http::fake(['*' => Http::response(['success' => true, 'data' => ['reference' => 'X-1', 'checkout_url' => 'http://pirate.example/paiement']], 201)]);
    expect(fn () => fournisseur()->creerPaiement(demandeTest()))->toThrow(FournisseurIndisponible::class, 'Réponse inattendue');

    Http::swap(new Factory);
    Http::fake(['*' => Http::response(['success' => true, 'data' => []], 201)]);
    expect(fn () => fournisseur()->creerPaiement(demandeTest()))->toThrow(FournisseurIndisponible::class);
});

it('traduit les erreurs du fournisseur sans jamais répéter une clé secrète', function () {
    configurerGeniusPay();

    Http::fake(['*' => Http::response(['error' => ['code' => 'INVALID_API_KEY', 'message' => 'clé '.CLE_SECRETE_TEST], 'echo' => CLE_SECRETE_TEST], 401)]);
    $e1 = rescue(fn () => fournisseur()->creerPaiement(demandeTest()), fn ($e) => $e, false);
    expect($e1)->toBeInstanceOf(RequeteRefusee::class)->and($e1->getMessage())->not->toContain('sk_')->and($e1->getMessage())->not->toContain(CLE_SECRETE_TEST);

    Http::swap(new Factory);
    Http::fake(['*' => Http::response(['error' => ['code' => 'VALIDATION_ERROR']], 422)]);
    $e2 = rescue(fn () => fournisseur()->creerPaiement(demandeTest()), fn ($e) => $e, false);
    expect($e2)->toBeInstanceOf(RequeteRefusee::class)->and($e2->getMessage())->toContain('VALIDATION_ERROR');

    Http::swap(new Factory);
    Http::fake(['*' => Http::response('panne', 500)]);
    expect(fn () => fournisseur()->creerPaiement(demandeTest()))->toThrow(FournisseurIndisponible::class, 'panne');

    Http::swap(new Factory);
    Http::fake(fn () => throw new ConnectionException('cURL error: Authorization sk_sandbox_FUITE'));
    $e3 = rescue(fn () => fournisseur()->creerPaiement(demandeTest()), fn ($e) => $e, false);
    expect($e3)->toBeInstanceOf(FournisseurIndisponible::class)->and($e3->getMessage())->not->toContain('sk_');
});

it('lit l\'état d\'un paiement et traduit les statuts', function (string $statutFournisseur, string $attendu) {
    configurerGeniusPay();
    fauxGeniusPay(['MTX-ABC123' => ['status' => $statutFournisseur, 'amount' => 16500]]);

    $etat = fournisseur()->lirePaiement('MTX-ABC123');

    expect($etat->statut)->toBe($attendu)->and($etat->montant)->toBe(16500);
})->with([
    ['completed', 'reussi'], ['pending', 'en_attente'], ['processing', 'en_attente'],
    ['failed', 'echoue'], ['cancelled', 'echoue'], ['refunded', 'rembourse'], ['bizarre', 'inconnu'],
]);

it('refuse une référence de paiement qui pourrait détourner l\'adresse appelée', function () {
    configurerGeniusPay();
    Http::fake();

    expect(fn () => fournisseur()->lirePaiement('../account/balance'))->toThrow(RequeteRefusee::class);
    expect(fn () => fournisseur()->lirePaiement('MTX 1?x=2'))->toThrow(RequeteRefusee::class);
    Http::assertNothingSent();
});

it('rembourse : montant omis pour un remboursement total, indiqué pour un remboursement partiel', function () {
    configurerGeniusPay();
    $faux = fauxGeniusPay(['MTX-ABC123' => ['status' => 'completed', 'amount' => 55000]]);

    $total = fournisseur()->rembourser('MTX-ABC123', null, 'Annulation par le client');
    $partiel = fournisseur()->rembourser('MTX-ABC123', 35000, 'Nuit retenue');

    expect($total->reference)->toBe('TXN-R1')->and($faux->remboursements[0]['corps'])->not->toHaveKey('amount')->and($faux->remboursements[0]['corps']['reason'])->toBe('Annulation par le client')
        ->and($partiel->montant)->toBe(35000)->and($faux->remboursements[1]['corps']['amount'])->toBe(35000);
});

// --- Signature des messages ----------------------------------------------------------------------------

function lire(string $corps, array $entetes): EvenementPaiement
{
    $minuscules = [];
    foreach ($entetes as $nom => $valeur) {
        $minuscules[strtolower(str_replace('_', '-', preg_replace('/^HTTP_/', '', $nom)))] = $valeur;
    }

    return fournisseur()->lireEvenement($corps, $minuscules);
}

it('accepte un message dont la signature est correcte (HMAC-SHA256 de horodatage.corps)', function () {
    configurerGeniusPay();
    [$corps, $entetes] = messageSigne('payment.success', 'MTX-1', 16500);

    $e = lire($corps, $entetes);

    expect($e->type)->toBe(EvenementPaiement::REUSSI)->and($e->reference)->toBe('MTX-1')->and($e->montant)->toBe(16500)->and($e->environnement)->toBe('sandbox');
});

it('traduit tous les types de messages', function (string $nom, string $type) {
    configurerGeniusPay();
    [$corps, $entetes] = messageSigne($nom, 'MTX-1', 100);

    expect(lire($corps, $entetes)->type)->toBe($type);
})->with([
    ['payment.initiated', EvenementPaiement::INITIE], ['payment.success', EvenementPaiement::REUSSI],
    ['payment.failed', EvenementPaiement::ECHOUE], ['payment.cancelled', EvenementPaiement::ECHOUE],
    ['payment.refunded', EvenementPaiement::REMBOURSE], ['quelque.chose', EvenementPaiement::AUTRE],
]);

it('refuse une fausse signature, un corps modifié, un mauvais secret, un horodatage changé', function (array $options) {
    configurerGeniusPay();
    [$corps, $entetes] = messageSigne('payment.success', 'MTX-1', 16500, $options);
    if (isset($options['modifier'])) {
        $corps = str_replace('16500', '1', $corps); // le montant est changé après la signature
    }

    expect(fn () => lire($corps, $entetes))->toThrow(SignatureInvalide::class);
})->with([
    'fausse signature' => [['signature' => str_repeat('a', 64)]],
    'mauvais secret' => [['secret' => 'whsec_AUTRE']],
    'corps modifié' => [['modifier' => true]],
]);

it('refuse un message dont l\'horodatage ne correspond pas à la signature', function () {
    configurerGeniusPay();
    [$corps, $entetes] = messageSigne('payment.success', 'MTX-1', 16500);
    $entetes['HTTP_X_WEBHOOK_TIMESTAMP'] = (string) (time() + 5);

    expect(fn () => lire($corps, $entetes))->toThrow(SignatureInvalide::class);
});

it('refuse un message sans en-têtes de signature, ou avec un horodatage illisible', function () {
    configurerGeniusPay();
    [$corps] = messageSigne('payment.success', 'MTX-1', 16500);

    expect(fn () => lire($corps, []))->toThrow(SignatureInvalide::class);
    [$corps2, $entetes2] = messageSigne('payment.success', 'MTX-1', 16500, ['horodatage' => 'abc']);
    expect(fn () => lire($corps2, $entetes2))->toThrow(SignatureInvalide::class);
});

it('refuse TOUT message tant que le secret de signature n\'est pas configuré (jamais de vérification sautée)', function () {
    Parametres::definir('geniuspay.mode', 'sandbox');
    [$corps, $entetes] = messageSigne('payment.success', 'MTX-1', 16500, ['secret' => '']);

    expect(fn () => lire($corps, $entetes))->toThrow(SignatureInvalide::class, 'non configuré');
});

it('refuse un message « test » en mode réel, et un message « réel » en mode test', function (string $mode, string $environnementDuMessage) {
    configurerGeniusPay($mode);
    [$corps, $entetes] = messageSigne('payment.success', 'MTX-1', 16500, ['environnement' => $environnementDuMessage]);

    expect(fn () => lire($corps, $entetes))->toThrow(SignatureInvalide::class, 'Environnement');
})->with([['live', 'sandbox'], ['sandbox', 'live']]);

it('conserve les clés chiffrées en base : jamais en clair', function () {
    configurerGeniusPay();

    $enBase = json_encode(DB::table('parametre')->get());

    expect($enBase)->not->toContain(CLE_SECRETE_TEST)->and($enBase)->not->toContain(SECRET_WEBHOOK_TEST)->and($enBase)->not->toContain('PUBLIQUE-DE-TEST');
    expect(json_encode(DB::table('parametre_historique')->get()))->not->toContain('SECRET');
    expect(json_encode(DB::table('journal_audit')->get()))->not->toContain('SECRET');
});

it('les appels partent vers l\'adresse officielle en HTTPS', function () {
    configurerGeniusPay();
    $faux = fauxGeniusPay();

    fournisseur()->creerPaiement(demandeTest());

    Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://geniuspay.ci/api/v1/merchant/payments'));
});
