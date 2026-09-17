<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Support\Paginator;

/**
 * Journal d'activité (§ 5.2 du cahier des charges) : qui a validé, modifié ou supprimé quoi.
 *
 * **Écran en lecture seule, volontairement.** Aucune action d'écriture n'est exposée : un journal
 * dont une entrée peut être effacée depuis l'interface ne prouve plus rien. La purge des lignes
 * anciennes, si elle devient nécessaire, se fera en base.
 *
 * Périmètre : rôle `staff`. Un Admin Pays ne voit que son pays ; un Super Admin voit tous les pays
 * et les actions système (sans pays). Les comptes agence n'y ont pas accès — le journal contient
 * les décisions de modération et les actions des autres agences.
 */
final class ActivityController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): Response
    {
        $countryId = $this->scope($request);
        $repo = $this->app->activityLog();

        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'action' => mb_substr(trim((string) $request->query('action', '')), 0, 60),
            'module' => mb_substr(trim((string) $request->query('module', '')), 0, 40),
            'utilisateur' => (string) $request->query('utilisateur', ''),
            'du' => (string) $request->query('du', ''),
            'au' => (string) $request->query('au', ''),
        ];

        $requested = Paginator::fromRequest($request, PHP_INT_MAX, self::PER_PAGE);
        $result = $repo->paginate($countryId, $filters, self::PER_PAGE, $requested->offset);
        $paginator = new Paginator($result['total'], self::PER_PAGE, $requested->page);
        if ($paginator->page !== $requested->page) {
            $result = $repo->paginate($countryId, $filters, self::PER_PAGE, $paginator->offset);
        }

        $actions = $repo->actions($countryId);

        return $this->render($request, 'activity/index', [
            'rows' => $result['rows'],
            'filters' => $filters,
            'actions' => $actions,
            'modules' => $this->modules($actions),
            'users' => $repo->users($countryId),
            'showCountry' => $countryId === null,
            'pagination' => $paginator->toArray(),
        ], [
            'title' => __('activity.title'),
            'activeMenu' => 'logs',
        ]);
    }

    /**
     * Pays imposé au dépôt : `null` pour un Super Admin (tous les pays et les actions système).
     * Il n'est jamais lu dans la requête.
     */
    private function scope(Request $request): ?int
    {
        $user = $this->user($request);
        if (!$user->isStaff()) {
            throw new HttpException(403);
        }
        if ($user->isSuperAdmin()) {
            return null;
        }

        return $user->countryId ?? site()?->country->id ?? throw new HttpException(403);
    }

    /**
     * Familles d'actions présentes (ce qui précède le point), pour le filtre « Module ».
     *
     * @param list<string> $actions
     * @return list<string>
     */
    private function modules(array $actions): array
    {
        $modules = [];
        foreach ($actions as $action) {
            $modules[explode('.', $action)[0]] = true;
        }
        ksort($modules);

        return array_keys($modules);
    }
}
