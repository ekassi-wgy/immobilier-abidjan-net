<?php

declare(strict_types=1);

/**
 * Routes du site public.
 */

use App\Controllers\Front\AgencyController;
use App\Controllers\Front\ContactController;
use App\Controllers\Front\FavoriteController;
use App\Controllers\Front\HomeController;
use App\Controllers\Front\Owner\AccountController as OwnerAccountController;
use App\Controllers\Front\Owner\SpaceController as OwnerSpaceController;
use App\Controllers\Front\Owner\SubmissionController as OwnerSubmissionController;
use App\Controllers\Front\PageController;
use App\Controllers\Front\PostController;
use App\Controllers\Front\PropertyController;
use App\Controllers\Front\SearchController;
use App\Controllers\Front\SitemapController;
use App\Controllers\Preview\FrontPreviewController;
use App\Core\App;
use App\Core\Router;
use App\Middlewares\AuthenticateOwner;

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

    // Vitrine des partenaires : ni coordonnées ni contact direct (Weblogy est l'intermédiaire exclusif).
    // L'ancien annuaire (/agences, profils et formulaire de contact d'agence) redirige en 301.
    $router->get('/partenaires', [AgencyController::class, 'index'], 'agencies');
    $router->get('/agences', [AgencyController::class, 'legacy']);
    $router->get('/agences/{slug:[a-z0-9-]+}', [AgencyController::class, 'legacy']);

    // Formulaires publics autonomes (lot 1.11)
    $router->get('/contact', [ContactController::class, 'contact'], 'contact');
    $router->post('/contact', [ContactController::class, 'sendContact']);
    $router->get('/devenir-partenaire', [ContactController::class, 'partner'], 'partner');
    $router->post('/devenir-partenaire', [ContactController::class, 'sendPartner']);

    // Particuliers : Weblogy est l'intermédiaire exclusif, un particulier CONFIE son bien (il ne publie pas).
    // L'ancien formulaire anonyme « Déposer un bien » redirige vers la présentation du service.
    $router->get('/confiez-nous-votre-bien', [OwnerSubmissionController::class, 'landing'], 'entrust');
    $router->get('/deposer-un-bien', [OwnerSubmissionController::class, 'legacy']);

    // Espace propriétaire : compte obligatoire (garde « owner », jamais le back-office)
    $router->group(['prefix' => '/mon-espace'], static function (Router $router): void {
        $router->get('/connexion', [OwnerAccountController::class, 'showLogin'], 'owner.login');
        $router->post('/connexion', [OwnerAccountController::class, 'login']);
        $router->get('/inscription', [OwnerAccountController::class, 'showRegister'], 'owner.register');
        $router->post('/inscription', [OwnerAccountController::class, 'register']);
        $router->get('/mot-de-passe-oublie', [OwnerAccountController::class, 'showForgot'], 'owner.forgot');
        $router->post('/mot-de-passe-oublie', [OwnerAccountController::class, 'forgot']);
        $router->get('/mot-de-passe/{token:[A-Za-z0-9_-]+}', [OwnerAccountController::class, 'showReset'], 'owner.reset');
        $router->post('/mot-de-passe/{token:[A-Za-z0-9_-]+}', [OwnerAccountController::class, 'reset']);
        $router->get('/confirmer-email/{token:[A-Za-z0-9_-]+}', [OwnerAccountController::class, 'confirmEmail'], 'owner.verify');

        $router->group(['middleware' => [AuthenticateOwner::class]], static function (Router $router): void {
            $router->get('', [OwnerSpaceController::class, 'index'], 'owner.space');
            $router->post('/deconnexion', [OwnerAccountController::class, 'logout'], 'owner.logout');
            $router->post('/renvoyer-confirmation', [OwnerAccountController::class, 'resendVerification'], 'owner.verify.resend');
            $router->get('/profil', [OwnerSpaceController::class, 'profile'], 'owner.profile');
            $router->post('/profil', [OwnerSpaceController::class, 'updateProfile']);
            $router->post('/profil/mot-de-passe', [OwnerSpaceController::class, 'updatePassword'], 'owner.password');
            $router->get('/biens/nouveau', [OwnerSubmissionController::class, 'create'], 'owner.submission.create');
            $router->post('/biens', [OwnerSubmissionController::class, 'store'], 'owner.submission.store');
            $router->get('/biens/{id:[0-9]+}', [OwnerSubmissionController::class, 'show'], 'owner.submission.show');
            $router->post('/biens/{id:[0-9]+}/retirer', [OwnerSubmissionController::class, 'withdraw'], 'owner.submission.withdraw');
            $router->get('/biens/{id:[0-9]+}/photos/{file:[0-9]+}', [OwnerSubmissionController::class, 'photo'], 'owner.submission.photo');
        });
    });

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
