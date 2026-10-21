<?php

// Inventaire supplémentaire du parcours navigateur uniquement ; jamais une commande de production.
use App\Noyau\Models\Utilisateur;
use App\Paiement\Models\MiseEnAvant;
use App\Sejours\Models\Equipement;
use App\Sejours\Models\Residence;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing')) {
    throw new RuntimeException('Ces données sont interdites hors de l’environnement testing.');
}
$gerant = Utilisateur::where('telephone', '0799000001')->firstOrFail();
$communes = ['Cocody', 'Marcory', 'Bingerville', 'Yopougon', 'Abobo', 'Treichville', 'Plateau'];
for ($i = 0; $i < 12; $i++) {
    $r = Residence::create(['gerant_id' => $gerant->id, 'nom' => 'Résidence catalogue ESSAI '.$i,
        'commune' => $communes[$i % count($communes)], 'statut_verification' => 'verifiee']);
    $u = $r->unites()->create(['nom' => 'Logement catalogue ESSAI '.$i, 'type' => 'studio', 'capacite' => 2]);
    $u->tarifs()->create(['type_periode' => 'standard', 'prix_nuit' => 22000 + $i * 1000]);
    $r->photos()->create(['unite_id' => $u->id, 'chemin' => 'essais/maison.png', 'ordre' => 1]);
    if ($i === 0) {
        $u->equipements()->attach(Equipement::where('code', 'wifi')->value('id'));
    }
    if ($i < 6) {
        MiseEnAvant::create(['residence_id' => $r->id, 'cree_par' => $gerant->id, 'date_debut' => today(),
            'date_fin' => today()->addDays(6), 'prix' => 10000, 'offre' => 'Essai navigateur', 'jours' => 7, 'statut' => 'active']);
    }
}
echo "Catalogue fictif du navigateur préparé (testing uniquement).\n";
