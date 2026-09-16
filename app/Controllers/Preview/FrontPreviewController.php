<?php

declare(strict_types=1);

namespace App\Controllers\Preview;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * PROVISOIRE — charte graphique du site public avec des données fictives (bin/preview/front-fixtures.php).
 * Route déclarée uniquement si app.preview (APP_ENV=local) ; l'accueil réel est servi par Front\HomeController (lot 1.8).
 */
final class FrontPreviewController extends Controller
{
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
