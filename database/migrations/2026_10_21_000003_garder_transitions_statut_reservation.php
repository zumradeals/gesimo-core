<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les changements de statut d'une réservation ne sont permis que dans le sens prévu.
 * La même liste existe dans App\Sejours\Enums\StatutReservation (un test vérifie qu'elles concordent).
 */
return new class extends Migration
{
    public function up(): void
    {
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
        DB::unprepared('CREATE TRIGGER reservation_transition_statut BEFORE UPDATE OF statut ON reservation FOR EACH ROW EXECUTE FUNCTION garder_transition_statut_reservation()');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS reservation_transition_statut ON reservation');
        DB::unprepared('DROP FUNCTION IF EXISTS garder_transition_statut_reservation()');
    }
};
