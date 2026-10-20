<?php

namespace App\Sejours\Services;

use App\Sejours\Models\Photo;
use App\Sejours\Models\Residence;
use App\Sejours\Models\Unite;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Photos : on ne garde jamais le fichier envoyé. L'image est relue, redimensionnée et réencodée
 * (les données cachées et les fichiers piégés disparaissent), en JPEG léger pour les connexions lentes.
 * Fonctionne avec l'extension PHP GD, sans bibliothèque supplémentaire.
 */
class Photos
{
    public const MAX_PAR_RESIDENCE = 8;

    public const LARGEUR_MAX = 1600;

    private const TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    public static function enregistrer(UploadedFile $fichier, Residence $residence, ?Unite $unite = null): Photo
    {
        if (Photo::where('residence_id', $residence->id)->count() >= self::MAX_PAR_RESIDENCE) {
            throw new InvalidArgumentException('Une résidence peut avoir au plus '.self::MAX_PAR_RESIDENCE.' photos.');
        }

        $contenu = file_get_contents($fichier->getRealPath());
        $infos = $contenu === false ? false : @getimagesizefromstring($contenu);
        if ($infos === false || ! in_array($infos[2], self::TYPES, true) || $infos[0] * $infos[1] > 40_000_000) {
            throw new InvalidArgumentException('Cette photo n\'est pas une image valide (JPEG, PNG ou WebP).');
        }

        $source = @imagecreatefromstring($contenu);
        if ($source === false) {
            throw new InvalidArgumentException('Cette photo n\'est pas une image valide (JPEG, PNG ou WebP).');
        }

        $source = self::redresser($source, $fichier->getRealPath(), $infos[2]);

        [$largeur, $hauteur] = [imagesx($source), imagesy($source)];
        if ($largeur > self::LARGEUR_MAX) {
            $reduite = imagescale($source, self::LARGEUR_MAX, (int) round($hauteur * self::LARGEUR_MAX / $largeur));
            if ($reduite !== false) {
                $source = $reduite;
            }
        }

        // Fond blanc sous les images transparentes.
        $fond = imagecreatetruecolor(imagesx($source), imagesy($source));
        imagefill($fond, 0, 0, imagecolorallocate($fond, 255, 255, 255));
        imagecopy($fond, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));

        ob_start();
        imagejpeg($fond, null, 80);
        $jpeg = (string) ob_get_clean();

        $chemin = "photos/{$residence->id}/".Str::random(32).'.jpg';
        Storage::disk('public')->put($chemin, $jpeg);

        return Photo::create([
            'residence_id' => $residence->id,
            'unite_id' => $unite?->id,
            'chemin' => $chemin,
            'ordre' => (int) Photo::where('residence_id', $residence->id)->max('ordre') + 1,
        ]);
    }

    /** Les téléphones notent le sens de la photo dans les données EXIF (effacées au réencodage) : on la remet droite avant. */
    private static function redresser(\GdImage $image, string $chemin, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = @exif_read_data($chemin)['Orientation'] ?? 1;
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }

    public static function supprimer(Photo $photo): void
    {
        Storage::disk('public')->delete($photo->chemin);
        $photo->delete();
    }
}
