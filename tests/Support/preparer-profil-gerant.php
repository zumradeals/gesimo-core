<?php

// Données fictives réservées au navigateur de test : ce fichier n'est ni une route ni une commande de production.
use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;
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
$gerant = Utilisateur::create(['nom' => 'ESSAI Identité privée', 'telephone' => '0799000001', 'email' => 'gerant@example.test', 'mot_de_passe' => 'mot-de-passe-essai-navigateur']);
$gerant->attribuerRole(Role::Gerant);
$admin = Utilisateur::create(['nom' => 'ESSAI Administrateur', 'telephone' => '0799000002', 'email' => 'admin@example.test', 'mot_de_passe' => 'mot-de-passe-essai-navigateur']);
$admin->attribuerRole(Role::Admin);
$profil = ProfilGerant::pour($gerant);
$profil->update(['nom_public' => 'Maison Naya — ESSAI', 'adresse' => 'Adresse personnelle confidentielle ESSAI', 'commune_publique' => 'Cocody', 'presentation' => 'Des logements accueillants pour vos séjours à Abidjan.']);
$r = Residence::create(['gerant_id' => $gerant->id, 'nom' => 'Villa Papaye — ESSAI', 'commune' => 'Cocody']);
$u = $r->unites()->create(['nom' => 'Studio Papaye — ESSAI', 'type' => 'studio', 'capacite' => 2]);
$u->tarifs()->create(['type_periode' => 'standard', 'prix_nuit' => 18000]);
$image = imagecreatetruecolor(600, 400);
imagefill($image, 0, 0, imagecolorallocate($image, 218, 230, 218));
ob_start();
imagepng($image);
$png = (string) ob_get_clean();
Storage::disk('public')->put('essais/maison.png', $png);
$r->photos()->create(['unite_id' => $u->id, 'chemin' => 'essais/maison.png', 'ordre' => 1]);
$dossier = $argv[1] ?? sys_get_temp_dir().'/avelis-parcours';
if (! is_dir($dossier)) {
    mkdir($dossier, 0700, true);
}
file_put_contents($dossier.'/document-fictif.png', $png);
file_put_contents($dossier.'/donnees.json', json_encode(['profilId' => $profil->id, 'slug' => $profil->slug, 'residenceId' => $r->id], JSON_THROW_ON_ERROR));
echo "Données fictives du parcours navigateur préparées.\n";
