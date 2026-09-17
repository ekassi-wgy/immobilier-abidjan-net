<?php

declare(strict_types=1);

/**
 * Génère les versions WebP des logos (site public et back-office) à partir des PNG de la charte.
 *
 *   php bin/build-logos.php
 *
 * À relancer si un logo PNG est remplacé. Les PNG restent la référence et servent de repli
 * (`<picture>`), ainsi que pour les emails, le JSON-LD et les icônes (favicon, apple-touch-icon).
 *
 * Réglages vérifiés à l'œil, agrandis 3 fois : aucune différence visible avec le PNG.
 *  - logo blanc : sans perte (le plus léger des deux modes pour une image à deux couleurs) ;
 *  - logos en couleur : qualité 92 (dégradés et lettres rouges intacts).
 */

const DIRECTORIES = [
    __DIR__ . '/../public/assets/img/brand',
    __DIR__ . '/../public/cmsadmin/assets/images',
];
const LOSSLESS = ['logo-immobilier-abidjan-net-blanc.png'];
const QUALITY = 92;

if (!function_exists('imagewebp')) {
    fwrite(STDERR, "GD sans prise en charge du WebP : impossible de générer les logos.\n");
    exit(1);
}

$total = 0;
foreach (DIRECTORIES as $directory) {
    foreach (glob($directory . '/logo-*.png') ?: [] as $source) {
        $image = imagecreatefrompng($source);
        if ($image === false) {
            fwrite(STDERR, 'Lecture impossible : ' . basename($source) . "\n");
            exit(1);
        }
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $target = substr($source, 0, -4) . '.webp';
        $quality = in_array(basename($source), LOSSLESS, true) ? IMG_WEBP_LOSSLESS : QUALITY;
        if (!imagewebp($image, $target, $quality)) {
            fwrite(STDERR, 'Encodage impossible : ' . basename($source) . "\n");
            exit(1);
        }
        imagedestroy($image);
        clearstatcache();

        printf("%-58s %5.1f Ko → %5.1f Ko\n", str_replace(realpath(__DIR__ . '/..') . '/', '', realpath($source)), filesize($source) / 1024, filesize($target) / 1024);
        $total++;
    }
}
printf("%d logo(s) converti(s).\n", $total);
