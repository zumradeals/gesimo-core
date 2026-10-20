<?php

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;
use App\Paiement\Models\Paiement;
use App\Paiement\Services\ArriveeService;
use App\Paiement\Services\PaiementService;
use App\Parametres\Services\Parametres;
use App\Sejours\Models\Reservation;
use App\Sejours\Models\Residence;
use App\Sejours\Models\Unite;
use App\Sejours\Services\ReservationService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
// Les tests de concurrence gèrent eux-mêmes la base : leurs données doivent être réellement enregistrées.
pest()->extend(TestCase::class)->in('Concurrence');

/** Crée une personne avec les rôles demandés. */
function creerUtilisateur(Role ...$roles): Utilisateur
{
    static $n = 0;
    $n++;

    $utilisateur = Utilisateur::create([
        'nom' => "Test {$n}",
        'telephone' => sprintf('07%08d', $n),
        'email' => "test{$n}@example.test",
        'mot_de_passe' => 'mot-de-passe-de-test',
    ]);
    foreach ($roles as $role) {
        $utilisateur->attribuerRole($role);
    }

    return $utilisateur;
}

function creerResidence(?Utilisateur $gerant = null, array $attributs = []): Residence
{
    $gerant ??= creerUtilisateur(Role::Gerant);

    return Residence::create($attributs + [
        'gerant_id' => $gerant->id,
        'nom' => 'Résidence de test',
        'commune' => 'Cocody',
        'statut_verification' => 'verifiee',
    ]);
}

function creerUnite(?Residence $residence = null): Unite
{
    $residence ??= creerResidence();

    return $residence->unites()->create(['nom' => 'Chambre test', 'type' => 'chambre', 'capacite' => 2]);
}

/**
 * Insertion SQL directe, sans passer par l'application : c'est la base qui doit se défendre.
 *
 * Une réservation en ligne naît toujours « En attente » ; pour obtenir un autre statut, on suit le chemin permis
 * (avec un paiement réussi quand il faut « Confirmer »), exactement comme le ferait l'application.
 */
function insererReservationSql(Unite $unite, string $arrivee, string $depart, array $surcharges = []): int
{
    $statutVoulu = $surcharges['statut'] ?? 'confirmee';
    $hors = ($surcharges['origine'] ?? 'plateforme') === 'hors_plateforme';
    $chemins = [
        'en_attente' => [], 'hors_plateforme' => [], 'confirmee' => ['confirmee'], 'arrivee_enregistree' => ['confirmee', 'arrivee_enregistree'],
        'terminee' => $hors ? ['terminee'] : ['confirmee', 'arrivee_enregistree', 'terminee'], 'annulee' => ['annulee'], 'expiree' => ['expiree'], 'litige' => ['confirmee', 'litige'],
    ];
    $client = $hors ? null : ($surcharges['client_id'] ?? creerUtilisateur(Role::Client)->id);

    $ligne = $surcharges + [
        'unite_id' => $unite->id,
        'client_id' => $client,
        'date_arrivee' => $arrivee,
        'date_depart' => $depart,
        'montant_total' => 30000,
        'acompte' => 9000,
        'solde' => 21000,
        'origine' => 'plateforme',
        'created_at' => now(),
        'updated_at' => now(),
    ];
    $ligne['statut'] = $hors ? 'hors_plateforme' : 'en_attente';
    if (! array_key_exists($statutVoulu, $chemins)) {
        $ligne['statut'] = $statutVoulu; // statut inconnu : c'est la base qui doit le refuser
    }
    if (! $hors) {
        $ligne['expire_le'] = array_key_exists('expire_le', $surcharges) ? $surcharges['expire_le'] : now()->addHours(2);
    }

    $id = DB::table('reservation')->insertGetId($ligne);

    foreach ($chemins[$statutVoulu] ?? [] as $etape) {
        if ($etape === 'confirmee' && ! $hors) {
            // « Confirmée » exige un paiement réussi couvrant l'acompte.
            DB::table('paiement')->insert([
                'reservation_id' => $id, 'reference_geniuspay' => 'AUTO-'.Str::random(12), 'montant' => max(1, (int) $ligne['acompte']),
                'statut' => 'en_attente', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('paiement')->where('reservation_id', $id)->update(['statut' => 'reussi']);
        }
        DB::table('reservation')->where('id', $id)->update(['statut' => $etape]);
    }

    return $id;
}

/** Exécute une action qui doit être refusée par la base ; renvoie le code d'erreur PostgreSQL. */
function codeRefusBase(Closure $action): ?string
{
    try {
        DB::transaction($action); // point de sauvegarde : la transaction du test reste utilisable
    } catch (QueryException $e) {
        return $e->getCode();
    }

    return null;
}

/** Un logement réservable : résidence vérifiée, prix normal et prix week-end. */
function creerLogement(int $prix = 15000, ?int $prixWeekEnd = 20000, array $residence = [], int $capacite = 3): Unite
{
    $unite = creerResidence(null, $residence)->unites()->create(['nom' => 'Chambre test', 'type' => 'chambre', 'capacite' => $capacite]);
    $unite->tarifs()->create(['type_periode' => 'standard', 'prix_nuit' => $prix]);
    if ($prixWeekEnd !== null) {
        $unite->tarifs()->create(['type_periode' => 'week_end', 'prix_nuit' => $prixWeekEnd]);
    }

    return $unite->fresh();
}

/** Prochain vendredi (au moins dans 10 jours) : sert de date d'arrivée stable dans les tests. */
function prochainVendredi(): CarbonImmutable
{
    return CarbonImmutable::now()->addDays(10)->next(CarbonInterface::FRIDAY)->startOfDay();
}

// =====================================================================================================
// Paiement (Lot 3) : faux GeniusPay. Aucune requête réelle ne part jamais pendant les tests.
// =====================================================================================================

const CLE_SECRETE_TEST = 'sk_sandbox_CLE-SECRETE-DE-TEST-0123456789';
const SECRET_WEBHOOK_TEST = 'whsec_SECRET-WEBHOOK-DE-TEST-0123456789';

/** Saisit les réglages comme le ferait le super-administrateur dans les Paramètres. */
function configurerGeniusPay(string $mode = 'sandbox', ?float $commission = 10.0): void
{
    Parametres::definir('geniuspay.mode', $mode);
    Parametres::definir("geniuspay.{$mode}.cle_publique", 'pk_'.$mode.'_PUBLIQUE-DE-TEST', null, true);
    Parametres::definir("geniuspay.{$mode}.cle_secrete", CLE_SECRETE_TEST, null, true);
    Parametres::definir("geniuspay.{$mode}.webhook_secret", SECRET_WEBHOOK_TEST, null, true);
    if ($commission !== null) {
        Parametres::definir('commission.pourcentage', (string) $commission);
    }
}

/**
 * Simule GeniusPay. $etats : référence => ['status' => 'completed'|..., 'amount' => int] pour GET /payments/{ref}.
 * Renvoie un objet qui garde la trace des appels, pour que les tests vérifient ce qui a été envoyé.
 */
function fauxGeniusPay(array $etats = [], bool $remboursementPossible = true): object
{
    $etat = new class
    {
        public array $etats = [];

        public array $creations = [];

        public array $remboursements = [];

        public array $lectures = [];

        public bool $remboursementPossible = true;

        public int $statutSolde = 200;

        public int $appelsSolde = 0;

        public int $compteur = 0;
    };
    $etat->etats = $etats;
    $etat->remboursementPossible = $remboursementPossible;

    Http::fake(function (Request $requete) use ($etat) {
        $chemin = parse_url($requete->url(), PHP_URL_PATH);
        $chemin = preg_replace('#^/api/v1/merchant#', '', $chemin);

        if ($requete->method() === 'GET' && $chemin === '/account/balance') {
            $etat->appelsSolde++;

            return Http::response($etat->statutSolde === 200 ? ['success' => true, 'data' => ['balance' => 0, 'currency' => 'XOF']] : ['success' => false], $etat->statutSolde);
        }
        if ($requete->method() === 'POST' && $chemin === '/payments') {
            $etat->compteur++;
            $reference = 'SANDBOX-'.str_pad((string) $etat->compteur, 4, '0', STR_PAD_LEFT);
            $etat->creations[] = ['reference' => $reference, 'corps' => $requete->data(), 'entetes' => $requete->headers(), 'url' => $requete->url()];
            $montant = (int) $requete['amount'];

            return Http::response(['success' => true, 'data' => [
                'id' => $etat->compteur, 'reference' => $reference, 'external_reference' => $requete['external_reference'] ?? null,
                'amount' => $montant, 'fees' => (int) round($montant * 0.03), 'net_amount' => $montant - (int) round($montant * 0.03),
                'status' => 'pending', 'checkout_url' => "https://geniuspay.ci/checkout/{$reference}", 'environment' => 'sandbox',
                'expires_at' => now()->addDay()->toIso8601ZuluString(),
            ]], 201);
        }
        if ($requete->method() === 'POST' && preg_match('#^/payments/([^/]+)/refund$#', $chemin, $m)) {
            $etat->remboursements[] = ['reference' => $m[1], 'corps' => $requete->data(), 'entetes' => $requete->headers()];
            if (! $etat->remboursementPossible) {
                return Http::response(['success' => false, 'message' => 'Service indisponible'], 503);
            }

            return Http::response(['success' => true, 'message' => 'Refund processed', 'data' => [
                'reference' => $m[1], 'status' => 'refunded', 'refund_reference' => 'TXN-R'.count($etat->remboursements),
                'amount_refunded' => (int) ($requete['amount'] ?? ($etat->etats[$m[1]]['amount'] ?? 0)), 'currency' => 'XOF', 'environment' => 'sandbox',
            ]], 200);
        }
        if ($requete->method() === 'GET' && preg_match('#^/payments/([^/]+)$#', $chemin, $m)) {
            $etat->lectures[] = $m[1];
            if (! isset($etat->etats[$m[1]])) {
                return Http::response(['success' => false, 'error' => ['code' => 'TRANSACTION_NOT_FOUND']], 404);
            }

            return Http::response(['success' => true, 'data' => ['reference' => $m[1], 'payment_method' => 'wave'] + $etat->etats[$m[1]]], 200);
        }

        return Http::response(['success' => false], 404);
    });

    return $etat;
}

/** Corps et en-têtes d'un message GeniusPay correctement signé (documentation officielle). */
function messageSigne(string $evenement, string $reference, int $montant, array $options = []): array
{
    $horodatage = (string) ($options['horodatage'] ?? time());
    $corps = $options['corps'] ?? json_encode([
        'event' => $evenement,
        'timestamp' => now()->toIso8601ZuluString(),
        'data' => [
            'transaction' => ['id' => 1, 'reference' => $reference, 'amount' => $montant, 'status' => 'completed', 'metadata' => []],
            'merchant' => ['id' => 'uuid-marchand', 'name' => 'GESIMO'],
            'environment' => $options['environnement'] ?? 'sandbox',
        ],
    ]);
    $signature = $options['signature'] ?? hash_hmac('sha256', $horodatage.'.'.$corps, $options['secret'] ?? SECRET_WEBHOOK_TEST);

    return [$corps, [
        'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
        'HTTP_X_WEBHOOK_TIMESTAMP' => $horodatage,
        'HTTP_X_WEBHOOK_EVENT' => $evenement,
        'HTTP_X_WEBHOOK_ENVIRONMENT' => $options['environnement'] ?? 'sandbox',
        'CONTENT_TYPE' => 'application/json',
    ]];
}

/** Envoie un message signé sur l'adresse de réception, comme le ferait GeniusPay. */
function recevoirWebhook(TestCase $test, string $evenement, string $reference, int $montant, array $options = []): TestResponse
{
    [$corps, $entetes] = messageSigne($evenement, $reference, $montant, $options);

    return $test->call('POST', '/webhooks/geniuspay', [], [], [], $entetes, $corps);
}

/** Une réservation « En attente » d'un client, prête à payer. */
function reservationAPayer(array $attributs = []): Reservation
{
    $client = $attributs['client'] ?? creerUtilisateur(Role::Client);
    $unite = $attributs['unite'] ?? creerLogement(15000, 20000, $attributs['residence'] ?? []);
    $arrivee = $attributs['arrivee'] ?? prochainVendredi();
    $nuits = $attributs['nuits'] ?? 3;

    $reservation = app(ReservationService::class)->garderDates($client, $unite, $arrivee, $arrivee->addDays($nuits), 2);

    return $reservation->fresh();
}

/** Paie et confirme une réservation de bout en bout (démarrage + message signé), comme en production. */
function payerEtConfirmer(TestCase $test, object $faux, Reservation $reservation): Paiement
{
    $paiement = app(PaiementService::class)->demarrer($reservation, $reservation->client);
    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'completed', 'amount' => $paiement->montant];
    recevoirWebhook($test, 'payment.success', $paiement->reference_geniuspay, $paiement->montant)->assertOk();

    return $paiement->fresh();
}

/** Une réservation payée, dont le client est arrivé aujourd'hui puis reparti : statut « Terminée ». */
function reservationTerminee(TestCase $test, object $faux): Reservation
{
    $reservation = reservationAPayer(['arrivee' => CarbonImmutable::today(), 'nuits' => 2]);
    payerEtConfirmer($test, $faux, $reservation);
    $gerant = $reservation->unite->residence->gerant;
    app(ArriveeService::class)->enregistrerArrivee($reservation->fresh(), $gerant);
    app(ArriveeService::class)->enregistrerDepart($reservation->fresh(), $gerant);

    return $reservation->fresh();
}
