<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tout l'argent. Montants en FCFA, entiers, strictement positifs.
 * Aucune suppression. Les écritures comptables ne changent jamais ; les autres tables
 * ne laissent avancer que leur statut (dans le sens prévu) ; une correction = une nouvelle écriture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mise_en_avant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residence_id')->constrained('residence');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->bigInteger('prix');
            $table->string('statut', 25)->default('en_attente_paiement');
            $table->timestampsTz();
            $table->index(['residence_id', 'statut']);
        });
        DB::statement('ALTER TABLE mise_en_avant ADD CONSTRAINT mise_en_avant_prix_positif CHECK (prix > 0)');
        DB::statement('ALTER TABLE mise_en_avant ADD CONSTRAINT mise_en_avant_dates_valides CHECK (date_fin >= date_debut)');
        DB::statement("ALTER TABLE mise_en_avant ADD CONSTRAINT mise_en_avant_statut_valide CHECK (statut IN ('en_attente_paiement', 'active', 'terminee', 'annulee'))");

        Schema::create('paiement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->nullable()->constrained('reservation');
            $table->foreignId('mise_en_avant_id')->nullable()->constrained('mise_en_avant');
            // Idempotence : une référence n'est enregistrée qu'une seule fois (RG-12).
            $table->string('reference_geniuspay')->unique();
            $table->bigInteger('montant');
            $table->string('moyen', 20)->nullable();
            $table->string('statut', 20)->default('en_attente');
            $table->jsonb('donnees_brutes')->nullable();
            $table->timestampsTz();
            $table->index('reservation_id');
            $table->index('mise_en_avant_id');
        });
        DB::statement('ALTER TABLE paiement ADD CONSTRAINT paiement_montant_positif CHECK (montant > 0)');
        DB::statement("ALTER TABLE paiement ADD CONSTRAINT paiement_statut_valide CHECK (statut IN ('en_attente', 'reussi', 'echoue'))");
        DB::statement("ALTER TABLE paiement ADD CONSTRAINT paiement_moyen_valide CHECK (moyen IS NULL OR moyen IN ('mobile_money', 'carte'))");
        DB::statement('ALTER TABLE paiement ADD CONSTRAINT paiement_une_seule_cible CHECK ((reservation_id IS NULL) <> (mise_en_avant_id IS NULL))');

        Schema::create('reversement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gerant_id')->constrained('utilisateur');
            $table->foreignId('reservation_id')->constrained('reservation');
            $table->bigInteger('montant');
            $table->string('statut', 20)->default('a_payer');
            $table->timestampTz('paye_le')->nullable();
            $table->foreignId('paye_par')->nullable()->constrained('utilisateur');
            $table->string('reference_mobile_money')->nullable();
            $table->timestampsTz();
            $table->index('gerant_id');
            $table->index('reservation_id');
        });
        DB::statement('ALTER TABLE reversement ADD CONSTRAINT reversement_montant_positif CHECK (montant > 0)');
        DB::statement("ALTER TABLE reversement ADD CONSTRAINT reversement_statut_valide CHECK (statut IN ('a_payer', 'paye'))");
        DB::statement("ALTER TABLE reversement ADD CONSTRAINT reversement_paye_complet CHECK ((statut = 'paye') = (paye_le IS NOT NULL AND paye_par IS NOT NULL AND reference_mobile_money IS NOT NULL))");

        Schema::create('remboursement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservation');
            $table->foreignId('paiement_id')->constrained('paiement');
            $table->bigInteger('montant');
            $table->text('motif');
            $table->string('statut', 20)->default('en_attente');
            $table->timestampTz('effectue_le')->nullable();
            $table->timestampsTz();
            $table->index('reservation_id');
            $table->index('paiement_id');
        });
        DB::statement('ALTER TABLE remboursement ADD CONSTRAINT remboursement_montant_positif CHECK (montant > 0)');
        DB::statement("ALTER TABLE remboursement ADD CONSTRAINT remboursement_statut_valide CHECK (statut IN ('en_attente', 'effectue', 'echoue'))");

        Schema::create('ecriture_comptable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->nullable()->constrained('reservation');
            $table->foreignId('paiement_id')->nullable()->constrained('paiement');
            $table->foreignId('mise_en_avant_id')->nullable()->constrained('mise_en_avant');
            $table->foreignId('reversement_id')->nullable()->constrained('reversement');
            $table->foreignId('remboursement_id')->nullable()->constrained('remboursement');
            $table->string('type', 20);
            $table->bigInteger('montant');
            // Une correction est une nouvelle écriture qui renvoie vers celle qu'elle corrige.
            $table->foreignId('correction_de')->nullable()->constrained('ecriture_comptable');
            $table->text('motif')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('utilisateur');
            $table->timestampTz('created_at')->useCurrent();
            $table->index('reservation_id');
            $table->index('paiement_id');
            $table->index(['type', 'created_at']);
        });
        DB::statement('ALTER TABLE ecriture_comptable ADD CONSTRAINT ecriture_montant_positif CHECK (montant > 0)');
        DB::statement("ALTER TABLE ecriture_comptable ADD CONSTRAINT ecriture_type_valide CHECK (type IN ('encaissement', 'commission', 'part_gerant', 'remboursement', 'reversement', 'mise_en_avant'))");
        DB::statement('ALTER TABLE ecriture_comptable ADD CONSTRAINT ecriture_a_une_cible CHECK (reservation_id IS NOT NULL OR paiement_id IS NOT NULL OR mise_en_avant_id IS NOT NULL OR reversement_id IS NOT NULL OR remboursement_id IS NOT NULL)');

        // --- Gardes ---------------------------------------------------------------------------
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION garder_paiement() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.reference_geniuspay <> OLD.reference_geniuspay OR NEW.montant <> OLD.montant
                   OR NEW.reservation_id IS DISTINCT FROM OLD.reservation_id
                   OR NEW.mise_en_avant_id IS DISTINCT FROM OLD.mise_en_avant_id
                   OR NEW.moyen IS DISTINCT FROM OLD.moyen
                   OR NEW.donnees_brutes IS DISTINCT FROM OLD.donnees_brutes
                   OR NEW.created_at <> OLD.created_at THEN
                    RAISE EXCEPTION 'Un paiement ne se modifie pas : seul son statut peut avancer.' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                IF NEW.statut <> OLD.statut AND NOT (OLD.statut = 'en_attente' AND NEW.statut IN ('reussi', 'echoue')) THEN
                    RAISE EXCEPTION 'Le statut d''un paiement ne peut pas passer de % à %.', OLD.statut, NEW.statut USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END;
            $$
        SQL);
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION garder_reversement() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.gerant_id <> OLD.gerant_id OR NEW.reservation_id <> OLD.reservation_id OR NEW.montant <> OLD.montant
                   OR NEW.created_at <> OLD.created_at THEN
                    RAISE EXCEPTION 'Un reversement ne se modifie pas : seul son paiement peut être enregistré.' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                IF OLD.statut = 'paye' AND (NEW.statut <> 'paye' OR NEW.paye_le IS DISTINCT FROM OLD.paye_le
                   OR NEW.paye_par IS DISTINCT FROM OLD.paye_par OR NEW.reference_mobile_money IS DISTINCT FROM OLD.reference_mobile_money) THEN
                    RAISE EXCEPTION 'Un reversement déjà payé ne se modifie plus.' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END;
            $$
        SQL);
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION garder_remboursement() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.reservation_id <> OLD.reservation_id OR NEW.paiement_id <> OLD.paiement_id OR NEW.montant <> OLD.montant
                   OR NEW.motif <> OLD.motif OR NEW.created_at <> OLD.created_at THEN
                    RAISE EXCEPTION 'Un remboursement ne se modifie pas : seul son statut peut avancer.' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                IF NEW.statut <> OLD.statut AND NOT (OLD.statut = 'en_attente' AND NEW.statut IN ('effectue', 'echoue')) THEN
                    RAISE EXCEPTION 'Le statut d''un remboursement ne peut pas passer de % à %.', OLD.statut, NEW.statut USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END;
            $$
        SQL);
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION garder_mise_en_avant() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.residence_id <> OLD.residence_id OR NEW.prix <> OLD.prix OR NEW.date_debut <> OLD.date_debut
                   OR NEW.date_fin <> OLD.date_fin OR NEW.created_at <> OLD.created_at THEN
                    RAISE EXCEPTION 'Une mise en avant ne se modifie pas : seul son statut peut avancer.' USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END;
            $$
        SQL);

        foreach (['paiement' => 'garder_paiement', 'reversement' => 'garder_reversement', 'remboursement' => 'garder_remboursement', 'mise_en_avant' => 'garder_mise_en_avant'] as $nomTable => $fonction) {
            DB::unprepared("CREATE TRIGGER {$nomTable}_gardee BEFORE UPDATE ON {$nomTable} FOR EACH ROW EXECUTE FUNCTION {$fonction}()");
            DB::unprepared("CREATE TRIGGER {$nomTable}_sans_suppression BEFORE DELETE OR TRUNCATE ON {$nomTable} FOR EACH STATEMENT EXECUTE FUNCTION interdire_suppression()");
        }
        foreach (['paiement', 'reversement', 'remboursement', 'ecriture_comptable'] as $nomTable) {
            DB::unprepared("CREATE TRIGGER {$nomTable}_pas_hors_plateforme BEFORE INSERT ON {$nomTable} FOR EACH ROW EXECUTE FUNCTION refuser_si_hors_plateforme()");
        }
        DB::unprepared('CREATE TRIGGER ecriture_comptable_immuable BEFORE UPDATE OR DELETE ON ecriture_comptable FOR EACH ROW EXECUTE FUNCTION interdire_modification()');
        DB::unprepared('CREATE TRIGGER ecriture_comptable_sans_vidage BEFORE TRUNCATE ON ecriture_comptable FOR EACH STATEMENT EXECUTE FUNCTION interdire_modification()');
    }

    public function down(): void
    {
        foreach (['ecriture_comptable', 'remboursement', 'reversement', 'paiement', 'mise_en_avant'] as $nomTable) {
            Schema::dropIfExists($nomTable);
        }
        foreach (['garder_mise_en_avant', 'garder_remboursement', 'garder_reversement', 'garder_paiement'] as $fonction) {
            DB::unprepared("DROP FUNCTION IF EXISTS {$fonction}()");
        }
    }
};
