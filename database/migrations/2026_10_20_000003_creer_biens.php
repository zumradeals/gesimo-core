<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('residence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gerant_id')->constrained('utilisateur');
            $table->string('nom');
            $table->string('commune', 100);
            $table->string('quartier', 100)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->text('reglement')->nullable();
            $table->string('statut_verification', 20)->default('en_attente');
            $table->string('mode_paiement', 10)->default('total');
            $table->unsignedSmallInteger('pourcentage_acompte')->nullable();
            $table->string('politique_annulation', 20)->default('flexible');
            $table->timestampsTz();
            $table->index(['commune', 'statut_verification']);
            $table->index('gerant_id');
        });
        DB::statement("ALTER TABLE residence ADD CONSTRAINT residence_statut_valide CHECK (statut_verification IN ('en_attente', 'verifiee', 'refusee', 'suspendue'))");
        DB::statement("ALTER TABLE residence ADD CONSTRAINT residence_mode_paiement_valide CHECK (mode_paiement IN ('total', 'acompte'))");
        DB::statement("ALTER TABLE residence ADD CONSTRAINT residence_acompte_valide CHECK (mode_paiement = 'total' OR (pourcentage_acompte IS NOT NULL AND pourcentage_acompte BETWEEN 1 AND 100))");
        DB::statement('ALTER TABLE residence ADD CONSTRAINT residence_acompte_positif CHECK (pourcentage_acompte IS NULL OR pourcentage_acompte BETWEEN 1 AND 100)');
        DB::statement("ALTER TABLE residence ADD CONSTRAINT residence_politique_valide CHECK (politique_annulation IN ('flexible', 'stricte'))");
        DB::statement('ALTER TABLE residence ADD CONSTRAINT residence_gps_valide CHECK ((latitude IS NULL) = (longitude IS NULL))');

        Schema::create('membre_residence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residence_id')->constrained('residence')->cascadeOnDelete();
            $table->foreignId('utilisateur_id')->constrained('utilisateur')->cascadeOnDelete();
            // Droits limités par le gérant (cahier, section 2 : check-in/check-out et calendrier).
            $table->boolean('peut_gerer_checkin_checkout')->default(false);
            $table->boolean('peut_gerer_calendrier')->default(false);
            $table->timestampsTz();
            $table->unique(['residence_id', 'utilisateur_id']);
            $table->index('utilisateur_id');
        });

        Schema::create('unite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residence_id')->constrained('residence')->cascadeOnDelete();
            $table->string('nom');
            $table->string('type', 20);
            $table->unsignedSmallInteger('capacite');
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->index('residence_id');
        });
        DB::statement("ALTER TABLE unite ADD CONSTRAINT unite_type_valide CHECK (type IN ('chambre', 'studio', 'appartement'))");
        DB::statement('ALTER TABLE unite ADD CONSTRAINT unite_capacite_positive CHECK (capacite > 0)');

        Schema::create('equipement', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle', 100);
            $table->timestampsTz();
        });
        // Liste de référence des filtres de recherche (F-C2).
        $maintenant = now();
        DB::table('equipement')->insert(array_map(fn ($ligne) => $ligne + ['created_at' => $maintenant, 'updated_at' => $maintenant], [
            ['code' => 'climatisation', 'libelle' => 'Climatisation'],
            ['code' => 'groupe_electrogene', 'libelle' => 'Groupe électrogène'],
            ['code' => 'eau_chaude', 'libelle' => 'Eau chaude'],
            ['code' => 'wifi', 'libelle' => 'Wi-Fi'],
            ['code' => 'parking', 'libelle' => 'Parking'],
        ]));

        Schema::create('unite_equipement', function (Blueprint $table) {
            $table->foreignId('unite_id')->constrained('unite')->cascadeOnDelete();
            $table->foreignId('equipement_id')->constrained('equipement')->cascadeOnDelete();
            $table->primary(['unite_id', 'equipement_id']);
        });

        Schema::create('photo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residence_id')->constrained('residence')->cascadeOnDelete();
            $table->foreignId('unite_id')->nullable()->constrained('unite')->cascadeOnDelete();
            $table->string('chemin');
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestampsTz();
            $table->index(['residence_id', 'ordre']);
            $table->index('unite_id');
        });

        Schema::create('tarif', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unite_id')->constrained('unite')->cascadeOnDelete();
            $table->string('type_periode', 20)->default('standard');
            $table->string('libelle')->nullable();
            $table->bigInteger('prix_nuit');
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->timestampsTz();
            $table->index(['unite_id', 'type_periode']);
        });
        DB::statement("ALTER TABLE tarif ADD CONSTRAINT tarif_type_valide CHECK (type_periode IN ('standard', 'week_end', 'fete'))");
        DB::statement('ALTER TABLE tarif ADD CONSTRAINT tarif_prix_positif CHECK (prix_nuit > 0)');
        DB::statement('ALTER TABLE tarif ADD CONSTRAINT tarif_dates_valides CHECK (date_debut IS NULL OR date_fin IS NULL OR date_fin >= date_debut)');

        Schema::create('document_verification', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residence_id')->constrained('residence')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('chemin');
            $table->string('statut', 20)->default('en_attente');
            $table->foreignId('verifie_par')->nullable()->constrained('utilisateur');
            $table->timestampTz('verifie_le')->nullable();
            $table->timestampsTz();
            $table->index('residence_id');
        });
        DB::statement("ALTER TABLE document_verification ADD CONSTRAINT document_type_valide CHECK (type IN ('piece_gerant', 'justificatif_droit_gestion'))");
        DB::statement("ALTER TABLE document_verification ADD CONSTRAINT document_statut_valide CHECK (statut IN ('en_attente', 'accepte', 'refuse'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('document_verification');
        Schema::dropIfExists('tarif');
        Schema::dropIfExists('photo');
        Schema::dropIfExists('unite_equipement');
        Schema::dropIfExists('equipement');
        Schema::dropIfExists('unite');
        Schema::dropIfExists('membre_residence');
        Schema::dropIfExists('residence');
    }
};
