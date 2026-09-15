<?php

declare(strict_types=1);

namespace App\Controllers\Preview;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * PROVISOIRE — maquettes du site public avec données fictives (bin/preview/front-fixtures.php).
 * Routes déclarées uniquement si app.preview (APP_ENV=local) ; l'accueil réel arrive au lot 1.8.
 */
final class FrontPreviewController extends Controller
{
    public function home(Request $request): Response
    {
        $fixtures = $this->fixtures();

        return $this->page('front/layouts/app', 'front/pages/home-mockup', $fixtures['home'], [
            'title' => 'Annonces immobilières à Abidjan et en Côte d’Ivoire',
            'description' => 'Villas, appartements, terrains et bureaux à vendre ou à louer à Abidjan, publiés par des agences partenaires vérifiées.',
            'headerOverlay' => true,
            'preloadImage' => $fixtures['home']['hero']['slides'][0]['image'] . '-1920.webp',
            'pageScripts' => ['js/hero.js'],
            'noindex' => true,
        ]);
    }

    public function styleguide(Request $request): Response
    {
        return $this->page('front/layouts/app', 'front/pages/styleguide', $this->fixtures()['styleguide'], [
            'title' => 'Charte graphique',
            'description' => 'Système de design du site public.',
            'noindex' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function fixtures(): array
    {
        return require $this->app->root . '/bin/preview/front-fixtures.php';
    }
}
