<?php

declare(strict_types=1);

/**
 * Routes du back-office /cmsadmin (noindex, exclu du sitemap).
 * Tout est réservé aux comptes connectés, sauf les écrans de connexion et de mot de passe oublié.
 * Le contrôle des rôles se fait par route ('RequireRole:…') ET dans chaque action (country_id, agency_id).
 */

use App\Controllers\Cmsadmin\AccountController;
use App\Controllers\Cmsadmin\AuthController;
use App\Controllers\Cmsadmin\PasswordController;
use App\Controllers\Preview\CmsadminPreviewController;
use App\Core\App;
use App\Core\Router;
use App\Middlewares\Authenticate;
use App\Middlewares\RedirectIfAuthenticated;

return static function (Router $router, App $app): void {
    $router->group(['prefix' => '/cmsadmin', 'as' => 'cmsadmin.'], static function (Router $router) use ($app): void {
        // Hors session
        $router->group(['middleware' => [RedirectIfAuthenticated::class]], static function (Router $router): void {
            $router->get('/connexion', [AuthController::class, 'showLogin'], 'login');
            $router->post('/connexion', [AuthController::class, 'login'], 'login.submit');
            $router->get('/mot-de-passe-oublie', [PasswordController::class, 'showForgot'], 'password.forgot');
            $router->post('/mot-de-passe-oublie', [PasswordController::class, 'sendResetLink'], 'password.email');
            $router->get('/mot-de-passe/reinitialiser/{token:[A-Za-z0-9_-]+}', [PasswordController::class, 'showReset'], 'password.reset');
            $router->post('/mot-de-passe/reinitialiser/{token:[A-Za-z0-9_-]+}', [PasswordController::class, 'reset'], 'password.update');
        });

        // Comptes connectés
        $router->group(['middleware' => [Authenticate::class]], static function (Router $router) use ($app): void {
            $router->post('/deconnexion', [AuthController::class, 'logout'], 'logout');
            $router->get('/mot-de-passe/changer', [PasswordController::class, 'showChange'], 'password.change');
            $router->post('/mot-de-passe/changer', [PasswordController::class, 'change'], 'password.change.submit');
            $router->get('/mon-compte', [AccountController::class, 'show'], 'account');
            $router->post('/mon-compte/mot-de-passe', [AccountController::class, 'updatePassword'], 'account.password');

            if ($app->config->get('app.preview')) {
                // PROVISOIRE (APP_ENV=local) : écrans avec données fictives, remplacés module par module
                // (annonces → lot 1.6, tableau de bord → lot 1.12)
                $router->get('/', [CmsadminPreviewController::class, 'dashboard'], 'dashboard');
                $router->get('/annonces', [CmsadminPreviewController::class, 'properties'], 'properties.index');
                $router->get('/annonces/nouvelle', [CmsadminPreviewController::class, 'propertyForm'], 'properties.create');
                $router->get('/annonces/{reference:[A-Z0-9-]+}/modifier', [CmsadminPreviewController::class, 'propertyForm'], 'properties.edit');
                $router->get('/erreur-500', [CmsadminPreviewController::class, 'serverError'], 'preview.error');
            } else {
                // Tableau de bord réel : lot 1.12
                $router->get('/', [AccountController::class, 'show'], 'dashboard');
            }
        });
    });
};
