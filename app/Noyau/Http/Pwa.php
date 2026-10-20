<?php

namespace App\Noyau\Http;

use App\Parametres\Services\Marque;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** Application installable (PWA) : manifeste, service worker versionné et page « hors connexion ». */
class Pwa
{
    public function manifeste(): JsonResponse
    {
        $nom = Marque::nom();

        return response()->json([
            'id' => '/',
            'name' => $nom,
            'short_name' => Str::limit(explode(' ', $nom)[0], 12, ''),
            'description' => 'Réservez une résidence meublée en Côte d\'Ivoire.',
            'lang' => 'fr',
            'start_url' => '/?source=app',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#0f766e',
            'theme_color' => '#0f766e',
            'icons' => [
                ['src' => '/icons/icone-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icone-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icone-masquable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }

    /**
     * Le service worker est servi par l'application pour recevoir un numéro de version qui change à chaque mise en ligne
     * (empreinte du site compilé + du service worker lui-même) : c'est ce qui déclenche « Une nouvelle version est disponible ».
     */
    public function serviceWorker(): Response
    {
        $source = (string) file_get_contents(resource_path('pwa/sw.js'));
        $site = is_file(public_path('build/manifest.json')) ? (string) file_get_contents(public_path('build/manifest.json')) : '';
        $version = substr(md5($source.$site), 0, 12);

        return response(str_replace('__VERSION__', $version, $source), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache, max-age=0',
        ]);
    }

    /** Page affichée quand le réseau est coupé et que la page demandée n'est pas enregistrée. Aucune donnée personnelle. */
    public function horsConnexion(): Response
    {
        return response()->view('pwa.hors-connexion', ['nom' => Marque::nom()])->header('Cache-Control', 'public, max-age=300');
    }
}
