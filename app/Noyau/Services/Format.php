<?php

namespace App\Noyau\Services;

use Carbon\CarbonInterface;

class Format
{
    /** 55000 → « 55 000 FCFA » (espaces insécables : le montant ne se coupe jamais en deux lignes). */
    public static function fcfa(int $montant): string
    {
        return number_format($montant, 0, ',', "\u{202F}")."\u{00A0}FCFA";
    }

    public static function date(CarbonInterface $date): string
    {
        return $date->translatedFormat('D j M Y');
    }
}
