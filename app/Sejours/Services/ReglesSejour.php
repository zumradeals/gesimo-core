<?php

namespace App\Sejours\Services;

use App\Paiement\Services\ReglagesPaiement;
use App\Parametres\Services\Reglages;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Règles de durée et d'horaires d'un séjour, lues dans les Paramètres (rubrique « Réservation »).
 * Un seul endroit pour la fiche du logement, le récapitulatif et le service de réservation.
 */
class ReglesSejour
{
    public static function nuitsMin(): int
    {
        return max(1, Reglages::entier('reservation.sejour_min_nuits'));
    }

    public static function nuitsMax(): int
    {
        return max(self::nuitsMin(), Reglages::entier('reservation.sejour_max_nuits'));
    }

    /** Heure de départ au format « 11:00 ». */
    public static function heureDepart(): string
    {
        $heure = (string) Reglages::valeur('reservation.heure_depart');

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $heure) ? $heure : '11:00';
    }

    /** Heure d'arrivée standard au format « 14:00 » (le même réglage sert au calcul du délai d'annulation). */
    public static function heureArrivee(): string
    {
        return ReglagesPaiement::heureArrivee();
    }

    /** Jours pendant lesquels le gérant peut ouvrir la pièce d'identité après le départ. */
    public static function accesPieceJours(): int
    {
        return max(0, Reglages::entier('reservation.piece_acces_jours'));
    }

    /** « 14:00 » devient « 14 h », « 08:30 » devient « 8 h 30 ». */
    public static function heureLisible(string $heure): string
    {
        [$h, $m] = array_map('intval', explode(':', $heure) + [1 => 0]);

        return $m === 0 ? "{$h} h" : sprintf('%d h %02d', $h, $m);
    }

    /** Dernier instant pour annuler sans frais : l'arrivée (à l'heure standard) moins le délai réglé. */
    public static function limiteAnnulationGratuite(CarbonInterface $arrivee, int $delaiHeures, string $heureArrivee): CarbonImmutable
    {
        [$h, $m] = array_map('intval', explode(':', $heureArrivee) + [1 => 0]);

        return CarbonImmutable::parse($arrivee)->startOfDay()->setTime($h, $m)->subHours($delaiHeures);
    }
}
