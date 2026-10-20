<?php

namespace App\Noyau\Http\Middleware;

use App\Noyau\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage : ->middleware('role:admin') ou 'role:gerant,admin' (l'un OU l'autre).
 * Refus 403 côté serveur si la personne est connectée sans le rôle ; 401 si elle ne l'est pas.
 */
class VerifierRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $utilisateur = $request->user();

        abort_if($utilisateur === null, 401, 'Veuillez vous connecter.');
        abort_unless($utilisateur->estActif(), 403, 'Ce compte est suspendu.');

        foreach ($roles as $nom) {
            if ($utilisateur->aRole(Role::from($nom))) {
                return $next($request);
            }
        }

        abort(403, "Vous n'avez pas le droit d'accéder à cette page.");
    }
}
