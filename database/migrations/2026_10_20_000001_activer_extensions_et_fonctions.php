<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extension PostgreSQL nécessaire à l'interdiction de chevauchement de dates,
 * et fonctions génériques réutilisées par les déclencheurs des tables financières.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE EXTENSION IF NOT EXISTS btree_gist');

        // Écritures et journaux : ni modification ni suppression, jamais.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION interdire_modification() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'La table % est en ajout seul : aucune modification ni suppression n''est permise. Faites une nouvelle écriture.', TG_TABLE_NAME
                    USING ERRCODE = 'integrity_constraint_violation';
            END;
            $$
        SQL);

        // Interdit toute suppression (tables financières dont seul le statut avance).
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION interdire_suppression() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'La table % ne permet aucune suppression.', TG_TABLE_NAME
                    USING ERRCODE = 'integrity_constraint_violation';
            END;
            $$
        SQL);

        // Réservation « hors plateforme » : aucun paiement, aucune écriture, aucune commission.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION refuser_si_hors_plateforme() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                id_reservation bigint := (to_jsonb(NEW) ->> 'reservation_id')::bigint;
            BEGIN
                IF id_reservation IS NOT NULL AND EXISTS (
                    SELECT 1 FROM reservation WHERE id = id_reservation AND origine = 'hors_plateforme'
                ) THEN
                    RAISE EXCEPTION 'Une réservation hors plateforme ne peut avoir ni paiement, ni écriture, ni commission.'
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS refuser_si_hors_plateforme()');
        DB::unprepared('DROP FUNCTION IF EXISTS interdire_suppression()');
        DB::unprepared('DROP FUNCTION IF EXISTS interdire_modification()');
        DB::unprepared('DROP EXTENSION IF EXISTS btree_gist');
    }
};
