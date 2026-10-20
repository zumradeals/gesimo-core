<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chaque réservation garde les conditions en vigueur le jour où elle est créée (commission, délai d'annulation).
        Schema::table('reservation', function (Blueprint $table) {
            $table->unsignedSmallInteger('annulation_delai_heures')->nullable();
            $table->string('annulation_heure_arrivee', 5)->nullable();
        });

        // Mise en avant : durée de l'offre achetée, créateur, suspension par l'équipe.
        Schema::table('mise_en_avant', function (Blueprint $table) {
            $table->string('offre', 60)->nullable();
            $table->unsignedSmallInteger('jours')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('utilisateur');
            $table->timestampTz('suspendue_le')->nullable();
            $table->foreignId('suspendue_par')->nullable()->constrained('utilisateur');
            $table->text('suspension_motif')->nullable();
            $table->index(['statut', 'date_debut', 'date_fin']);
        });
        // Une mise en avant payée n'est écrite qu'une seule fois dans le livre de comptes (revenu de GESIMO).
        DB::statement("CREATE UNIQUE INDEX ecriture_mise_en_avant_unique ON ecriture_comptable (paiement_id) WHERE type = 'mise_en_avant' AND correction_de IS NULL");
        DB::statement('ALTER TABLE mise_en_avant DROP CONSTRAINT mise_en_avant_statut_valide');
        DB::statement("ALTER TABLE mise_en_avant ADD CONSTRAINT mise_en_avant_statut_valide CHECK (statut IN ('en_attente_paiement', 'active', 'suspendue', 'terminee', 'annulee'))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ecriture_mise_en_avant_unique');
        DB::statement("UPDATE mise_en_avant SET statut = 'annulee' WHERE statut = 'suspendue'");
        DB::statement('ALTER TABLE mise_en_avant DROP CONSTRAINT mise_en_avant_statut_valide');
        DB::statement("ALTER TABLE mise_en_avant ADD CONSTRAINT mise_en_avant_statut_valide CHECK (statut IN ('en_attente_paiement', 'active', 'terminee', 'annulee'))");
        Schema::table('mise_en_avant', function (Blueprint $table) {
            $table->dropIndex(['statut', 'date_debut', 'date_fin']);
            $table->dropConstrainedForeignId('suspendue_par');
            $table->dropConstrainedForeignId('cree_par');
            $table->dropColumn(['offre', 'jours', 'suspendue_le', 'suspension_motif']);
        });
        Schema::table('reservation', fn (Blueprint $t) => $t->dropColumn(['annulation_delai_heures', 'annulation_heure_arrivee']));
    }
};
