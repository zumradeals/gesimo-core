<?php

namespace App\Parametres\Services;

use Illuminate\Support\Facades\Storage;

/** Identité de la plateforme (réglages Général) : nom, logo, coordonnées, réseaux sociaux, informations légales. */
class Marque
{
    public const RESEAUX = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'tiktok' => 'TikTok'];

    public static function nom(): string
    {
        return Reglages::valeur('general.nom') ?: 'GESIMO Séjours';
    }

    /** @return array{0: string, 1: string} le premier mot, puis le reste (mis en gras dans le logo) */
    public static function nomEnDeux(): array
    {
        $parts = explode(' ', self::nom(), 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    public static function logoUrl(): ?string
    {
        return self::fichier('general.logo');
    }

    public static function imageAccueilUrl(): ?string
    {
        return self::fichier('general.image_accueil');
    }

    /** Valeur d'un réglage texte, ou null si vide (pour ne rien afficher quand rien n'est renseigné). */
    public static function texte(string $cle): ?string
    {
        $v = trim((string) Parametres::obtenir($cle));

        return $v === '' ? null : $v;
    }

    public static function slogan(): ?string
    {
        return self::texte('general.slogan');
    }

    /** Lien « tel: » (chiffres et +). */
    public static function lienTelephone(?string $numero): ?string
    {
        $n = preg_replace('/[^0-9+]/', '', (string) $numero);

        return $n === '' ? null : 'tel:'.$n;
    }

    /** Lien WhatsApp (wa.me, chiffres seulement). */
    public static function lienWhatsapp(): ?string
    {
        $n = preg_replace('/\D/', '', (string) self::texte('general.whatsapp'));

        return $n === '' ? null : 'https://wa.me/'.$n;
    }

    /** @return array<string, string> réseau => adresse, seulement ceux qui sont renseignés */
    public static function reseaux(): array
    {
        $r = [];
        foreach (self::RESEAUX as $cle => $nom) {
            if ($url = self::texte("social.{$cle}")) {
                $r[$cle] = $url;
            }
        }

        return $r;
    }

    /** Ligne légale du pied de page, par exemple « SARL Exemple · RCCM CI-ABJ-2026-B-0000 · NIF 0000000A ». */
    public static function ligneLegale(): ?string
    {
        $morceaux = array_filter([
            self::texte('general.raison_sociale'),
            ($rccm = self::texte('general.rccm')) ? "RCCM {$rccm}" : null,
            ($nif = self::texte('general.nif')) ? "NIF {$nif}" : null,
        ]);

        return $morceaux === [] ? null : implode(' · ', $morceaux);
    }

    private static function fichier(string $cle): ?string
    {
        $chemin = Parametres::obtenir($cle);

        return $chemin !== null && $chemin !== '' && Storage::disk('public')->exists($chemin) ? Storage::disk('public')->url($chemin) : null;
    }
}
