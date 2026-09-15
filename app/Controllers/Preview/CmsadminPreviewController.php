<?php

declare(strict_types=1);

namespace App\Controllers\Preview;

use App\Controllers\Cmsadmin\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * PROVISOIRE — écrans du back-office avec données fictives (bin/preview/fixtures.php).
 * Routes déclarées uniquement si app.preview (APP_ENV=local), derrière la vraie authentification (lot 1.3).
 * Le module Annonces réel (lot 1.6) a remplacé les écrans fictifs correspondants ; reste le tableau de bord (lot 1.12).
 *
 * Le rôle affiché est celui du compte connecté. Un Super Admin peut prévisualiser les écrans d'un autre rôle
 * avec ?role=country_admin|agency|super_admin (mémorisé par cookie) — prévisualisation locale uniquement.
 */
final class CmsadminPreviewController extends Controller
{
    private const ROLES = ['super_admin', 'country_admin', 'agency'];

    public function dashboard(Request $request): Response
    {
        return $this->screen($request, 'dashboard/index', fn (array $f, string $role): array => $f['dashboard']($role), [
            'title' => 'Tableau de bord',
            'activeMenu' => 'dashboard',
            'plugins' => ['chart'],
            'pageScripts' => ['js/dashboard.js'],
        ]);
    }

    public function serverError(Request $request): Response
    {
        return $this->page('cmsadmin/layouts/auth', 'cmsadmin/pages/errors/error', ['code' => 500], [
            'title' => __('errors.500.title'),
            'variant' => 'center',
        ], 500);
    }

    /**
     * @param callable(array<string, mixed>, string): array<string, mixed> $data
     * @param array<string, mixed> $layoutData
     */
    private function screen(Request $request, string $view, callable $data, array $layoutData): Response
    {
        $fixtures = require $this->app->root . '/bin/preview/fixtures.php';
        $role = $this->role($request);
        // Compteurs du menu : encore fictifs
        $layoutData['counters'] = $fixtures['shared']($role)['counters'];

        $shared = $this->shared($request);
        $shared['user']['role'] = $role;

        $response = $this->render($request, $view, $data($fixtures, $role) + ['user' => $shared['user']], ['user' => $shared['user']] + $layoutData);
        if ($request->query('role') === $role && $this->user($request)->isSuperAdmin()) {
            setcookie('preview_role', $role, ['path' => '/cmsadmin', 'httponly' => true, 'samesite' => 'Lax']);
        }

        return $response;
    }

    private function role(Request $request): string
    {
        $user = $this->user($request);
        if (!$user->isSuperAdmin()) {
            return $user->menuRole();
        }

        foreach ([$request->query('role'), $request->cookie('preview_role')] as $candidate) {
            if (in_array($candidate, self::ROLES, true)) {
                return $candidate;
            }
        }

        return 'super_admin';
    }
}
