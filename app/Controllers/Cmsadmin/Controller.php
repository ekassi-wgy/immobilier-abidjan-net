<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin;

use App\Controllers\Controller as BaseController;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use LogicException;

/**
 * Base des contrôleurs du back-office : utilisateur connecté et données communes du layout.
 */
abstract class Controller extends BaseController
{
    /** Utilisateur posé par le middleware Authenticate. */
    protected function user(Request $request): User
    {
        $user = $request->attribute('user');
        if (!$user instanceof User) {
            throw new LogicException('Route du back-office sans middleware Authenticate.');
        }

        return $user;
    }

    /**
     * Page dans le layout principal (menu, barre supérieure).
     *
     * @param array<string, mixed> $data       Données de la vue (reçoit aussi user, site, csrfToken)
     * @param array<string, mixed> $layoutData title, activeMenu, plugins, pageScripts, counters…
     */
    protected function render(Request $request, string $view, array $data = [], array $layoutData = [], int $status = 200): Response
    {
        $shared = $this->shared($request);
        $layoutData += ['counters' => [], 'flash' => []];
        $layoutData['flash'] = [...$this->app->session()->flashes(), ...$layoutData['flash']];

        return $this->page('cmsadmin/layouts/app', 'cmsadmin/pages/' . $view, $data + $shared, $layoutData + $shared, $status);
    }

    /**
     * Écran hors menu (connexion, mot de passe…), layout « auth ».
     *
     * @param array<string, mixed> $data
     */
    protected function renderAuth(string $view, array $data, string $title, int $status = 200): Response
    {
        return $this->page('cmsadmin/layouts/auth', 'cmsadmin/pages/' . $view, $data + [
            'csrfToken' => csrf_token(),
            'flash' => $this->app->session()->flashes(),
        ], ['title' => $title, 'variant' => 'split'], $status);
    }

    /**
     * Données communes aux vues du layout principal (format attendu par navbar et sidebar).
     *
     * @return array{user: array<string, mixed>, site: array<string, string>, csrfToken: string}
     */
    protected function shared(Request $request): array
    {
        $user = $this->user($request);
        $site = site();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->fullName(),
                'email' => $user->email,
                'role' => $user->menuRole(),
                'role_label' => __('auth.roles.' . $user->role),
                'agency_name' => $user->agencyName,
            ],
            'site' => [
                'name' => $site->name ?? '',
                'country' => $site?->country->localizedName(locale()) ?? '',
                'currency' => $site->country->currencySymbol ?? '',
                'url' => url(),
            ],
            'csrfToken' => csrf_token(),
        ];
    }
}
