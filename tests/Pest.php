<?php

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;
use App\Sejours\Models\Residence;
use App\Sejours\Models\Unite;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

/** Insertion SQL directe, sans passer par l'application : c'est la base qui doit se défendre. */
function insererReservationSql(Unite $unite, string $arrivee, string $depart, array $surcharges = []): int
{
    $client = $surcharges['client_id'] ?? creerUtilisateur(Role::Client)->id;

    return DB::table('reservation')->insertGetId($surcharges + [
        'unite_id' => $unite->id,
        'client_id' => $client,
        'date_arrivee' => $arrivee,
        'date_depart' => $depart,
        'montant_total' => 30000,
        'acompte' => 9000,
        'solde' => 21000,
        'statut' => 'confirmee',
        'origine' => 'plateforme',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
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
