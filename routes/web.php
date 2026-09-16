<?php

declare(strict_types=1);

/**
 * Routes du site public.
 */

use App\Controllers\Front\AgencyController;
use App\Controllers\Front\ContactController;
use App\Controllers\Front\FavoriteController;
use App\Controllers\Front\HomeController;
use App\Controllers\Front\PageController;
use App\Controllers\Front\PostController;
use App\Controllers\Front\PropertyController;
use App\Controllers\Front\SearchController;
use App\Controllers\Front\SitemapController;
use App\Controllers\Preview\FrontPreviewController;
use App\Core\App;
use App\Core\Router;

return static function (Router $router, App $app): void {
    $router->get('/', [HomeController::class, 'index'], 'home');
    $router->get('/favoris', [FavoriteController::class, 'index'], 'favorites');

    // Actualités (lot 2.2)
    $router->get('/actualites', [PostController::class, 'index'], 'posts');
    $router->get('/actualites/{slug:[a-z0-9-]+}', [PostController::class, 'show'], 'post.show');

    // Référencement (lot 2.1)
    $router->get('/sitemap.xml', [SitemapController::class, 'sitemap'], 'sitemap');
    $router->get('/robots.txt', [SitemapController::class, 'robots'], 'robots');

    // Fiche annonce (lot 1.10). Déclarée avant le bloc de recherche ci-dessous.
    $listing = '/annonces/{slug:[a-z0-9-]+}-ref{id:[0-9]+}';
    $router->get($listing, [PropertyController::class, 'show'], 'property.show');
    $router->post($listing . '/contact', [PropertyController::class, 'contact'], 'property.contact');

    // Annuaire et profil public des agences (lot 1.11)
    $router->get('/agences', [AgencyController::class, 'index'], 'agencies');
    $router->get('/agences/{slug:[a-z0-9-]+}', [AgencyController::class, 'show'], 'agency.show');
    $router->post('/agences/{slug:[a-z0-9-]+}/contact', [AgencyController::class, 'contact'], 'agency.contact');

    // Formulaires publics autonomes (lot 1.11)
    $router->get('/contact', [ContactController::class, 'contact'], 'contact');
    $router->post('/contact', [ContactController::class, 'sendContact']);
    $router->get('/devenir-partenaire', [ContactController::class, 'partner'], 'partner');
    $router->post('/devenir-partenaire', [ContactController::class, 'sendPartner']);
    $router->get('/deposer-un-bien', [ContactController::class, 'submit'], 'submit-property');
    $router->post('/deposer-un-bien', [ContactController::class, 'sendSubmit']);

    // Pages éditoriales et légales : une route par page publiée (table `pages`, liste en cache).
    // Elles sont déclarées ici, au-dessus du bloc de recherche : un slug de page ne doit donc
    // jamais reprendre un slug de transaction, sinon la recherche passerait avant.
    foreach ($app->pages()->publishedSlugs() as $pageSlug) {
        // Le slug est passé en paramètre de route (motif littéral) : PageController::show()
        // le reçoit comme n'importe quel autre segment nommé.
        $router->get('/{slug:' . preg_quote($pageSlug, '#') . '}', [PageController::class, 'show']);
    }

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
