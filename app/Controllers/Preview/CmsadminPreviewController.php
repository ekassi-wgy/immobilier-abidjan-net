<?php

declare(strict_types=1);

namespace App\Controllers\Preview;

use App\Controllers\Cmsadmin\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * PROVISOIRE — écrans du back-office avec données fictives (bin/preview/fixtures.php).
 * Routes déclarées uniquement si app.preview (APP_ENV=local), derrière la vraie authentification (lot 1.3).
 * Chaque écran disparaît quand son module réel est livré.
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

    public function properties(Request $request): Response
    {
        return $this->screen($request, 'properties/index', fn (array $f, string $role): array => $f['properties']($role, $request->queryAll()), [
            'title' => 'Annonces',
            'activeMenu' => $request->query('statut') === 'en-attente' ? 'properties.pending' : 'properties.all',
            'plugins' => ['select2'],
            'flash' => $request->query('flash') !== null ? [['type' => 'success', 'message' => 'L’annonce IAN-24518 a été publiée.']] : [],
        ]);
    }

    public function propertyForm(Request $request, ?string $reference = null): Response
    {
        $isEdit = $reference !== null;

        return $this->screen($request, 'properties/form', fn (array $f, string $role): array => $f['form']($role, $isEdit, $request->query('erreurs') !== null), [
            'title' => $isEdit ? 'Modifier l’annonce' : 'Nouvelle annonce',
            'activeMenu' => $isEdit ? 'properties.all' : 'properties.create',
            'plugins' => ['select2'],
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
