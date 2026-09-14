<?php

declare(strict_types=1);

/**
 * Fonctions utilitaires globales.
 * Provisoire : sera intégré au socle (lot 1.1 — autoload Composer "files", configuration .env).
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

/** Échappement HTML systématique en sortie. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL absolue depuis la racine publique du site. */
function url(string $path = ''): string
{
    $base = rtrim((string) ($_ENV['APP_BASE_PATH'] ?? ''), '/');

    return $base . '/' . ltrim($path, '/');
}

/** URL d'une ressource du back-office, versionnée par date de modification (cache busting). */
function cmsadmin_asset(string $path): string
{
    $relative = 'cmsadmin/assets/' . ltrim($path, '/');
    $file = APP_ROOT . '/public/' . $relative;
    $version = is_file($file) ? '?v=' . filemtime($file) : '';

    return url($relative) . $version;
}

/** URL d'une page du back-office. */
function cmsadmin_url(string $path = ''): string
{
    return url('cmsadmin/' . ltrim($path, '/'));
}

/**
 * Rend une vue PHP avec ses données et retourne le HTML.
 * Les données sont isolées dans la portée de la vue.
 */
function render_view(string $view, array $data = []): string
{
    $file = APP_ROOT . '/app/Views/' . $view . '.php';
    if (!is_file($file)) {
        throw new RuntimeException("Vue introuvable : {$view}");
    }

    return (static function (string $__file, array $__data): string {
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            require $__file;
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }

        return (string) ob_get_clean();
    })($file, $data);
}

/** Inclut un fragment du back-office (app/Views/cmsadmin/partials). */
function cmsadmin_partial(string $name, array $data = []): string
{
    return render_view('cmsadmin/partials/' . $name, $data);
}

/** Formatage d'un prix : 185 000 000 FCFA. */
function format_price(int|float|null $amount, string $currency = 'FCFA'): string
{
    if ($amount === null) {
        return 'Prix sur demande';
    }

    return number_format((float) $amount, 0, ',', "\u{00A0}") . "\u{00A0}" . $currency;
}

/** Formatage d'un nombre entier : 12 480. */
function format_number(int|float $value): string
{
    return number_format((float) $value, 0, ',', "\u{00A0}");
}

/** URL d'une ressource du site public (public/assets), versionnée par date de modification. */
function asset(string $path): string
{
    $relative = 'assets/' . ltrim($path, '/');
    $file = APP_ROOT . '/public/' . $relative;
    $version = is_file($file) ? '?v=' . filemtime($file) : '';

    return url($relative) . $version;
}

/** Icône du sprite Phosphor (public/assets/img/icons.svg). Décorative par défaut. */
function icon(string $name, string $class = '', ?string $label = null): string
{
    static $sprite = null;
    $sprite ??= asset('img/icons.svg');

    $accessibility = $label === null
        ? ' aria-hidden="true" focusable="false"'
        : ' role="img" aria-label="' . e($label) . '"';

    return sprintf(
        '<svg class="im-icon%s"%s><use href="%s#i-%s"></use></svg>',
        $class !== '' ? ' ' . e($class) : '',
        $accessibility,
        e($sprite),
        e($name)
    );
}

/** Libellé de période de prix : « / mois », « / nuit »… */
function price_period_label(string $period): string
{
    return [
        'month' => '/ mois',
        'week' => '/ semaine',
        'night' => '/ nuit',
        'year' => '/ an',
    ][$period] ?? '';
}
