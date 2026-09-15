<?php

declare(strict_types=1);

/**
 * Fonctions utilitaires globales (chargées par l'autoload Composer, section « files »).
 */

use App\Core\App;
use App\Core\Csrf;
use App\Core\Env;
use App\Models\Site;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

// Application & configuration ---------------------------------------------------

function app(): App
{
    return App::instance();
}

/** Valeur brute de .env (true/false/null convertis). À n'utiliser que dans config/*.php. */
function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

/** Configuration en notation pointée : config('app.debug'). */
function config(string $key, mixed $default = null): mixed
{
    return app()->config->get($key, $default);
}

/** Site courant résolu depuis le nom d'hôte (null en CLI ou avant résolution). */
function site(): ?Site
{
    return app()->site();
}

/** Paramètre effectif du site courant (table settings) : settings('listing.lifetime_days', 90). */
function settings(string $key, mixed $default = null): mixed
{
    return app()->settings()->get($key, $default);
}

// Traductions ----------------------------------------------------------------------

/**
 * Chaîne d'interface traduite (lang/{locale}.php) : __('errors.404.title').
 *
 * @param array<string, string|int|float> $replace
 */
function __(string $key, array $replace = []): string
{
    return app()->translator()->get($key, $replace);
}

function locale(): string
{
    return app()->translator()->locale();
}

// Sécurité ------------------------------------------------------------------------

/** Échappement HTML systématique en sortie. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Jeton CSRF de la session (ouvre la session si nécessaire). */
function csrf_token(): string
{
    return app()->csrf()->token();
}

/** Champ caché CSRF à placer dans chaque formulaire POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="' . Csrf::FIELD . '" value="' . e(csrf_token()) . '">';
}

// URL ---------------------------------------------------------------------------------

/** URL depuis la racine publique du site (préfixe d'installation inclus). */
function url(string $path = ''): string
{
    $base = (string) config('app.base_path', '');

    return $base . '/' . ltrim($path, '/');
}

/**
 * URL absolue sur le domaine courant (canoniques, Open Graph, emails, sitemap).
 * Le nom d'hôte vient du site résolu (donc validé), jamais directement de l'en-tête Host.
 */
function absolute_url(string $path = ''): string
{
    $site = site();
    $request = app()->request();

    if ($site === null || $request === null) {
        return rtrim((string) config('app.url'), '/') . url($path);
    }

    return ($request->isSecure() ? 'https://' : 'http://') . $site->host . url($path);
}

/**
 * URL d'une route nommée : route('cmsadmin.properties.edit', ['reference' => 'IAN-24531']).
 *
 * @param array<string, string|int> $params
 */
function route(string $name, array $params = []): string
{
    return url(app()->router->path($name, $params));
}

/** URL d'une ressource du site public (public/assets), versionnée par date de modification. */
function asset(string $path): string
{
    $relative = 'assets/' . ltrim($path, '/');
    $file = APP_ROOT . '/public/' . $relative;
    $version = is_file($file) ? '?v=' . filemtime($file) : '';

    return url($relative) . $version;
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

// Vues ------------------------------------------------------------------------------

/**
 * Rend une vue PHP (app/Views) avec ses données et retourne le HTML.
 *
 * @param array<string, mixed> $data
 */
function render_view(string $view, array $data = []): string
{
    return app()->view()->render($view, $data);
}

/**
 * Inclut un fragment du back-office (app/Views/cmsadmin/partials).
 *
 * @param array<string, mixed> $data
 */
function cmsadmin_partial(string $name, array $data = []): string
{
    return render_view('cmsadmin/partials/' . $name, $data);
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

// Formatage ---------------------------------------------------------------------------

/**
 * Formatage d'un prix : 185 000 000 FCFA.
 * Par défaut, devise et nombre de décimales du pays du site courant.
 */
function format_price(int|float|string|null $amount, ?string $currency = null, ?int $decimals = null): string
{
    if ($amount === null || $amount === '') {
        return __('common.price_on_request');
    }

    $country = site()?->country;
    $currency ??= $country->currencySymbol ?? 'FCFA';
    $decimals ??= $country->currencyDecimals ?? 0;

    return number_format((float) $amount, $decimals, ',', "\u{00A0}") . "\u{00A0}" . $currency;
}

/** Formatage d'un nombre entier : 12 480. */
function format_number(int|float $value): string
{
    return number_format((float) $value, 0, ',', "\u{00A0}");
}

/** Libellé de période de prix : « / mois », « / nuit »… */
function price_period_label(string $period): string
{
    return in_array($period, ['month', 'week', 'night', 'year'], true) ? __('common.price_period.' . $period) : '';
}
