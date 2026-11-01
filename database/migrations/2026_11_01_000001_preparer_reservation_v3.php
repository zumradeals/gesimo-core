<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Réservation v3 : informations demandées au voyageur (motif, heure d'arrivée, message, acceptation du règlement),
 * heure de départ retenue à la création, et pièce d'identité conservée dans le profil du voyageur.
 * Le nom et le téléphone du voyageur réutilisent nom_client et telephone_client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation', function (Blueprint $table) {
            $table->string('motif_sejour', 20)->nullable();
            $table->string('motif_precision', 120)->nullable();
            $table->string('heure_arrivee_prevue', 20)->nullable();
            $table->text('message_gerant')->nullable();
            $table->timestampTz('conditions_acceptees_le')->nullable();
            // Comme le délai d'annulation : l'heure en vigueur à la création ne change plus.
            $table->string('heure_depart', 5)->nullable();
        });
        DB::statement("ALTER TABLE reservation ADD CONSTRAINT reservation_motif_valide CHECK (motif_sejour IS NULL OR motif_sejour IN ('vacances', 'affaires', 'famille', 'evenement', 'etudes', 'soins', 'autre'))");
        DB::statement("ALTER TABLE reservation ADD CONSTRAINT reservation_heure_arrivee_prevue_valide CHECK (heure_arrivee_prevue IS NULL OR heure_arrivee_prevue IN ('inconnue', 'avant_14h', '14h_18h', '18h_22h', 'apres_22h'))");

        Schema::create('piece_voyageur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->unique()->constrained('utilisateur');
            $table->string('type', 20);
            // Fichier chiffré par l'application, stocké sur le disque privé uniquement.
            $table->string('chemin');
            $table->string('mime', 60);
            $table->date('valide_jusqu')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE piece_voyageur ADD CONSTRAINT piece_voyageur_type_valide CHECK (type IN ('cni', 'passeport', 'titre_sejour', 'autre'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('piece_voyageur');
        DB::statement('ALTER TABLE reservation DROP CONSTRAINT IF EXISTS reservation_heure_arrivee_prevue_valide');
        DB::statement('ALTER TABLE reservation DROP CONSTRAINT IF EXISTS reservation_motif_valide');
        Schema::table('reservation', function (Blueprint $table) {
            $table->dropColumn(['motif_sejour', 'motif_precision', 'heure_arrivee_prevue', 'message_gerant', 'conditions_acceptees_le', 'heure_depart']);
        });
    }
};
