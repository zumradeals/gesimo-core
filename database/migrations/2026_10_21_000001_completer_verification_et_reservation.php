<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residence', function (Blueprint $table) {
            // Envoyée à la vérification (vide = brouillon, invisible de l'équipe GESIMO).
            $table->timestampTz('soumise_le')->nullable();
            // Badge « Vérifié GESIMO » : accordé en plus de la validation (F-A2).
            $table->boolean('badge_verifie')->default(false);
            $table->text('motif_refus')->nullable();
            $table->foreignId('verifiee_par')->nullable()->constrained('utilisateur');
            $table->timestampTz('verifiee_le')->nullable();
        });
        DB::statement("ALTER TABLE residence ADD CONSTRAINT residence_badge_si_verifiee CHECK (NOT badge_verifie OR statut_verification = 'verifiee')");
        DB::statement("ALTER TABLE residence ADD CONSTRAINT residence_refus_motive CHECK (statut_verification <> 'refusee' OR motif_refus IS NOT NULL)");

        Schema::table('reservation', function (Blueprint $table) {
            $table->unsignedSmallInteger('nombre_personnes')->nullable();
        });
        DB::statement('ALTER TABLE reservation ADD CONSTRAINT reservation_personnes_positif CHECK (nombre_personnes IS NULL OR nombre_personnes > 0)');

        // Un seul prix normal et un seul prix week-end par logement.
        DB::statement("CREATE UNIQUE INDEX tarif_un_seul_par_type ON tarif (unite_id, type_periode) WHERE type_periode IN ('standard', 'week_end')");
        // Les périodes « fête » sont datées.
        DB::statement("ALTER TABLE tarif ADD CONSTRAINT tarif_fete_datee CHECK (type_periode <> 'fete' OR (date_debut IS NOT NULL AND date_fin IS NOT NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tarif DROP CONSTRAINT IF EXISTS tarif_fete_datee');
        DB::statement('DROP INDEX IF EXISTS tarif_un_seul_par_type');
        DB::statement('ALTER TABLE reservation DROP CONSTRAINT IF EXISTS reservation_personnes_positif');
        Schema::table('reservation', fn (Blueprint $table) => $table->dropColumn('nombre_personnes'));
        DB::statement('ALTER TABLE residence DROP CONSTRAINT IF EXISTS residence_refus_motive');
        DB::statement('ALTER TABLE residence DROP CONSTRAINT IF EXISTS residence_badge_si_verifiee');
        Schema::table('residence', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verifiee_par');
            $table->dropColumn(['soumise_le', 'badge_verifie', 'motif_refus', 'verifiee_le']);
        });
    }
};
