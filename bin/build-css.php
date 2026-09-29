<?php

declare(strict_types=1);

/**
 * Compile les feuilles SCSS du site public (scssphp, sans Node).
 *
 * Usage : php bin/build-css.php          → public/assets/css/app.css et widget.css minifiés
 *         php bin/build-css.php --dev    → sortie lisible (non minifiée) pour le débogage
 *
 * Sources : resources/scss/app.scss (Bootstrap 5.3 importé depuis vendor/twbs/bootstrap/scss) et
 * resources/scss/widget.scss (section « Biens à la une » embarquée sur Abidjan.net).
 * Le CSS compilé est commité : le serveur de production n'a pas besoin de compiler.
 */

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Logger\QuietLogger;
use ScssPhp\ScssPhp\OutputStyle;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$dev = in_array('--dev', $argv, true);

// Feuilles à produire. Le widget (lot 2.6) vit dans la page d'Abidjan.net : ses `rem` dépendraient de
// la taille de police de leur page, ils sont donc convertis en px (1rem = 16px, la base du site).
$builds = [
    'app' => ['post' => null],
    'widget' => ['post' => static fn (string $css): string => (string) preg_replace_callback(
        '/(?<![\w.-])(-?\d*\.?\d+)rem\b/',
        static fn (array $m): string => rtrim(rtrim(number_format((float) $m[1] * 16, 3, '.', ''), '0'), '.') . 'px',
        $css
    )],
];

$compiler = new Compiler();
$compiler->setImportPaths([$root . '/resources/scss', $root . '/vendor/twbs/bootstrap/scss']);
$compiler->setOutputStyle($dev ? OutputStyle::EXPANDED : OutputStyle::COMPRESSED);
// Bootstrap 5.3 déclenche des avertissements de dépréciation Sass sans conséquence
$compiler->setLogger(new QuietLogger());

foreach ($builds as $name => $build) {
    $entry = "{$root}/resources/scss/{$name}.scss";
    $target = "{$root}/public/assets/css/{$name}.css";
    $start = microtime(true);

    try {
        $css = $compiler->compileString((string) file_get_contents($entry), $entry)->getCss();
    } catch (Throwable $exception) {
        fwrite(STDERR, "Erreur SCSS ({$name}.scss) : " . $exception->getMessage() . "\n");
        exit(1);
    }
    if ($build['post'] !== null) {
        $css = ($build['post'])($css);
    }

    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0775, true);
    }
    file_put_contents($target, $css . "\n");

    printf(
        "resources/scss/%s.scss → public/assets/css/%s.css (%.1f Ko, %.1f Ko gzip) en %.1f s\n",
        $name,
        $name,
        strlen($css) / 1024,
        strlen(gzencode($css, 9)) / 1024,
        microtime(true) - $start
    );
}
