<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained('reservation');
            $table->foreignId('client_id')->constrained('utilisateur');
            $table->unsignedSmallInteger('note');
            $table->text('commentaire')->nullable();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE avis ADD CONSTRAINT avis_note_valide CHECK (note BETWEEN 1 AND 5)');
        // F-C7 : un avis n'est possible qu'après un séjour terminé, et seulement par le client du séjour.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION verifier_avis_apres_sejour() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM reservation WHERE id = NEW.reservation_id AND statut = 'terminee' AND client_id = NEW.client_id
                ) THEN
                    RAISE EXCEPTION 'Un avis n''est possible qu''après un séjour terminé, par le client concerné.'
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$
        SQL);
        DB::unprepared('CREATE TRIGGER avis_apres_sejour BEFORE INSERT ON avis FOR EACH ROW EXECUTE FUNCTION verifier_avis_apres_sejour()');

        Schema::create('litige', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservation');
            $table->foreignId('ouvert_par')->constrained('utilisateur');
            $table->foreignId('partie_adverse_id')->nullable()->constrained('utilisateur');
            $table->text('motif');
            $table->text('decision')->nullable();
            $table->foreignId('decide_par')->nullable()->constrained('utilisateur');
            $table->timestampTz('decide_le')->nullable();
            $table->string('statut', 20)->default('ouvert');
            $table->timestampsTz();
            $table->index('reservation_id');
        });
        DB::statement("ALTER TABLE litige ADD CONSTRAINT litige_statut_valide CHECK (statut IN ('ouvert', 'resolu'))");

        Schema::create('notification', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destinataire_id')->constrained('utilisateur');
            $table->string('canal', 20)->default('email');
            $table->string('sujet');
            $table->text('contenu');
            $table->string('statut', 20)->default('en_attente');
            $table->timestampTz('envoye_le')->nullable();
            $table->text('erreur')->nullable();
            $table->timestampsTz();
            $table->index(['statut', 'created_at']);
            $table->index('destinataire_id');
        });
        DB::statement("ALTER TABLE notification ADD CONSTRAINT notification_canal_valide CHECK (canal IN ('email'))");
        DB::statement("ALTER TABLE notification ADD CONSTRAINT notification_statut_valide CHECK (statut IN ('en_attente', 'envoye', 'echoue'))");

        Schema::create('parametre', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 100)->unique();
            // Pour un secret, ce champ contient uniquement le texte chiffré.
            $table->text('valeur')->nullable();
            $table->boolean('est_secret')->default(false);
            $table->string('groupe', 50)->default('general');
            $table->foreignId('modifie_par')->nullable()->constrained('utilisateur');
            $table->timestampsTz();
        });

        Schema::create('parametre_historique', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parametre_id')->constrained('parametre');
            // Toujours vides pour un secret : on trace le changement, jamais la valeur.
            $table->text('ancienne_valeur')->nullable();
            $table->text('nouvelle_valeur')->nullable();
            $table->boolean('est_secret')->default(false);
            $table->foreignId('modifie_par')->nullable()->constrained('utilisateur');
            $table->timestampTz('created_at')->useCurrent();
            $table->index('parametre_id');
        });
        DB::statement('ALTER TABLE parametre_historique ADD CONSTRAINT historique_secret_sans_valeur CHECK (NOT est_secret OR (ancienne_valeur IS NULL AND nouvelle_valeur IS NULL))');

        Schema::create('journal_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateur');
            $table->string('action', 100);
            $table->string('objet_type', 100)->nullable();
            $table->unsignedBigInteger('objet_id')->nullable();
            $table->jsonb('details')->nullable();
            $table->string('adresse_ip', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['objet_type', 'objet_id']);
            $table->index(['utilisateur_id', 'created_at']);
            $table->index('action');
        });

        foreach (['parametre_historique', 'journal_audit'] as $nomTable) {
            DB::unprepared("CREATE TRIGGER {$nomTable}_immuable BEFORE UPDATE OR DELETE ON {$nomTable} FOR EACH ROW EXECUTE FUNCTION interdire_modification()");
            DB::unprepared("CREATE TRIGGER {$nomTable}_sans_vidage BEFORE TRUNCATE ON {$nomTable} FOR EACH STATEMENT EXECUTE FUNCTION interdire_modification()");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_audit');
        Schema::dropIfExists('parametre_historique');
        Schema::dropIfExists('parametre');
        Schema::dropIfExists('notification');
        Schema::dropIfExists('litige');
        Schema::dropIfExists('avis');
        DB::unprepared('DROP FUNCTION IF EXISTS verifier_avis_apres_sejour()');
    }
};
