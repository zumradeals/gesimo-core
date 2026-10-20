<?php

namespace App\Noyau\Services;

/** Numéros de téléphone : on garde seulement les 10 chiffres ivoiriens, sans espace ni indicatif. */
class Telephone
{
    public static function normaliser(string $numero): string
    {
        $chiffres = preg_replace('/\D+/', '', $numero) ?? '';

        foreach (['00225', '225'] as $indicatif) {
            if (str_starts_with($chiffres, $indicatif) && strlen($chiffres) > 10) {
                return substr($chiffres, strlen($indicatif));
            }
        }

        return $chiffres;
    }

    public static function estValide(string $numeroNormalise): bool
    {
        return (bool) preg_match('/^\d{10}$/', $numeroNormalise);
    }

    public static function afficher(string $numeroNormalise): string
    {
        return trim(chunk_split($numeroNormalise, 2, ' '));
    }
}
