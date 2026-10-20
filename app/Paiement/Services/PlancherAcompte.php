<?php

namespace App\Paiement\Services;

use App\Parametres\Services\Parametres;

/**
 * RG-14 : le pourcentage d'acompte ne peut pas être inférieur à « commission + frais de paiement ».
 * Tant que ces deux réglages ne sont pas saisis dans les Paramètres, le plancher est inconnu et
 * l'option « acompte » n'est pas proposée (seul « tout payer en ligne » est possible).
 */
class PlancherAcompte
{
    /** Pourcentage minimum (entier, arrondi au-dessus), ou null si commission et frais ne sont pas réglés. */
    public static function pourcentageMinimum(): ?int
    {
        return self::calculer(Parametres::obtenir('commission.pourcentage'), Parametres::obtenir('paiement.frais_pourcentage'));
    }

    /** Plancher pour des valeurs données (pour contrôler un changement avant de l'enregistrer). */
    public static function calculer(mixed $commission, mixed $frais): ?int
    {
        if ($commission === null || $frais === null || ! is_numeric($commission) || ! is_numeric($frais)) {
            return null;
        }

        return (int) min(100, ceil((float) $commission + (float) $frais));
    }

    public static function acompteEstPossible(): bool
    {
        return self::pourcentageMinimum() !== null;
    }

    /** Acompte en FCFA entiers : arrondi au-dessus, jamais plus que le total. */
    public static function montant(int $total, int $pourcentage): int
    {
        return min($total, (int) ceil($total * $pourcentage / 100));
    }
}
