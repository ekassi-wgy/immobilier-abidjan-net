<?php

declare(strict_types=1);

/**
 * PRÉVISUALISATION du site public — développement local uniquement.
 * Inclus par public/index.php. Remplacé par le vrai routeur au lot 1.1.
 *
 *   /            → maquette de l'accueil (hero + recherche + biens à la une)
 *   /styleguide  → charte graphique
 */

require_once __DIR__ . '/../../app/Support/helpers.php';
$fixtures = require __DIR__ . '/front-fixtures.php';

$route = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');

switch ($route) {
    case '':
        echo render_view('front/layouts/app', [
            'title' => 'Annonces immobilières à Abidjan et en Côte d’Ivoire',
            'description' => 'Villas, appartements, terrains et bureaux à vendre ou à louer à Abidjan, publiés par des agences partenaires vérifiées.',
            'headerOverlay' => true,
            'preloadImage' => $fixtures['home']['hero']['slides'][0]['image'] . '-1920.webp',
            'pageScripts' => ['js/hero.js'],
            'noindex' => true,
            'content' => render_view('front/pages/home-mockup', $fixtures['home']),
        ]);
        break;

    case '/styleguide':
        echo render_view('front/layouts/app', [
            'title' => 'Charte graphique',
            'description' => 'Système de design du site public.',
            'noindex' => true,
            'content' => render_view('front/pages/styleguide', $fixtures['styleguide']),
        ]);
        break;

    default:
        http_response_code(404);
        echo render_view('front/layouts/app', [
            'title' => 'Page en préparation',
            'description' => '',
            'noindex' => true,
            'content' => '<section class="im-section"><div class="im-container"><p class="im-eyebrow">Maquette</p>'
                . '<h1 class="im-h1" style="margin-top:.75rem">Cette page n’est pas encore réalisée</h1>'
                . '<p class="im-lead" style="margin-top:1rem">Elle sera construite dans un prochain lot. '
                . '<a class="im-link-underline" href="' . e(url()) . '">Retour à l’accueil</a> · '
                . '<a class="im-link-underline" href="' . e(url('styleguide')) . '">Charte graphique</a></p></div></section>',
        ]);
}
