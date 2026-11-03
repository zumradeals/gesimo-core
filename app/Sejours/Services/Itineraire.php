<?php

namespace App\Sejours\Services;

use App\Sejours\Models\Residence;

/**
 * Itinéraire du voyageur jusqu'à la résidence : simples liens vers les applications de cartes du téléphone.
 * Aucun service extérieur n'est appelé ; une position collée depuis un lien Google Maps est lue dans le lien lui-même.
 */
class Itineraire
{
    /** Hôtes de liens de cartes dont on accepte de lire la position. */
    private const HOTES = ['google.com', 'google.fr', 'google.ci', 'maps.google.com', 'waze.com', 'maps.apple.com'];

    /** @return array{0: float, 1: float}|null */
    public static function positionDepuisLien(string $lien): ?array
    {
        $lien = trim($lien);
        $parties = parse_url($lien);
        if ($parties === false || ! in_array(strtolower($parties['scheme'] ?? ''), ['http', 'https'], true) || empty($parties['host'])) {
            return null;
        }
        $hote = strtolower(preg_replace('/^www\./', '', $parties['host']));
        if (! in_array($hote, self::HOTES, true) && ! str_ends_with($hote, '.google.com')) {
            return null;
        }
        $nombre = '(-?\d{1,3}(?:\.\d+)?)';
        foreach (["/@{$nombre},{$nombre}/", "/[?&](?:q|query|ll|daddr|destination)={$nombre}(?:,|%2C){$nombre}/i", "/!3d{$nombre}!4d{$nombre}/"] as $motif) {
            if (preg_match($motif, $lien, $m) && abs((float) $m[1]) <= 90 && abs((float) $m[2]) <= 180) {
                return [(float) $m[1], (float) $m[2]];
            }
        }

        return null;
    }

    public static function aUnePosition(Residence $r): bool
    {
        return $r->latitude !== null && $r->longitude !== null;
    }

    /** @return array<string, string> Liens d'itinéraire (Google Maps, Waze, Plans) ; vide sans position. */
    public static function liens(Residence $r): array
    {
        if (! self::aUnePosition($r)) {
            return [];
        }
        $p = rtrim(rtrim(number_format((float) $r->latitude, 6, '.', ''), '0'), '.').','.rtrim(rtrim(number_format((float) $r->longitude, 6, '.', ''), '0'), '.');

        return [
            'google' => 'https://www.google.com/maps/dir/?api=1&destination='.$p,
            'waze' => 'https://waze.com/ul?ll='.$p.'&navigate=yes',
            'plans' => 'https://maps.apple.com/?daddr='.$p,
        ];
    }

    /** Message prêt à envoyer à un chauffeur : adresse, repères et lien de la carte. */
    public static function messageChauffeur(Residence $r): string
    {
        $lieu = collect([$r->adresse, $r->quartier, $r->commune])->filter()->implode(', ');
        $lignes = ["Bonjour, merci de m'amener à : {$r->nom}", $lieu];
        if ($r->reperes) {
            $lignes[] = 'Repères : '.$r->reperes;
        }
        if (self::aUnePosition($r)) {
            $lignes[] = 'Carte : '.self::liens($r)['google'];
        }

        return implode("\n", array_filter($lignes));
    }

    public static function lienWhatsapp(Residence $r): string
    {
        return 'https://wa.me/?text='.rawurlencode(self::messageChauffeur($r));
    }

    /** Distance en mètres entre deux positions (formule de haversine). */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
