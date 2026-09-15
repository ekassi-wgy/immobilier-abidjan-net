<?php

declare(strict_types=1);

namespace App\Controllers\Preview;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * PROVISOIRE — écrans du back-office avec données fictives (bin/preview/fixtures.php), sans base de données.
 * Routes déclarées uniquement si app.preview (APP_ENV=local). Chaque écran disparaît quand son module réel est livré.
 *
 * Rôle simulé : ?role=super_admin|country_admin|agency (mémorisé par cookie).
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

    public function login(Request $request): Response
    {
        $failed = $request->query('erreur') !== null;

        return $this->page('cmsadmin/layouts/auth', 'cmsadmin/pages/auth/login', [
            'csrfToken' => csrf_token(),
            'errorMessage' => $failed ? 'Identifiants incorrects.' : null,
            'email' => $failed ? 'agence@exemple.ci' : '',
        ], [
            'title' => 'Connexion',
            'variant' => 'split',
        ]);
    }

    /** Aucune authentification réelle avant le lot 1.3 : le jeton CSRF est vérifié, puis échec simulé. */
    public function loginSubmit(Request $request): Response
    {
        return $this->redirectToRoute('cmsadmin.login', ['erreur' => 1], 303);
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
        $shared = $fixtures['shared']($role);

        // Site courant réel (lot 1.2) à la place des données fictives
        $site = site();
        if ($site !== null) {
            $shared['site'] = [
                'name' => $site->name,
                'country' => $site->country->localizedName(locale()),
                'currency' => $site->country->currencySymbol,
                'url' => url(),
            ];
        }

        $response = $this->page('cmsadmin/layouts/app', 'cmsadmin/pages/' . $view, $data($fixtures, $role) + $shared, $shared + $layoutData);
        if ($request->query('role') === $role) {
            setcookie('preview_role', $role, ['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        }

        return $response;
    }

    private function role(Request $request): string
    {
        foreach ([$request->query('role'), $request->cookie('preview_role')] as $candidate) {
            if (in_array($candidate, self::ROLES, true)) {
                return $candidate;
            }
        }

        return 'super_admin';
    }
}
