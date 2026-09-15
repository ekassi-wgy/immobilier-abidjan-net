<?php

declare(strict_types=1);

/**
 * Supprime les photos envoyées mais jamais rattachées à une annonce (dossiers uploads/{pays}/tmp/),
 * plus vieilles que 24 heures. À lancer une fois par jour en CRON, après expire-listings.
 *
 *   php bin/cleanup-uploads.php [--hours=24] [--dry-run]
 */

/** @var App\Core\App $app */
$app = require __DIR__ . '/../app/bootstrap.php';

$options = getopt('', ['hours::', 'dry-run']);
$hours = max(1, (int) ($options['hours'] ?? 24));
$dryRun = isset($options['dry-run']);
$limit = time() - $hours * 3600;

$deleted = 0;
$bytes = 0;
foreach (glob($app->root . '/public/uploads/*/tmp/*/*') ?: [] as $file) {
    if (!is_file($file) || filemtime($file) > $limit) {
        continue;
    }
    $deleted++;
    $bytes += (int) filesize($file);
    if (!$dryRun) {
        @unlink($file);
    }
}

// Dossiers mensuels devenus vides
foreach (glob($app->root . '/public/uploads/*/tmp/*', GLOB_ONLYDIR) ?: [] as $directory) {
    if (!$dryRun && (glob($directory . '/*') ?: []) === []) {
        @rmdir($directory);
    }
}

printf("%s%d fichier(s) temporaire(s) supprimé(s) (%.1f Mo), plus vieux que %d h.\n", $dryRun ? '[simulation] ' : '', $deleted, $bytes / 1048576, $hours);
