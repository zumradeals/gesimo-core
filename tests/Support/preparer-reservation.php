<?php

// Données fictives du parcours navigateur « réservation » : ce fichier n'est ni une route ni une commande de production.
use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;
use App\Sejours\Models\Equipement;
use App\Sejours\Models\ProfilGerant;
use App\Sejours\Models\Residence;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing')) {
    throw new RuntimeException('Ces données sont interdites hors de l’environnement testing.');
}

$gerant = Utilisateur::create(['nom' => 'ESSAI Gérant réservation', 'telephone' => '0799100001', 'email' => 'gerant-resa@example.test', 'mot_de_passe' => 'mot-de-passe-essai-navigateur']);
$gerant->attribuerRole(Role::Gerant);
$client = Utilisateur::create(['nom' => 'ESSAI Voyageur', 'telephone' => '0799100003', 'email' => 'voyageur@example.test', 'mot_de_passe' => 'mot-de-passe-essai-navigateur']);
$client->attribuerRole(Role::Client);

$profil = ProfilGerant::pour($gerant);
$profil->update(['nom_public' => 'Confort ESSAI', 'adresse' => 'Adresse ESSAI', 'commune_publique' => 'Cocody']);
Storage::disk('local')->put('tests-identites/essai-reservation.pdf', 'Identité fictive réservée au navigateur de test');
$profil->documents()->create(['type' => 'identite', 'statut' => 'accepte', 'chemin' => 'tests-identites/essai-reservation.pdf', 'verifie_le' => now()]);

$image = imagecreatetruecolor(1200, 800);
imagefill($image, 0, 0, imagecolorallocate($image, 230, 214, 196));
ob_start();
imagepng($image);
Storage::disk('public')->put('essais/appartement.png', (string) ob_get_clean());

$r = Residence::create(['gerant_id' => $gerant->id, 'nom' => 'Résidence Papaye — ESSAI', 'commune' => 'Cocody', 'quartier' => 'Djibi', 'statut_verification' => 'verifiee', 'reglement' => 'Pas de fête après 22 h.']);
$u = $r->unites()->create(['nom' => 'Appartement ESSAI', 'type' => 'appartement', 'capacite' => 2]);
$u->tarifs()->create(['type_periode' => 'standard', 'prix_nuit' => 10000]);
$u->tarifs()->create(['type_periode' => 'week_end', 'prix_nuit' => 12000]);
$r->photos()->create(['unite_id' => $u->id, 'chemin' => 'essais/appartement.png', 'ordre' => 1]);
foreach (['climatisation', 'wifi'] as $code) {
    if ($id = Equipement::where('code', $code)->value('id')) {
        $u->equipements()->attach($id);
    }
}

echo json_encode(['unite' => $u->id, 'residence' => $r->id]), "\n";
