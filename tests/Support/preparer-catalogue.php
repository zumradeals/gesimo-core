<?php

// Catalogue fictif du navigateur uniquement ; jamais une commande de production. À lancer après preparer-reservation.php.
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
$gerant = Utilisateur::where('telephone', '0799100001')->firstOrFail();
$modeles = [
    ['Studio lumineux', 'studio', 'Marcory', 'Zone 4', 18000, 2],
    ['Appartement avec terrasse', 'appartement', 'Cocody', 'Riviera', 35000, 4],
    ['Résidence familiale', 'appartement', 'Cocody', 'Angré', 25000, 6],
    ['Chambre confort', 'chambre', 'Bingerville', null, 12000, 2],
    ['Studio vue lagune', 'studio', 'Marcory', 'Biétry', 20000, 2],
    ['Appartement 3 pièces', 'appartement', 'Cocody', 'Deux-Plateaux', 30000, 5],
    ['Chambre simple', 'chambre', 'Cocody', 'Danga', 10000, 1],
    ['Studio centre', 'studio', 'Plateau', null, 22000, 2],
];
foreach ($modeles as $i => [$nom, $type, $commune, $quartier, $prix, $capacite]) {
    $r = Residence::create(['gerant_id' => $gerant->id, 'nom' => 'Résidence '.$nom, 'commune' => $commune, 'quartier' => $quartier, 'statut_verification' => 'verifiee']);
    $u = $r->unites()->create(['nom' => $nom, 'type' => $type, 'capacite' => $capacite]);
    $u->tarifs()->create(['type_periode' => 'standard', 'prix_nuit' => $prix]);
    $r->photos()->create(['unite_id' => $u->id, 'chemin' => 'essais/appartement.png', 'ordre' => 1]);
    $u->equipements()->attach(Equipement::whereIn('code', $i % 2 ? ['wifi', 'climatisation'] : ['wifi', 'parking', 'groupe_electrogene'])->pluck('id'));
    if ($i < 2) {
        MiseEnAvant::create(['residence_id' => $r->id, 'cree_par' => $gerant->id, 'date_debut' => today(), 'date_fin' => today()->addDays(6),
            'prix' => 10000, 'offre' => 'Essai navigateur', 'jours' => 7, 'statut' => 'active']);
    }
}
echo count($modeles)." logements fictifs ajoutés.\n";
