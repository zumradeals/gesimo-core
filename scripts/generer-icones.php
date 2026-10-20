<?php

/**
 * Génère les icônes de l'application (un « G » blanc sur fond vert GESIMO). À relancer seulement pour changer l'icône :
 *   php scripts/generer-icones.php
 * Les fichiers produits sont dans public/icons (ils sont versionnés : le déploiement n'a rien à générer).
 */
function icone(int $taille, bool $masquable, string $chemin, bool $arrondie = true): void
{
    $im = imagecreatetruecolor($taille, $taille);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $transparent);

    // Dégradé vert GESIMO (#0f766e -> #1aa395), en diagonale.
    $rayon = $arrondie && ! $masquable ? (int) round($taille * 0.22) : 0;
    for ($y = 0; $y < $taille; $y++) {
        for ($x = 0; $x < $taille; $x++) {
            $t = ($x + $y) / (2 * $taille);
            $r = (int) (15 + (26 - 15) * $t);
            $g = (int) (118 + (163 - 118) * $t);
            $b = (int) (110 + (149 - 110) * $t);
            $dedans = true;
            if ($rayon > 0) {
                $cx = $x < $rayon ? $rayon : ($x >= $taille - $rayon ? $taille - $rayon - 1 : $x);
                $cy = $y < $rayon ? $rayon : ($y >= $taille - $rayon ? $taille - $rayon - 1 : $y);
                $dedans = (($x - $cx) ** 2 + ($y - $cy) ** 2) <= $rayon ** 2;
            }
            if ($dedans) {
                imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, $r, $g, $b, 0));
            }
        }
    }

    // Le « G » : un anneau ouvert à droite avec une barre, dessiné en blanc. Pour une icône « masquable », il reste dans la zone sûre (80 %).
    imagealphablending($im, true);
    $blanc = imagecolorallocate($im, 255, 255, 255);
    $zone = $masquable ? 0.40 : 0.50;
    $rExt = $taille * $zone * 0.62;
    $epaisseur = $taille * 0.105;
    $cx = $taille / 2;
    $cy = $taille / 2;
    for ($ang = 35; $ang <= 325; $ang += 0.25) {
        $a = deg2rad($ang);
        for ($e = 0; $e < $epaisseur; $e += 0.7) {
            $rr = $rExt - $e;
            imagefilledellipse($im, (int) round($cx + $rr * cos($a)), (int) round($cy - $rr * sin($a)), 3 + (int) ($taille / 90), 3 + (int) ($taille / 90), $blanc);
        }
    }
    imagefilledrectangle($im, (int) ($cx + 0.02 * $taille), (int) ($cy - $epaisseur / 2 + 0.02 * $taille), (int) ($cx + $rExt - 1), (int) ($cy + $epaisseur / 2 + 0.02 * $taille), $blanc);

    imagepng($im, $chemin, 9);
    imagedestroy($im);
}

$dossier = __DIR__.'/../public/icons';
icone(192, false, "$dossier/icone-192.png");
icone(512, false, "$dossier/icone-512.png");
icone(512, true, "$dossier/icone-masquable-512.png");
icone(180, true, "$dossier/apple-touch-icon.png"); // iOS applique lui-même l'arrondi
icone(48, false, "$dossier/favicon-48.png");
echo "Icônes générées dans public/icons\n";
