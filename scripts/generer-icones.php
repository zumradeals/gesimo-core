<?php

/**
 * Génère les icônes de l'application (un « A » blanc sur fond vert Avelis). À relancer seulement pour changer l'icône :
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

    // Dégradé vert Avelis (#0f766e -> #1aa395), en diagonale.
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

    // Le « A » : deux jambes réunies au sommet et une barre, dessinés en blanc. Pour une icône « masquable », il reste dans la zone sûre (80 %).
    imagealphablending($im, true);
    $blanc = imagecolorallocate($im, 255, 255, 255);
    $zone = $masquable ? 0.40 : 0.50;
    $demiLargeur = $taille * $zone * 0.62;
    $demiHauteur = $taille * $zone * 0.68;
    $epaisseur = $taille * 0.115;
    $cx = $taille / 2;
    $haut = $taille / 2 - $demiHauteur;
    $bas = $taille / 2 + $demiHauteur;
    $t = $epaisseur;
    // Jambe gauche puis jambe droite : des parallélogrammes qui se rejoignent au sommet.
    imagefilledpolygon($im, [(int) ($cx - $t * 0.55), (int) $haut, (int) ($cx + $t * 0.55), (int) $haut, (int) ($cx - $demiLargeur + $t * 1.1), (int) $bas, (int) ($cx - $demiLargeur), (int) $bas], $blanc);
    imagefilledpolygon($im, [(int) ($cx - $t * 0.55), (int) $haut, (int) ($cx + $t * 0.55), (int) $haut, (int) ($cx + $demiLargeur), (int) $bas, (int) ($cx + $demiLargeur - $t * 1.1), (int) $bas], $blanc);
    // La barre du « A », à environ 62 % de la hauteur.
    $yBarre = $haut + ($bas - $haut) * 0.62;
    $demiBarre = $demiLargeur * 0.50;
    imagefilledrectangle($im, (int) ($cx - $demiBarre), (int) $yBarre, (int) ($cx + $demiBarre), (int) ($yBarre + $t * 0.8), $blanc);

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
