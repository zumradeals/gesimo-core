<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Liens de connexion par e-mail et codes SMS : on ne stocke jamais le secret, seulement son empreinte.
        Schema::create('jeton_connexion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->constrained('utilisateur')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('empreinte', 64)->index();
            $table->timestampTz('expire_le');
            $table->timestampTz('utilise_le')->nullable();
            $table->unsignedSmallInteger('tentatives')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['utilisateur_id', 'type']);
        });
        DB::statement("ALTER TABLE jeton_connexion ADD CONSTRAINT jeton_type_valide CHECK (type IN ('lien_email', 'code_sms'))");

        Schema::create('invitation_personnel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residence_id')->constrained('residence')->cascadeOnDelete();
            $table->foreignId('invite_par')->constrained('utilisateur');
            $table->string('nom');
            $table->string('email');
            $table->string('telephone', 30);
            $table->boolean('peut_gerer_checkin_checkout')->default(false);
            $table->boolean('peut_gerer_calendrier')->default(false);
            $table->string('empreinte', 64)->index();
            $table->timestampTz('expire_le');
            $table->timestampTz('acceptee_le')->nullable();
            $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateur');
            $table->timestampsTz();
            $table->index(['residence_id', 'email']);
        });
        DB::statement('ALTER TABLE invitation_personnel ADD CONSTRAINT invitation_au_moins_un_droit CHECK (peut_gerer_checkin_checkout OR peut_gerer_calendrier)');
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_personnel');
        Schema::dropIfExists('jeton_connexion');
    }
};
