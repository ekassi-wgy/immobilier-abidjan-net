<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Response;

/**
 * Contrôleur de base : rendu de pages et redirections.
 *
 * Chaque action reçoit la requête puis les paramètres de route nommés :
 *   public function show(Request $request, string $slug): Response
 */
abstract class Controller
{
    public function __construct(protected readonly App $app)
    {
    }

    /**
     * Vue insérée dans un layout.
     *
     * @param array<string, mixed> $data       Données de la vue
     * @param array<string, mixed> $layoutData Titre, description, scripts… du layout
     */
    protected function page(string $layout, string $view, array $data = [], array $layoutData = [], int $status = 200): Response
    {
        return Response::html($this->app->view()->page($layout, $view, $data, $layoutData), $status);
    }

    protected function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect(url($path), $status);
    }

    /** @param array<string, string|int> $params */
    protected function redirectToRoute(string $name, array $params = [], int $status = 302): Response
    {
        return Response::redirect(route($name, $params), $status);
    }

    /** Message affiché sur la page suivante (après redirection). */
    protected function flash(string $type, string $message): void
    {
        $this->app->session()->flash($type, $message);
    }
}
