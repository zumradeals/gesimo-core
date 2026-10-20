<?php

namespace App\Parametres\Services;

use Illuminate\Support\Facades\Storage;

/** Identité de la plateforme (réglages Général) : nom et logo. */
class Marque
{
    public static function nom(): string
    {
        return Reglages::valeur('general.nom') ?: 'GESIMO Séjours';
    }

    public static function logoUrl(): ?string
    {
        $chemin = Parametres::obtenir('general.logo');

        return $chemin !== null && $chemin !== '' && Storage::disk('public')->exists($chemin) ? Storage::disk('public')->url($chemin) : null;
    }
}
