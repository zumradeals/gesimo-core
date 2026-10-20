<?php

namespace App\Noyau\Http\Middleware;

use App\Parametres\Services\Reglages;
use Closure;
use Illuminate\Http\Request;
use Throwable;

/**
 * Applique à chaque requête les réglages de la base qui doivent précéder la session (durée de session).
 * Un changement dans les Paramètres joue donc tout de suite, sans redémarrer le service.
 */
class AppliquerReglages
{
    public function handle(Request $request, Closure $next)
    {
        try {
            config(['session.lifetime' => Reglages::entier('securite.session_minutes')]);
        } catch (Throwable) {
            // Base pas encore prête (première installation) : la valeur par défaut du framework reste en place.
        }

        return $next($request);
    }
}
