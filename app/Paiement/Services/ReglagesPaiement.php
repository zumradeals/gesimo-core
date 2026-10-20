<?php

namespace App\Paiement\Services;

use App\Parametres\Services\Parametres;

/** Réglages d'argent lus dans les Paramètres (jamais dans un fichier technique). */
class ReglagesPaiement
{
    /** Pourcentage de commission, ou null tant que GESIMO ne l'a pas fixé (0 est une valeur valide). */
    public static function commissionPourcentage(): ?float
    {
        $valeur = Parametres::obtenir('commission.pourcentage');

        return is_numeric($valeur) ? (float) $valeur : null;
    }

    /** Délai d'annulation gratuite en heures avant l'arrivée (RG-6) : 24 par défaut. */
    public static function delaiAnnulationGratuiteHeures(): int
    {
        $heures = Parametres::obtenir('annulation.delai_gratuit_heures', '24');

        return is_numeric($heures) && (int) $heures >= 0 ? (int) $heures : 24;
    }

    /** Heure d'arrivée supposée pour calculer le délai : 14 h par défaut. */
    public static function heureArrivee(): string
    {
        $heure = Parametres::obtenir('annulation.heure_arrivee', '14:00');

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $heure) ? $heure : '14:00';
    }
}
