<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profil_gerant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->unique()->constrained('utilisateur');
            $table->string('slug', 40)->unique();
            $table->string('nom_complet', 120);
            $table->text('adresse')->nullable();
            $table->string('entreprise', 150)->nullable();
            $table->string('nom_public', 150);
            $table->string('commune_publique', 100)->nullable();
            $table->text('presentation')->nullable();
            $table->string('logo_chemin')->nullable();
            $table->boolean('reprise_a_revoir')->default(false);
            $table->timestampsTz();
        });
        Schema::create('document_gerant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profil_gerant_id')->constrained('profil_gerant');
            $table->string('type', 30);
            $table->string('chemin');
            $table->string('statut', 20)->default('en_attente');
            $table->text('motif_refus')->nullable();
            $table->foreignId('verifie_par')->nullable()->constrained('utilisateur');
            $table->timestampTz('verifie_le')->nullable();
            $table->timestampTz('remplace_le')->nullable();
            $table->foreignId('source_document_id')->nullable()->constrained('document_verification');
            $table->timestampsTz();
            $table->index(['profil_gerant_id', 'type']);
        });
        DB::statement("ALTER TABLE document_gerant ADD CONSTRAINT document_gerant_type CHECK (type IN ('identite', 'certificat_residence'))");
        DB::statement("ALTER TABLE document_gerant ADD CONSTRAINT document_gerant_statut CHECK (statut IN ('en_attente', 'accepte', 'refuse'))");
        DB::statement("ALTER TABLE document_gerant ADD CONSTRAINT document_gerant_refus CHECK (statut <> 'refuse' OR (motif_refus IS NOT NULL AND length(trim(motif_refus)) > 0))");
        DB::statement("CREATE UNIQUE INDEX document_gerant_valide_unique ON document_gerant (profil_gerant_id, type) WHERE statut = 'accepte' AND remplace_le IS NULL");
        Schema::table('document_verification', function (Blueprint $table) {
            $table->timestampTz('remplace_le')->nullable();
            $table->text('motif_refus')->nullable();
        });
        Schema::table('residence', function (Blueprint $table) {
            $table->timestampTz('gestion_verifiee_le')->nullable();
            $table->foreignId('gestion_verifiee_par')->nullable()->constrained('utilisateur');
        });

        // Reprise non destructive : aucune pièce ni ancienne décision n'est effacée.
        DB::table('utilisateur')->whereIn('id', DB::table('role_utilisateur')->where('role', 'gerant')->select('utilisateur_id'))
            ->orderBy('id')->each(function ($u) {
                $sources = DB::table('document_verification')->join('residence', 'residence.id', '=', 'document_verification.residence_id')
                    ->where('residence.gerant_id', $u->id)->where('document_verification.type', 'piece_gerant')
                    ->select('document_verification.*')->orderBy('document_verification.id')->get()->groupBy('chemin');
                $profil = DB::table('profil_gerant')->insertGetId([
                    'utilisateur_id' => $u->id, 'slug' => Str::random(24), 'nom_complet' => $u->nom,
                    'nom_public' => $u->nom, 'reprise_a_revoir' => $sources->count() > 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($sources as $documents) {
                    $source = $documents->firstWhere('statut', 'accepte') ?? $documents->last();
                    $accepte = $sources->count() === 1 && $source->statut === 'accepte';
                    DB::table('document_gerant')->insert([
                        'profil_gerant_id' => $profil, 'type' => 'identite', 'chemin' => $source->chemin,
                        'statut' => $accepte ? 'accepte' : 'en_attente', 'source_document_id' => $source->id,
                        'verifie_par' => $accepte ? $source->verifie_par : null,
                        'verifie_le' => $accepte ? $source->verifie_le : null,
                        'created_at' => $source->created_at, 'updated_at' => now(),
                    ]);
                }
            });
        // Le nouveau libellé du badge implique un contrôle explicite de gestion.
        // Les anciens booléens sont conservés, sans être transformés en nouvelles preuves.
    }

    public function down(): void
    {
        // Avant un retour de schéma, garder une archive chiffrée des dossiers et de toutes leurs références.
        if (DB::table('profil_gerant')->exists()) {
            $archive = Crypt::encryptString(json_encode([
                'profils' => DB::table('profil_gerant')->get(),
                'documents' => DB::table('document_gerant')->get(),
            ], JSON_THROW_ON_ERROR));
            $chemin = 'archives-dossiers-gerants/'.now()->format('YmdHis').'-'.Str::random(12).'.json.enc';
            if (! Storage::disk('local')->put($chemin, $archive)) {
                throw new RuntimeException('Archive privée impossible : le retour de schéma est annulé.');
            }
        }
        Schema::table('residence', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gestion_verifiee_par');
            $table->dropColumn('gestion_verifiee_le');
        });
        Schema::table('document_verification', fn (Blueprint $table) => $table->dropColumn(['remplace_le', 'motif_refus']));
        Schema::dropIfExists('document_gerant');
        Schema::dropIfExists('profil_gerant');
    }
};
