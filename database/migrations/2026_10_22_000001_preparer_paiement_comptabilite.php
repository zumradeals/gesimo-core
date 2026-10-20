<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 3 : paiement GeniusPay, comptabilité, remboursements, reversements.
 *
 * Règles gravées dans la base :
 *  - une réservation en ligne ne devient « Confirmée » qu'avec un paiement réussi couvrant son acompte ;
 *  - une réservation en ligne naît toujours « En attente » ;
 *  - chaque mouvement comptable n'existe qu'une fois (encaissement par paiement, commission et part du gérant
 *    par réservation, remboursement, reversement) : jamais deux fois la même écriture.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Paiement : informations connues dès la création chez GeniusPay -----------------------
        Schema::table('paiement', function (Blueprint $table) {
            $table->bigInteger('frais')->default(0);
            $table->string('mode', 10)->default('sandbox');
            $table->string('fournisseur', 30)->default('geniuspay');
            $table->string('reference_externe')->nullable()->unique();
            $table->string('checkout_url', 500)->nullable();
            $table->timestampTz('lien_expire_le')->nullable();
        });
        DB::statement('ALTER TABLE paiement ADD CONSTRAINT paiement_frais_valides CHECK (frais >= 0 AND frais <= montant)');
        DB::statement("ALTER TABLE paiement ADD CONSTRAINT paiement_mode_valide CHECK (mode IN ('sandbox', 'live'))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION garder_paiement() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.reference_geniuspay <> OLD.reference_geniuspay OR NEW.montant <> OLD.montant
                   OR NEW.reservation_id IS DISTINCT FROM OLD.reservation_id
                   OR NEW.mise_en_avant_id IS DISTINCT FROM OLD.mise_en_avant_id
                   OR NEW.moyen IS DISTINCT FROM OLD.moyen
                   OR NEW.donnees_brutes IS DISTINCT FROM OLD.donnees_brutes
                   OR NEW.frais <> OLD.frais OR NEW.mode <> OLD.mode OR NEW.fournisseur <> OLD.fournisseur
                   OR NEW.reference_externe IS DISTINCT FROM OLD.reference_externe
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

        // --- Messages reçus de GeniusPay : un même événement ne produit qu'un seul effet ---------------
        Schema::create('evenement_paiement', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->index();
            $table->string('evenement', 60)->nullable();
            $table->string('environnement', 10)->nullable();
            $table->boolean('signature_valide');
            $table->jsonb('corps')->nullable();
            $table->string('resultat', 80)->nullable();
            $table->timestampTz('recu_le')->useCurrent();
        });
        DB::statement('CREATE UNIQUE INDEX evenement_paiement_un_seul_effet ON evenement_paiement (reference, evenement) WHERE signature_valide');

        // --- Réservation : taux figé, motif d'annulation ----------------------------------------------
        Schema::table('reservation', function (Blueprint $table) {
            $table->decimal('commission_pourcentage', 5, 2)->nullable();
            $table->string('annulation_motif', 20)->nullable();
            $table->timestampTz('annulee_le')->nullable();
        });
        DB::statement("ALTER TABLE reservation ADD CONSTRAINT reservation_annulation_motif_valide CHECK (annulation_motif IS NULL OR annulation_motif IN ('client', 'gerant', 'absence', 'indisponible'))");
        DB::statement('ALTER TABLE reservation ADD CONSTRAINT reservation_commission_valide CHECK (commission_pourcentage IS NULL OR (commission_pourcentage >= 0 AND commission_pourcentage <= 100))');

        // Une réservation en ligne naît « En attente » ; « Confirmée » exige un paiement réussi.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION garder_confirmation_reservation() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    IF NEW.origine = 'plateforme' AND NEW.statut <> 'en_attente' THEN
                        RAISE EXCEPTION 'Une réservation en ligne commence toujours « En attente ».' USING ERRCODE = 'integrity_constraint_violation';
                    END IF;
                    IF NEW.origine = 'hors_plateforme' AND NEW.statut <> 'hors_plateforme' THEN
                        RAISE EXCEPTION 'Une réservation par téléphone commence toujours « Hors plateforme ».' USING ERRCODE = 'integrity_constraint_violation';
                    END IF;
                ELSIF NEW.statut = 'confirmee' AND OLD.statut <> 'confirmee' THEN
                    IF COALESCE((SELECT SUM(montant) FROM paiement WHERE reservation_id = NEW.id AND statut = 'reussi'), 0) < NEW.acompte THEN
                        RAISE EXCEPTION 'Une réservation ne peut être confirmée sans paiement réussi couvrant son acompte.' USING ERRCODE = 'integrity_constraint_violation';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$
        SQL);
        DB::unprepared('CREATE TRIGGER reservation_confirmation_gardee BEFORE INSERT OR UPDATE OF statut ON reservation FOR EACH ROW EXECUTE FUNCTION garder_confirmation_reservation()');

        // Transitions : un paiement tardif peut ressusciter une réservation expirée si les dates sont encore libres.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION garder_transition_statut_reservation() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                permis text[];
            BEGIN
                IF NEW.statut = OLD.statut THEN
                    RETURN NEW;
                END IF;

                IF OLD.origine = 'hors_plateforme' THEN
                    permis := ARRAY['hors_plateforme>annulee', 'hors_plateforme>terminee'];
                ELSE
                    permis := ARRAY[
                        'en_attente>confirmee', 'en_attente>expiree', 'en_attente>annulee',
                        'expiree>confirmee',
                        'confirmee>arrivee_enregistree', 'confirmee>annulee', 'confirmee>litige',
                        'arrivee_enregistree>terminee', 'arrivee_enregistree>litige',
                        'litige>terminee', 'litige>annulee'
                    ];
                END IF;

                IF NOT (OLD.statut || '>' || NEW.statut) = ANY (permis) THEN
                    RAISE EXCEPTION 'Une réservation ne peut pas passer du statut % au statut %.', OLD.statut, NEW.statut
                        USING ERRCODE = 'integrity_constraint_violation';
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        // --- Remboursement (un par paiement) et reversement (un par réservation) ------------------------------
        Schema::table('remboursement', function (Blueprint $table) {
            $table->string('reference_fournisseur')->nullable();
            $table->unsignedSmallInteger('tentatives')->default(0);
            $table->text('derniere_erreur')->nullable();
        });
        DB::statement('CREATE UNIQUE INDEX remboursement_un_par_paiement ON remboursement (paiement_id)');
        DB::statement('CREATE UNIQUE INDEX reversement_un_par_reservation ON reversement (reservation_id)');

        // --- Comptabilité : jamais deux fois la même écriture -------------------------------------------
        DB::statement("CREATE UNIQUE INDEX ecriture_un_encaissement_par_paiement ON ecriture_comptable (paiement_id) WHERE type = 'encaissement' AND correction_de IS NULL");
        DB::statement("CREATE UNIQUE INDEX ecriture_une_commission_et_une_part_par_reservation ON ecriture_comptable (reservation_id, type) WHERE type IN ('commission', 'part_gerant') AND correction_de IS NULL");
        DB::statement("CREATE UNIQUE INDEX ecriture_un_remboursement_par_remboursement ON ecriture_comptable (remboursement_id) WHERE type = 'remboursement' AND correction_de IS NULL");
        DB::statement("CREATE UNIQUE INDEX ecriture_un_reversement_par_reversement ON ecriture_comptable (reversement_id) WHERE type = 'reversement' AND correction_de IS NULL");

        // --- Alertes pour l'équipe GESIMO -------------------------------------------------------------------
        Schema::create('alerte_admin', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->foreignId('reservation_id')->nullable()->constrained('reservation');
            $table->foreignId('paiement_id')->nullable()->constrained('paiement');
            $table->foreignId('remboursement_id')->nullable()->constrained('remboursement');
            $table->text('message');
            $table->timestampTz('traitee_le')->nullable();
            $table->foreignId('traitee_par')->nullable()->constrained('utilisateur');
            $table->timestampsTz();
            $table->index(['traitee_le', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerte_admin');
        foreach (['ecriture_un_reversement_par_reversement', 'ecriture_un_remboursement_par_remboursement', 'ecriture_une_commission_et_une_part_par_reservation', 'ecriture_un_encaissement_par_paiement', 'reversement_un_par_reservation', 'remboursement_un_par_paiement'] as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
        Schema::table('remboursement', fn (Blueprint $table) => $table->dropColumn(['reference_fournisseur', 'tentatives', 'derniere_erreur']));

        // Retour à la liste de transitions du Lot 2.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION garder_transition_statut_reservation() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                permis text[];
            BEGIN
                IF NEW.statut = OLD.statut THEN
                    RETURN NEW;
                END IF;
                IF OLD.origine = 'hors_plateforme' THEN
                    permis := ARRAY['hors_plateforme>annulee', 'hors_plateforme>terminee'];
                ELSE
                    permis := ARRAY[
                        'en_attente>confirmee', 'en_attente>expiree', 'en_attente>annulee',
                        'confirmee>arrivee_enregistree', 'confirmee>annulee', 'confirmee>litige',
                        'arrivee_enregistree>terminee', 'arrivee_enregistree>litige',
                        'litige>terminee', 'litige>annulee'
                    ];
                END IF;
                IF NOT (OLD.statut || '>' || NEW.statut) = ANY (permis) THEN
                    RAISE EXCEPTION 'Une réservation ne peut pas passer du statut % au statut %.', OLD.statut, NEW.statut
                        USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                RETURN NEW;
            END;
            $$
        SQL);
        DB::unprepared('DROP TRIGGER IF EXISTS reservation_confirmation_gardee ON reservation');
        DB::unprepared('DROP FUNCTION IF EXISTS garder_confirmation_reservation()');
        DB::statement('ALTER TABLE reservation DROP CONSTRAINT IF EXISTS reservation_commission_valide');
        DB::statement('ALTER TABLE reservation DROP CONSTRAINT IF EXISTS reservation_annulation_motif_valide');
        Schema::table('reservation', fn (Blueprint $table) => $table->dropColumn(['commission_pourcentage', 'annulation_motif', 'annulee_le']));
        Schema::dropIfExists('evenement_paiement');

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
        DB::statement('ALTER TABLE paiement DROP CONSTRAINT IF EXISTS paiement_mode_valide');
        DB::statement('ALTER TABLE paiement DROP CONSTRAINT IF EXISTS paiement_frais_valides');
        Schema::table('paiement', fn (Blueprint $table) => $table->dropColumn(['frais', 'mode', 'fournisseur', 'reference_externe', 'checkout_url', 'lien_expire_le']));
    }
};
