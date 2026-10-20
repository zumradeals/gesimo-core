<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Réservations, blocages de calendrier, check-in, et l'interdiction de chevauchement (RG-1).
 *
 * Une réservation occupe les nuits [arrivée ; départ[ : le jour du départ, l'unité est
 * de nouveau libre pour une arrivée. Un blocage fonctionne de la même façon.
 *
 * Une contrainte d'exclusion PostgreSQL ne surveille qu'une seule table. Pour couvrir
 * réservations ET blocages ensemble, la table interne « occupation_unite » reçoit une ligne
 * pour chaque réservation active et chaque blocage (tenue à jour par des déclencheurs),
 * et porte l'unique contrainte d'exclusion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unite_id')->constrained('unite');
            // Vide pour une réservation prise par téléphone (hors plateforme).
            $table->foreignId('client_id')->nullable()->constrained('utilisateur');
            $table->string('nom_client')->nullable();
            $table->string('telephone_client', 30)->nullable();
            $table->date('date_arrivee');
            $table->date('date_depart');
            $table->integer('nombre_nuits')->storedAs('date_depart - date_arrivee');
            $table->bigInteger('montant_total');
            $table->bigInteger('acompte')->default(0);
            $table->bigInteger('solde')->default(0);
            $table->string('statut', 25)->default('en_attente');
            $table->string('origine', 20)->default('plateforme');
            $table->timestampTz('expire_le')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('utilisateur');
            $table->timestampsTz();
            $table->index(['unite_id', 'date_arrivee']);
            $table->index('client_id');
            $table->index(['statut', 'expire_le']);
        });
        DB::statement('ALTER TABLE reservation ADD CONSTRAINT reservation_dates_valides CHECK (date_depart > date_arrivee)');
        DB::statement("ALTER TABLE reservation ADD CONSTRAINT reservation_statut_valide CHECK (statut IN ('en_attente', 'confirmee', 'arrivee_enregistree', 'terminee', 'annulee', 'expiree', 'litige', 'hors_plateforme'))");
        DB::statement("ALTER TABLE reservation ADD CONSTRAINT reservation_origine_valide CHECK (origine IN ('plateforme', 'hors_plateforme'))");
        DB::statement('ALTER TABLE reservation ADD CONSTRAINT reservation_montants_entiers_positifs CHECK (montant_total >= 0 AND acompte >= 0 AND solde >= 0 AND montant_total = acompte + solde)');
        DB::statement("ALTER TABLE reservation ADD CONSTRAINT reservation_plateforme_complete CHECK (origine <> 'plateforme' OR (client_id IS NOT NULL AND montant_total > 0 AND statut <> 'hors_plateforme'))");
        // RG-5 : hors plateforme = gratuite, sans paiement en ligne ni commission ; elle bloque seulement le calendrier.
        DB::statement("ALTER TABLE reservation ADD CONSTRAINT reservation_hors_plateforme_sans_argent CHECK (origine <> 'hors_plateforme' OR (nom_client IS NOT NULL AND montant_total = 0 AND acompte = 0 AND solde = 0 AND statut IN ('hors_plateforme', 'annulee', 'terminee')))");
        DB::statement("ALTER TABLE reservation ADD CONSTRAINT reservation_attente_a_une_expiration CHECK (statut <> 'en_attente' OR expire_le IS NOT NULL)");

        Schema::create('blocage_calendrier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unite_id')->constrained('unite')->cascadeOnDelete();
            // date_fin = premier jour libre (même logique que le départ d'une réservation).
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('motif')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('utilisateur');
            $table->timestampsTz();
            $table->index(['unite_id', 'date_debut']);
        });
        DB::statement('ALTER TABLE blocage_calendrier ADD CONSTRAINT blocage_dates_valides CHECK (date_fin > date_debut)');

        Schema::create('checkin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained('reservation');
            $table->timestampTz('arrivee_le');
            $table->string('piece_type', 30)->nullable();
            // Chiffré par l'application (donnée personnelle sensible).
            $table->text('piece_numero')->nullable();
            // Fichier de la pièce : stockage privé uniquement.
            $table->string('piece_chemin')->nullable();
            $table->foreignId('enregistre_par')->constrained('utilisateur');
            $table->timestampTz('depart_le')->nullable();
            $table->foreignId('depart_enregistre_par')->nullable()->constrained('utilisateur');
            $table->timestampsTz();
        });

        // --- Interdiction de chevauchement -------------------------------------------------
        Schema::create('occupation_unite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unite_id')->constrained('unite')->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->unique()->constrained('reservation')->cascadeOnDelete();
            $table->foreignId('blocage_id')->nullable()->unique()->constrained('blocage_calendrier')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE occupation_unite ADD COLUMN periode daterange NOT NULL');
        DB::statement('ALTER TABLE occupation_unite ADD CONSTRAINT occupation_une_seule_source CHECK ((reservation_id IS NULL) <> (blocage_id IS NULL))');
        DB::statement('ALTER TABLE occupation_unite ADD CONSTRAINT occupation_sans_chevauchement EXCLUDE USING gist (unite_id WITH =, periode WITH &&)');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION synchroniser_occupation_reservation() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.statut IN ('en_attente', 'confirmee', 'arrivee_enregistree', 'hors_plateforme') THEN
                    UPDATE occupation_unite
                       SET unite_id = NEW.unite_id, periode = daterange(NEW.date_arrivee, NEW.date_depart, '[)')
                     WHERE reservation_id = NEW.id;
                    IF NOT FOUND THEN
                        INSERT INTO occupation_unite (unite_id, reservation_id, periode)
                        VALUES (NEW.unite_id, NEW.id, daterange(NEW.date_arrivee, NEW.date_depart, '[)'));
                    END IF;
                ELSE
                    DELETE FROM occupation_unite WHERE reservation_id = NEW.id;
                END IF;
                RETURN NEW;
            END;
            $$
        SQL);
        DB::unprepared('CREATE TRIGGER reservation_occupation AFTER INSERT OR UPDATE ON reservation FOR EACH ROW EXECUTE FUNCTION synchroniser_occupation_reservation()');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION synchroniser_occupation_blocage() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                UPDATE occupation_unite
                   SET unite_id = NEW.unite_id, periode = daterange(NEW.date_debut, NEW.date_fin, '[)')
                 WHERE blocage_id = NEW.id;
                IF NOT FOUND THEN
                    INSERT INTO occupation_unite (unite_id, blocage_id, periode)
                    VALUES (NEW.unite_id, NEW.id, daterange(NEW.date_debut, NEW.date_fin, '[)'));
                END IF;
                RETURN NEW;
            END;
            $$
        SQL);
        DB::unprepared('CREATE TRIGGER blocage_occupation AFTER INSERT OR UPDATE ON blocage_calendrier FOR EACH ROW EXECUTE FUNCTION synchroniser_occupation_blocage()');

        // --- Montants d'une réservation : figés après création (règle financière) ---------------
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION figer_montants_reservation() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.montant_total <> OLD.montant_total OR NEW.acompte <> OLD.acompte OR NEW.solde <> OLD.solde OR NEW.origine <> OLD.origine THEN
                    RAISE EXCEPTION 'Les montants et l''origine d''une réservation ne se modifient pas.'
                        USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END;
            $$
        SQL);
        DB::unprepared('CREATE TRIGGER reservation_montants_figes BEFORE UPDATE ON reservation FOR EACH ROW EXECUTE FUNCTION figer_montants_reservation()');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS reservation_montants_figes ON reservation');
        DB::unprepared('DROP FUNCTION IF EXISTS figer_montants_reservation()');
        DB::unprepared('DROP TRIGGER IF EXISTS blocage_occupation ON blocage_calendrier');
        DB::unprepared('DROP FUNCTION IF EXISTS synchroniser_occupation_blocage()');
        DB::unprepared('DROP TRIGGER IF EXISTS reservation_occupation ON reservation');
        DB::unprepared('DROP FUNCTION IF EXISTS synchroniser_occupation_reservation()');
        Schema::dropIfExists('occupation_unite');
        Schema::dropIfExists('checkin');
        Schema::dropIfExists('blocage_calendrier');
        Schema::dropIfExists('reservation');
    }
};
