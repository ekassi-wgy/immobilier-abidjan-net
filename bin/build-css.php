<?php

declare(strict_types=1);

/**
 * Compile les feuilles SCSS du site public (scssphp, sans Node).
 *
 * Usage : php bin/build-css.php          → public/assets/css/app.css minifié
 *         php bin/build-css.php --dev    → sortie lisible (non minifiée) pour le débogage
 *
 * Sources : resources/scss/app.scss (Bootstrap 5.3 importé depuis vendor/twbs/bootstrap/scss).
 * Le CSS compilé est commité : le serveur de production n'a pas besoin de compiler.
 */

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Logger\QuietLogger;
use ScssPhp\ScssPhp\OutputStyle;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$entry = $root . '/resources/scss/app.scss';
$target = $root . '/public/assets/css/app.css';
$dev = in_array('--dev', $argv, true);

$compiler = new Compiler();
$compiler->setImportPaths([$root . '/resources/scss', $root . '/vendor/twbs/bootstrap/scss']);
$compiler->setOutputStyle($dev ? OutputStyle::EXPANDED : OutputStyle::COMPRESSED);
// Bootstrap 5.3 déclenche des avertissements de dépréciation Sass sans conséquence
$compiler->setLogger(new QuietLogger());

$start = microtime(true);

try {
    $css = $compiler->compileString((string) file_get_contents($entry), $entry)->getCss();
} catch (Throwable $exception) {
    fwrite(STDERR, 'Erreur SCSS : ' . $exception->getMessage() . "\n");
    exit(1);
}

if (!is_dir(dirname($target))) {
    mkdir(dirname($target), 0775, true);
}
file_put_contents($target, $css . "\n");

printf(
    "%s → %s (%.1f Ko, %.1f Ko gzip) en %.1f s\n",
    'resources/scss/app.scss',
    'public/assets/css/app.css',
    strlen($css) / 1024,
    strlen(gzencode($css, 9)) / 1024,
    microtime(true) - $start
);
