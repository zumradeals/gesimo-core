<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un même message (modèle + réservation + destinataire) n'est écrit qu'une fois : un webhook rejoué n'envoie pas deux e-mails.
        Schema::table('notification', function (Blueprint $table) {
            $table->string('modele', 60)->nullable();
            $table->foreignId('reservation_id')->nullable()->constrained('reservation');
        });
        DB::statement('CREATE UNIQUE INDEX notification_unique_par_reservation ON notification (modele, reservation_id, destinataire_id) WHERE modele IS NOT NULL AND reservation_id IS NOT NULL');

        // Litige : on garde le statut d'avant pour pouvoir le rétablir à la clôture ; un seul litige ouvert par personne et par réservation.
        Schema::table('litige', function (Blueprint $table) {
            $table->string('statut_avant', 30)->nullable();
        });
        DB::statement("CREATE UNIQUE INDEX litige_un_ouvert_par_personne ON litige (reservation_id, ouvert_par) WHERE statut = 'ouvert'");

        Schema::create('message_contact', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 120);
            $table->string('contact', 190);
            $table->text('message');
            $table->string('adresse_ip', 64)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        $this->poserTransitions(true);
    }

    public function down(): void
    {
        $this->poserTransitions(false);
        Schema::dropIfExists('message_contact');
        DB::statement('DROP INDEX IF EXISTS litige_un_ouvert_par_personne');
        Schema::table('litige', fn (Blueprint $t) => $t->dropColumn('statut_avant'));
        DB::statement('DROP INDEX IF EXISTS notification_unique_par_reservation');
        Schema::table('notification', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_id');
            $table->dropColumn('modele');
        });
    }

    /** Ajoute (ou retire) le retour d'un litige vers le statut d'avant. Même liste que StatutReservation. */
    private function poserTransitions(bool $avecRetour): void
    {
        $retour = $avecRetour ? ", 'litige>confirmee', 'litige>arrivee_enregistree'" : '';
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION garder_transition_statut_reservation() RETURNS trigger LANGUAGE plpgsql AS \$\$
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
                        'litige>terminee', 'litige>annulee'{$retour}
                    ];
                END IF;

                IF NOT (OLD.statut || '>' || NEW.statut) = ANY (permis) THEN
                    RAISE EXCEPTION 'Une réservation ne peut pas passer du statut % au statut %.', OLD.statut, NEW.statut
                        USING ERRCODE = 'integrity_constraint_violation';
                END IF;

                RETURN NEW;
            END;
            \$\$
        SQL);
    }
};
