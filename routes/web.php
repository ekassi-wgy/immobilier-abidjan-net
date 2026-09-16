<?php

declare(strict_types=1);

/**
 * Routes du site public.
 */

use App\Controllers\Front\HomeController;
use App\Controllers\Preview\FrontPreviewController;
use App\Core\App;
use App\Core\Router;

return static function (Router $router, App $app): void {
    $router->get('/', [HomeController::class, 'index'], 'home');

    if ($app->config->get('app.preview')) {
        // PROVISOIRE (APP_ENV=local) : charte graphique de référence, avec des données fictives
        $router->get('/styleguide', [FrontPreviewController::class, 'styleguide'], 'styleguide');
    }
};
