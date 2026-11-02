<?php

// Données fictives du parcours navigateur « espace voyageur » (lot 3) : ni route ni commande de production.
// À lancer après preparer-reservation.php : un séjour confirmé dans 3 jours, un séjour terminé (avis à donner), un séjour à payer.
use App\Noyau\Models\Utilisateur;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing')) {
    throw new RuntimeException('Ces données sont interdites hors de l’environnement testing.');
}

$client = Utilisateur::where('telephone', '0799100003')->firstOrFail();
$base = ['unite_id' => 1, 'client_id' => $client->id, 'origine' => 'plateforme', 'created_at' => now(), 'updated_at' => now()];
$ids = [];
$lignes = [
    'confirmee' => ['confirmee', now()->addDays(3), now()->addDays(6), ['confirmee']],
    'terminee' => ['terminee', now()->subDays(10), now()->subDays(7), ['confirmee', 'arrivee_enregistree', 'terminee']],
    'a_payer' => ['en_attente', now()->addDays(40), now()->addDays(42), []],
];
foreach ($lignes as $cle => [$statut, $arrivee, $depart, $chemin]) {
    $id = DB::table('reservation')->insertGetId($base + [
        'date_arrivee' => $arrivee->toDateString(), 'date_depart' => $depart->toDateString(),
        'montant_total' => 30000, 'acompte' => 9000, 'solde' => 21000, 'statut' => 'en_attente',
        'expire_le' => now()->addHours(2),
    ]);
    foreach ($chemin as $etape) {
        if ($etape === 'confirmee') {
            DB::table('paiement')->insert(['reservation_id' => $id, 'reference_geniuspay' => 'ESSAI-'.$id, 'montant' => 9000, 'statut' => 'en_attente', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('paiement')->where('reservation_id', $id)->update(['statut' => 'reussi']);
        }
        DB::table('reservation')->where('id', $id)->update(['statut' => $etape]);
    }
    $ids[$cle] = $id;
}
echo json_encode($ids);
