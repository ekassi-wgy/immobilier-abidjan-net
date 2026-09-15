<?php

declare(strict_types=1);

/**
 * Routes du back-office /cmsadmin (noindex, exclu du sitemap).
 * Tout est réservé aux comptes connectés, sauf les écrans de connexion et de mot de passe oublié.
 * Le contrôle des rôles se fait par route ('RequireRole:…') ET dans chaque action (country_id, agency_id).
 */

use App\Controllers\Cmsadmin\AccountController;
use App\Controllers\Cmsadmin\AuthController;
use App\Controllers\Cmsadmin\Catalog\AttributeController;
use App\Controllers\Cmsadmin\Catalog\CategoryController;
use App\Controllers\Cmsadmin\Catalog\FeatureController;
use App\Controllers\Cmsadmin\Geo\CityController;
use App\Controllers\Cmsadmin\Geo\CommuneController;
use App\Controllers\Cmsadmin\Geo\DistrictController;
use App\Controllers\Cmsadmin\PasswordController;
use App\Controllers\Cmsadmin\SiteController;
use App\Controllers\Preview\CmsadminPreviewController;
use App\Core\App;
use App\Core\Router;
use App\Middlewares\Authenticate;
use App\Middlewares\RedirectIfAuthenticated;
use App\Middlewares\RequireRole;

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

            // Référentiel géographique : Super Admin et Admin Pays (limité à son pays)
            $router->group(['prefix' => '/geo', 'as' => 'geo.', 'middleware' => [RequireRole::class . ':staff']], static function (Router $router): void {
                $router->post('/pays', [CityController::class, 'switchCountry'], 'country');
                foreach (['villes' => [CityController::class, 'cities'], 'communes' => [CommuneController::class, 'communes'], 'quartiers' => [DistrictController::class, 'districts']] as $segment => [$controller, $name]) {
                    $router->get("/{$segment}", [$controller, 'index'], "{$name}.index");
                    $router->get("/{$segment}/ajouter", [$controller, 'create'], "{$name}.create");
                    $router->post("/{$segment}", [$controller, 'store'], "{$name}.store");
                    $router->get("/{$segment}/{id:\\d+}/modifier", [$controller, 'edit'], "{$name}.edit");
                    $router->post("/{$segment}/{id:\\d+}", [$controller, 'update'], "{$name}.update");
                    $router->post("/{$segment}/{id:\\d+}/activation", [$controller, 'toggle'], "{$name}.toggle");
                    $router->post("/{$segment}/{id:\\d+}/supprimer", [$controller, 'destroy'], "{$name}.destroy");
                }
            });

            // Catalogue : catégories, critères dynamiques, équipements (Super Admin)
            $router->group(['as' => 'catalog.', 'middleware' => [RequireRole::class . ':super_admin']], static function (Router $router): void {
                foreach (['categories' => [CategoryController::class, 'categories'], 'criteres' => [AttributeController::class, 'attributes'], 'equipements' => [FeatureController::class, 'features']] as $segment => [$controller, $name]) {
                    $router->get("/{$segment}", [$controller, 'index'], "{$name}.index");
                    $router->get("/{$segment}/ajouter", [$controller, 'create'], "{$name}.create");
                    $router->post("/{$segment}", [$controller, 'store'], "{$name}.store");
                    $router->get("/{$segment}/{id:\\d+}/modifier", [$controller, 'edit'], "{$name}.edit");
                    $router->post("/{$segment}/{id:\\d+}", [$controller, 'update'], "{$name}.update");
                    $router->post("/{$segment}/{id:\\d+}/activation", [$controller, 'toggle'], "{$name}.toggle");
                    $router->post("/{$segment}/{id:\\d+}/supprimer", [$controller, 'destroy'], "{$name}.destroy");
                }
            });

            // Pays, sites et domaines (Super Admin)
            $router->group(['prefix' => '/pays-sites', 'as' => 'sites.', 'middleware' => [RequireRole::class . ':super_admin']], static function (Router $router): void {
                $router->get('/', [SiteController::class, 'index'], 'index');
                $router->get('/pays/ajouter', [SiteController::class, 'createCountry'], 'countries.create');
                $router->post('/pays', [SiteController::class, 'storeCountry'], 'countries.store');
                $router->get('/pays/{id:\\d+}/modifier', [SiteController::class, 'editCountry'], 'countries.edit');
                $router->post('/pays/{id:\\d+}', [SiteController::class, 'updateCountry'], 'countries.update');
                $router->get('/sites/ajouter', [SiteController::class, 'createSite'], 'create');
                $router->post('/sites', [SiteController::class, 'storeSite'], 'store');
                $router->get('/sites/{id:\\d+}/modifier', [SiteController::class, 'editSite'], 'edit');
                $router->post('/sites/{id:\\d+}', [SiteController::class, 'updateSite'], 'update');
                $router->post('/sites/{id:\\d+}/domaines', [SiteController::class, 'addDomain'], 'domains.store');
                $router->post('/sites/{id:\\d+}/domaines/{domain:\\d+}/principal', [SiteController::class, 'primaryDomain'], 'domains.primary');
                $router->post('/sites/{id:\\d+}/domaines/{domain:\\d+}/supprimer', [SiteController::class, 'deleteDomain'], 'domains.destroy');
            });

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
