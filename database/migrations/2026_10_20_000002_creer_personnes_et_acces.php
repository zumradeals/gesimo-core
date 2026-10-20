<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilisateur', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('telephone', 30)->unique();
            $table->string('email')->unique();
            $table->timestampTz('email_verifie_le')->nullable();
            // Haché. Vide possible : connexion par code SMS ou par lien e-mail (F-C1).
            $table->string('mot_de_passe')->nullable();
            $table->string('statut', 20)->default('actif');
            $table->rememberToken();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE utilisateur ADD CONSTRAINT utilisateur_statut_valide CHECK (statut IN ('actif', 'suspendu'))");

        Schema::create('role_utilisateur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->constrained('utilisateur')->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestampsTz();
            $table->unique(['utilisateur_id', 'role']);
        });
        DB::statement("ALTER TABLE role_utilisateur ADD CONSTRAINT role_utilisateur_role_valide CHECK (role IN ('client', 'gerant', 'personnel', 'admin', 'super_admin'))");

        // Le lien « membre_residence » est créé avec les biens (il dépend de la table residence).

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('role_utilisateur');
        Schema::dropIfExists('utilisateur');
    }
};
