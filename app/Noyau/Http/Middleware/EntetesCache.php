<?php

namespace App\Noyau\Http\Middleware;

use App\Noyau\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dit au téléphone ce qu'il a le droit d'enregistrer pour le mode hors connexion (Lot 6) :
 *  - « public »  : pages ouvertes à tous, pour un visiteur non connecté ;
 *  - « prive »   : « Mes réservations » (et pages publiques) d'un CLIENT connecté — copie effacée à la déconnexion ;
 *  - sinon       : rien (Cache-Control: no-store) — paiement, reçus, pièces d'identité, argent, écrans gérant et admin.
 * Le service worker n'enregistre que ce qui porte l'un des deux marquages.
 */
class EntetesCache
{
    private const PUBLIQUES = ['accueil', 'recherche', 'logement', 'faq', 'a-propos', 'conditions', 'confidentialite', 'devenir-partenaire', 'contact'];

    private const PRIVEES_CLIENT = ['mes-reservations', 'reservation.detail'];

    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);
        $route = $request->route()?->getName();

        $marque = null;
        if ($request->isMethod('GET') && $reponse->getStatusCode() === 200 && ! $request->headers->has('X-Livewire')) {
            $u = $request->user();
            if ($u === null && in_array($route, self::PUBLIQUES, true)) {
                $marque = 'public';
            } elseif ($u !== null && $this->estClientSeulement($u) && (in_array($route, self::PUBLIQUES, true) || in_array($route, self::PRIVEES_CLIENT, true))) {
                $marque = 'prive';
            }
        }

        if ($marque !== null) {
            $reponse->headers->set('X-Gesimo-Cache', $marque);
            $reponse->headers->set('Cache-Control', 'private, no-cache');
        } elseif ($route !== null && ! str_starts_with($route, 'pwa.') || $request->is('livewire*')) {
            $reponse->headers->set('Cache-Control', 'no-store, private');
        }

        return $reponse;
    }

    /** Un compte qui est aussi gérant, personnel ou admin voit des données de tiers : il n'a pas de copie hors connexion. */
    private function estClientSeulement($utilisateur): bool
    {
        return $utilisateur->aRole(Role::Client) && ! $utilisateur->aRole(Role::Gerant) && ! $utilisateur->aRole(Role::Personnel) && ! $utilisateur->estAdmin();
    }
}
