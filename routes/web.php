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
    if ($app->config->get('app.preview')) {
        // PROVISOIRE (APP_ENV=local) : maquettes avec données fictives, retirées au fil des lots
        $router->get('/', [FrontPreviewController::class, 'home'], 'home');
        $router->get('/styleguide', [FrontPreviewController::class, 'styleguide'], 'styleguide');

        return;
    }

    $router->get('/', [HomeController::class, 'index'], 'home');
};
