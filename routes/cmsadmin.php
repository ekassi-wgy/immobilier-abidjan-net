<?php

declare(strict_types=1);

/**
 * Routes du back-office /cmsadmin (noindex, exclu du sitemap).
 * Tout est réservé aux comptes connectés, sauf les écrans de connexion et de mot de passe oublié.
 * Le contrôle des rôles se fait par route ('RequireRole:…') ET dans chaque action (country_id, agency_id).
 */

use App\Controllers\Cmsadmin\AccountController;
use App\Controllers\Cmsadmin\ActivityController;
use App\Controllers\Cmsadmin\Agencies\AgencyAccountController;
use App\Controllers\Cmsadmin\Agencies\AgencyController;
use App\Controllers\Cmsadmin\Agencies\AgencyProfileController;
use App\Controllers\Cmsadmin\Agencies\PartnerRequestController;
use App\Controllers\Cmsadmin\Agencies\StaffUserController;
use App\Controllers\Cmsadmin\AuthController;
use App\Controllers\Cmsadmin\Catalog\AttributeController;
use App\Controllers\Cmsadmin\Catalog\CategoryController;
use App\Controllers\Cmsadmin\Catalog\FeatureController;
use App\Controllers\Cmsadmin\ContentController;
use App\Controllers\Cmsadmin\DashboardController;
use App\Controllers\Cmsadmin\ExportController;
use App\Controllers\Cmsadmin\Geo\CityController;
use App\Controllers\Cmsadmin\Geo\CommuneController;
use App\Controllers\Cmsadmin\Geo\DistrictController;
use App\Controllers\Cmsadmin\LeadController;
use App\Controllers\Cmsadmin\NotificationController;
use App\Controllers\Cmsadmin\PasswordController;
use App\Controllers\Cmsadmin\Properties\PropertyActionController;
use App\Controllers\Cmsadmin\Properties\PropertyController;
use App\Controllers\Cmsadmin\Properties\PropertyMediaController;
use App\Controllers\Cmsadmin\SeoController;
use App\Controllers\Cmsadmin\GuideController;
use App\Controllers\Cmsadmin\SettingsController;
use App\Controllers\Cmsadmin\SiteController;
use App\Controllers\Cmsadmin\SubmissionController;
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

            // Annonces : tous les rôles, dans leur périmètre (agence = ses annonces)
            $router->group(['prefix' => '/annonces', 'as' => 'properties.'], static function (Router $router): void {
                $router->get('/', [PropertyController::class, 'index'], 'index');
                $router->get('/nouvelle', [PropertyController::class, 'create'], 'create');
                $router->post('/', [PropertyController::class, 'store'], 'store');
                $router->get('/criteres', [PropertyMediaController::class, 'criteria'], 'criteria');
                $router->get('/listes', [PropertyMediaController::class, 'options'], 'options');
                $router->post('/photos', [PropertyMediaController::class, 'uploadPhoto'], 'photos');
                $router->get('/{reference:[A-Z][A-Z0-9]*-[0-9]+}', [PropertyController::class, 'show'], 'show');
                $router->get('/{reference:[A-Z][A-Z0-9]*-[0-9]+}/modifier', [PropertyController::class, 'edit'], 'edit');
                $router->post('/{reference:[A-Z][A-Z0-9]*-[0-9]+}', [PropertyController::class, 'update'], 'update');
                $router->post('/{reference:[A-Z][A-Z0-9]*-[0-9]+}/valider', [PropertyActionController::class, 'approve'], 'approve');
                $router->post('/{reference:[A-Z][A-Z0-9]*-[0-9]+}/rejeter', [PropertyActionController::class, 'reject'], 'reject');
                $router->post('/{reference:[A-Z][A-Z0-9]*-[0-9]+}/depublier', [PropertyActionController::class, 'unpublish'], 'unpublish');
                $router->post('/{reference:[A-Z][A-Z0-9]*-[0-9]+}/republier', [PropertyActionController::class, 'republish'], 'republish');
                $router->post('/{reference:[A-Z][A-Z0-9]*-[0-9]+}/desactiver', [PropertyActionController::class, 'deactivate'], 'deactivate');
                $router->post('/{reference:[A-Z][A-Z0-9]*-[0-9]+}/reactiver', [PropertyActionController::class, 'reactivate'], 'reactivate');
                $router->post('/{reference:[A-Z][A-Z0-9]*-[0-9]+}/prolonger', [PropertyActionController::class, 'extend'], 'extend');
                $router->post('/{reference:[A-Z][A-Z0-9]*-[0-9]+}/archiver', [PropertyActionController::class, 'archive'], 'archive');
                $router->post('/{reference:[A-Z][A-Z0-9]*-[0-9]+}/disponibilite', [PropertyActionController::class, 'availability'], 'availability');
                $router->post('/{reference:[A-Z][A-Z0-9]*-[0-9]+}/mise-en-avant', [PropertyActionController::class, 'feature'], 'feature');
                $router->post('/{reference:[A-Z][A-Z0-9]*-[0-9]+}/supprimer', [PropertyActionController::class, 'destroy'], 'destroy');
            });

            // Notifications (cloche de la barre supérieure)
            $router->get('/notifications/{id:\\d+}', [NotificationController::class, 'open'], 'notifications.open');
            $router->post('/notifications/tout-lire', [NotificationController::class, 'markAllRead'], 'notifications.read_all');

            // Demandes de contact : équipe Weblogy uniquement. Weblogy est l'intermédiaire exclusif :
            // un partenaire ne voit jamais l'identité d'un prospect, Weblogy le sollicite lui-même.
            $router->group(['prefix' => '/contacts', 'as' => 'leads.', 'middleware' => [RequireRole::class . ':staff']], static function (Router $router): void {
                $router->get('/', [LeadController::class, 'index'], 'index');
                $router->get('/{id:\\d+}', [LeadController::class, 'show'], 'show');
                $router->post('/{id:\\d+}', [LeadController::class, 'update'], 'update');
            });

            // Espace agence : profil public de l'agence connectée (modification réservée au responsable)
            $router->group(['middleware' => [RequireRole::class . ':agency']], static function (Router $router): void {
                $router->get('/profil-agence', [AgencyProfileController::class, 'show'], 'agency.profile');
                $router->post('/profil-agence', [AgencyProfileController::class, 'update'], 'agency.profile.update');
            });

            // Agences partenaires, leurs comptes et demandes de partenariat : Super Admin et Admin Pays (pays du site)
            $router->group(['middleware' => [RequireRole::class . ':staff']], static function (Router $router): void {
                $router->group(['prefix' => '/agences', 'as' => 'agencies.'], static function (Router $router): void {
                    $router->get('/', [AgencyController::class, 'index'], 'index');
                    $router->get('/ajouter', [AgencyController::class, 'create'], 'create');
                    $router->post('/', [AgencyController::class, 'store'], 'store');
                    $router->get('/{id:\\d+}/modifier', [AgencyController::class, 'edit'], 'edit');
                    $router->post('/{id:\\d+}', [AgencyController::class, 'update'], 'update');
                    $router->post('/{id:\\d+}/supprimer', [AgencyController::class, 'destroy'], 'destroy');

                    $router->get('/{agency:\\d+}/comptes/ajouter', [AgencyAccountController::class, 'create'], 'accounts.create');
                    $router->post('/{agency:\\d+}/comptes', [AgencyAccountController::class, 'store'], 'accounts.store');
                    $router->get('/{agency:\\d+}/comptes/{id:\\d+}/modifier', [AgencyAccountController::class, 'edit'], 'accounts.edit');
                    $router->post('/{agency:\\d+}/comptes/{id:\\d+}', [AgencyAccountController::class, 'update'], 'accounts.update');
                    $router->post('/{agency:\\d+}/comptes/{id:\\d+}/activation', [AgencyAccountController::class, 'toggle'], 'accounts.toggle');
                    $router->post('/{agency:\\d+}/comptes/{id:\\d+}/invitation', [AgencyAccountController::class, 'invite'], 'accounts.invite');
                    $router->post('/{agency:\\d+}/comptes/{id:\\d+}/supprimer', [AgencyAccountController::class, 'destroy'], 'accounts.destroy');
                });

                $router->get('/demandes-partenariat', [PartnerRequestController::class, 'index'], 'partners.index');
                $router->get('/demandes-partenariat/{id:\\d+}', [PartnerRequestController::class, 'show'], 'partners.show');
                $router->post('/demandes-partenariat/{id:\\d+}', [PartnerRequestController::class, 'update'], 'partners.update');
                $router->get('/demandes-partenariat/{id:\\d+}/pieces/{file:\\d+}', [PartnerRequestController::class, 'document'], 'partners.document');

                // Biens confiés par des particuliers : étude, décision, création de l'annonce brouillon
                $router->get('/biens-confies', [SubmissionController::class, 'index'], 'submissions.index');
                $router->get('/biens-confies/{id:\\d+}', [SubmissionController::class, 'show'], 'submissions.show');
                $router->post('/biens-confies/{id:\\d+}', [SubmissionController::class, 'update'], 'submissions.update');
                $router->post('/biens-confies/{id:\\d+}/annonce', [SubmissionController::class, 'convert'], 'submissions.convert');
                $router->get('/biens-confies/{id:\\d+}/fichiers/{file:\\d+}', [SubmissionController::class, 'file'], 'submissions.file');

                // Journal d'activité (§ 5.2 du cahier des charges) : lecture seule, aucune écriture exposée
                $router->get('/journal', [ActivityController::class, 'index'], 'activity');

                // Exports CSV (lot 1.12) : pays du site, jamais un compte agence
                $router->get('/exports', [ExportController::class, 'index'], 'exports');
                $router->get('/exports/annonces.csv', [ExportController::class, 'properties'], 'exports.properties');
                $router->get('/exports/contacts.csv', [ExportController::class, 'leads'], 'exports.leads');
            });

            // Contenu éditorial (lot 2.2, Super Admin) : pages, actualités, bannières
            $router->group(['middleware' => [RequireRole::class . ':super_admin']], static function (Router $router): void {
                foreach ([
                    ['pages', 'Page'],
                    ['actualites', 'Post'],
                    ['bannieres', 'Banner'],
                ] as [$prefix, $suffix]) {
                    // pages() / posts() / banners() pour la liste, puis createX, storeX…
                    $router->get('/' . $prefix, [ContentController::class, lcfirst($suffix) . 's'], 'content.' . $prefix);
                    $router->get('/' . $prefix . '/ajouter', [ContentController::class, 'create' . $suffix], 'content.' . $prefix . '.create');
                    $router->post('/' . $prefix, [ContentController::class, 'store' . $suffix], 'content.' . $prefix . '.store');
                    $router->get('/' . $prefix . '/{id:\\d+}/modifier', [ContentController::class, 'edit' . $suffix], 'content.' . $prefix . '.edit');
                    $router->post('/' . $prefix . '/{id:\\d+}', [ContentController::class, 'update' . $suffix], 'content.' . $prefix . '.update');
                    $router->post('/' . $prefix . '/{id:\\d+}/supprimer', [ContentController::class, 'destroy' . $suffix], 'content.' . $prefix . '.destroy');
                }
            });

            // Référencement (lot 2.1, Super Admin) : balises méta et redirections du site courant
            $router->group(['prefix' => '/seo', 'as' => 'seo.', 'middleware' => [RequireRole::class . ':super_admin']], static function (Router $router): void {
                $router->get('/redirections', [SeoController::class, 'redirects'], 'redirects');
                $router->get('/redirections/ajouter', [SeoController::class, 'createRedirect'], 'redirects.create');
                $router->post('/redirections', [SeoController::class, 'storeRedirect'], 'redirects.store');
                $router->get('/redirections/{id:\\d+}/modifier', [SeoController::class, 'editRedirect'], 'redirects.edit');
                $router->post('/redirections/{id:\\d+}', [SeoController::class, 'updateRedirect'], 'redirects.update');
                $router->post('/redirections/{id:\\d+}/supprimer', [SeoController::class, 'destroyRedirect'], 'redirects.destroy');

                $router->get('/', [SeoController::class, 'index'], 'index');
                $router->get('/ajouter', [SeoController::class, 'create'], 'create');
                $router->post('/', [SeoController::class, 'store'], 'store');
                $router->get('/{id:\\d+}/modifier', [SeoController::class, 'edit'], 'edit');
                $router->post('/{id:\\d+}', [SeoController::class, 'update'], 'update');
                $router->post('/{id:\\d+}/supprimer', [SeoController::class, 'destroy'], 'destroy');
            });

            // Paramètres de la plateforme (lot 1.12, Super Admin)
            $router->group(['middleware' => [RequireRole::class . ':super_admin']], static function (Router $router): void {
                $router->get('/parametres', [SettingsController::class, 'edit'], 'settings');
                $router->post('/parametres', [SettingsController::class, 'update'], 'settings.update');
            });

            // Utilisateurs internes (Super Admin)
            $router->group(['prefix' => '/utilisateurs', 'as' => 'users.', 'middleware' => [RequireRole::class . ':super_admin']], static function (Router $router): void {
                $router->get('/', [StaffUserController::class, 'index'], 'index');
                $router->get('/ajouter', [StaffUserController::class, 'create'], 'create');
                $router->post('/', [StaffUserController::class, 'store'], 'store');
                $router->get('/{id:\\d+}/modifier', [StaffUserController::class, 'edit'], 'edit');
                $router->post('/{id:\\d+}', [StaffUserController::class, 'update'], 'update');
                $router->post('/{id:\\d+}/activation', [StaffUserController::class, 'toggle'], 'toggle');
                $router->post('/{id:\\d+}/invitation', [StaffUserController::class, 'invite'], 'invite');
                $router->post('/{id:\\d+}/supprimer', [StaffUserController::class, 'destroy'], 'destroy');
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

            // Guide d'utilisation : lecture seule, tous les rôles connectés (chacun voit son périmètre)
            $router->get('/guide', [GuideController::class, 'index'], 'guide');

            // Tableau de bord : réel pour les comptes agence (lot 1.7), maquette locale pour l'équipe interne (lot 1.12)
            $router->get('/', [DashboardController::class, 'index'], 'dashboard');
            if ($app->config->get('app.preview')) {
                $router->get('/erreur-500', [CmsadminPreviewController::class, 'serverError'], 'preview.error');
            }
        });
    });
};
