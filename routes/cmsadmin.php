<?php

declare(strict_types=1);

/**
 * Routes du back-office /cmsadmin (noindex, exclu du sitemap).
 * Authentification et contrôle des rôles : lot 1.3.
 */

use App\Controllers\Preview\CmsadminPreviewController;
use App\Core\App;
use App\Core\Router;

return static function (Router $router, App $app): void {
    $router->group(['prefix' => '/cmsadmin', 'as' => 'cmsadmin.'], static function (Router $router) use ($app): void {
        if ($app->config->get('app.preview')) {
            // PROVISOIRE (APP_ENV=local) : écrans avec données fictives, remplacés module par module
            // (connexion → lot 1.3, annonces → lot 1.6, tableau de bord → lot 1.12)
            $router->get('/', [CmsadminPreviewController::class, 'dashboard'], 'dashboard');
            $router->get('/annonces', [CmsadminPreviewController::class, 'properties'], 'properties.index');
            $router->get('/annonces/nouvelle', [CmsadminPreviewController::class, 'propertyForm'], 'properties.create');
            $router->get('/annonces/{reference:[A-Z0-9-]+}/modifier', [CmsadminPreviewController::class, 'propertyForm'], 'properties.edit');
            $router->get('/connexion', [CmsadminPreviewController::class, 'login'], 'login');
            $router->post('/connexion', [CmsadminPreviewController::class, 'loginSubmit'], 'login.submit');
            $router->get('/erreur-500', [CmsadminPreviewController::class, 'serverError'], 'preview.error');
        }
    });
};
