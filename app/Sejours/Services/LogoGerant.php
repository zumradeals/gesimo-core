<?php

namespace App\Sejours\Services;

use App\Sejours\Models\ProfilGerant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LogoGerant
{
    public static function enregistrer(UploadedFile $fichier, ProfilGerant $profil): string
    {
        DossierGerant::autoriserProprietaire($profil);
        $contenu = file_get_contents($fichier->getRealPath());
        $infos = $contenu === false ? false : @getimagesizefromstring($contenu);
        if (! $infos || ! in_array($infos[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $infos[0] * $infos[1] > 40_000_000) {
            throw ValidationException::withMessages(['logo' => 'Choisissez une image JPEG, PNG ou WebP valide.']);
        }
        $image = @imagecreatefromstring($contenu);
        if ($image === false) {
            throw ValidationException::withMessages(['logo' => 'Cette image ne peut pas être lue.']);
        }
        $largeur = imagesx($image);
        $petite = $largeur > 480 ? imagescale($image, 480) : $image;
        $fond = imagecreatetruecolor(imagesx($petite), imagesy($petite));
        imagefill($fond, 0, 0, imagecolorallocate($fond, 255, 255, 255));
        imagecopy($fond, $petite, 0, 0, 0, 0, imagesx($petite), imagesy($petite));
        ob_start();
        imagejpeg($fond, null, 82);
        $jpeg = (string) ob_get_clean();
        $chemin = 'logos-gerants/'.$profil->id.'/'.Str::random(32).'.jpg';
        if (! Storage::disk('public')->put($chemin, $jpeg)) {
            throw ValidationException::withMessages(['logo' => 'L’image n’a pas pu être enregistrée. Réessayez.']);
        }

        return $chemin;
    }
}
