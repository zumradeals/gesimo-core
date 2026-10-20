<?php

namespace Database\Seeders;

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;
use App\Sejours\Models\MembreResidence;
use App\Sejours\Models\Reservation;
use App\Sejours\Models\Residence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Données de démonstration. Tout est marqué « DÉMO ».
 * Impossible à charger hors des environnements de développement : voir ENVIRONNEMENTS_AUTORISES.
 * Les mots de passe sont tirés au hasard et affichés une seule fois : aucun compte à mot de passe connu.
 */
class DemoSeeder extends Seeder
{
    public const ENVIRONNEMENTS_AUTORISES = ['local', 'testing', 'demo'];

    public function run(): void
    {
        if (! app()->environment(self::ENVIRONNEMENTS_AUTORISES)) {
            throw new RuntimeException('Les données DÉMO ne se chargent jamais en production.');
        }

        $motDePasse = Str::password(16, symbols: false);

        DB::transaction(function () use ($motDePasse) {
            $comptes = [
                'client' => $this->compte('DÉMO Client', '0700000001', 'client', Role::Client, $motDePasse),
                'gerant' => $this->compte('DÉMO Gérant', '0700000002', 'gerant', Role::Gerant, $motDePasse),
                'personnel' => $this->compte('DÉMO Personnel', '0700000003', 'personnel', Role::Personnel, $motDePasse),
                'admin' => $this->compte('DÉMO Admin', '0700000004', 'admin', Role::Admin, $motDePasse),
                'super_admin' => $this->compte('DÉMO Super-administrateur', '0700000005', 'superadmin', Role::SuperAdmin, $motDePasse),
            ];

            $residence = Residence::create([
                'gerant_id' => $comptes['gerant']->id,
                'nom' => 'DÉMO Résidence Les Palmiers',
                'commune' => 'Cocody',
                'quartier' => 'Riviera (démo)',
                'reglement' => 'DÉMO : règlement de démonstration.',
                'statut_verification' => 'verifiee',
                'soumise_le' => now(),
                'badge_verifie' => true,
                'mode_paiement' => 'total',
            ]);

            MembreResidence::create([
                'residence_id' => $residence->id,
                'utilisateur_id' => $comptes['personnel']->id,
                'peut_gerer_checkin_checkout' => true,
                'peut_gerer_calendrier' => true,
            ]);

            $climatisation = DB::table('equipement')->where('code', 'climatisation')->value('id');
            $wifi = DB::table('equipement')->where('code', 'wifi')->value('id');

            foreach ([['DÉMO Chambre 1', 'chambre', 2, 15000], ['DÉMO Studio 2', 'studio', 3, 25000]] as [$nom, $type, $capacite, $prix]) {
                $unite = $residence->unites()->create(['nom' => $nom, 'type' => $type, 'capacite' => $capacite]);
                DB::table('unite_equipement')->insert([
                    ['unite_id' => $unite->id, 'equipement_id' => $climatisation],
                    ['unite_id' => $unite->id, 'equipement_id' => $wifi],
                ]);
                DB::table('tarif')->insert([
                    ['unite_id' => $unite->id, 'type_periode' => 'standard', 'libelle' => 'DÉMO Prix normal', 'prix_nuit' => $prix, 'created_at' => now(), 'updated_at' => now()],
                    ['unite_id' => $unite->id, 'type_periode' => 'week_end', 'libelle' => 'DÉMO Prix week-end', 'prix_nuit' => $prix + 5000, 'created_at' => now(), 'updated_at' => now()],
                ]);
            }

            $unite = $residence->unites()->first();
            Reservation::create([
                'unite_id' => $unite->id, 'client_id' => $comptes['client']->id,
                'date_arrivee' => now()->addDays(10)->toDateString(), 'date_depart' => now()->addDays(12)->toDateString(),
                'nombre_personnes' => 2, 'montant_total' => 30000, 'acompte' => 30000, 'solde' => 0,
                'statut' => 'confirmee', 'origine' => 'plateforme', 'cree_par' => $comptes['client']->id,
            ]);
        });

        $this->command?->warn("Comptes DÉMO créés (adresses @demo.invalid). Mot de passe commun, affiché une seule fois : {$motDePasse}");
    }

    private function compte(string $nom, string $telephone, string $identifiant, Role $role, string $motDePasse): Utilisateur
    {
        $utilisateur = Utilisateur::create([
            'nom' => $nom,
            'telephone' => $telephone,
            'email' => "demo.{$identifiant}@demo.invalid",
            'mot_de_passe' => $motDePasse,
        ]);
        $utilisateur->attribuerRole($role);

        return $utilisateur;
    }
}
