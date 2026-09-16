<?php

declare(strict_types=1);

/**
 * Routes du site public.
 */

use App\Controllers\Front\FavoriteController;
use App\Controllers\Front\HomeController;
use App\Controllers\Front\SearchController;
use App\Controllers\Preview\FrontPreviewController;
use App\Core\App;
use App\Core\Router;

return static function (Router $router, App $app): void {
    $router->get('/', [HomeController::class, 'index'], 'home');
    $router->get('/favoris', [FavoriteController::class, 'index'], 'favorites');

    if ($app->config->get('app.preview')) {
        // PROVISOIRE (APP_ENV=local) : charte graphique de référence, avec des données fictives
        $router->get('/styleguide', [FrontPreviewController::class, 'styleguide'], 'styleguide');
    }

    // -------------------------------------------------------------------------------------------
    // Résultats de recherche : /acheter/appartement/abidjan/cocody/riviera-golf
    //
    // ATTENTION : ces routes DOIVENT rester les dernières déclarées. Le premier segment est un slug
    // de transaction lu en base (`transaction_types.slug`), donc impossible à distinguer d'un autre
    // segment par une expression régulière : le routeur retient la première route qui correspond,
    // et toute nouvelle URL publique (/annonces/…, /agences/…, pages statiques) doit donc être
    // déclarée AU-DESSUS de ce bloc. Un slug inconnu répond 404 (SearchFilters::resolve).
    // -------------------------------------------------------------------------------------------
    $segment = '[a-z0-9-]+';
    // « cmsadmin » est un préfixe réservé : jamais capturé comme slug de transaction, même si
    // l'ordre de chargement des fichiers de routes venait à changer.
    $first = '(?!cmsadmin(?:/|$))[a-z0-9-]+';
    $router->get("/{transaction:{$first}}", [SearchController::class, 'index'], 'search');
    $router->get("/{transaction:{$first}}/{s1:{$segment}}", [SearchController::class, 'index']);
    $router->get("/{transaction:{$first}}/{s1:{$segment}}/{s2:{$segment}}", [SearchController::class, 'index']);
    $router->get("/{transaction:{$first}}/{s1:{$segment}}/{s2:{$segment}}/{s3:{$segment}}", [SearchController::class, 'index']);
    $router->get("/{transaction:{$first}}/{s1:{$segment}}/{s2:{$segment}}/{s3:{$segment}}/{s4:{$segment}}", [SearchController::class, 'index']);
};
