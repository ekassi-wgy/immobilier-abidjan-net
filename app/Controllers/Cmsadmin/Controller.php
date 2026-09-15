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
        $notifier = $this->app->notifier();
        $userId = $this->user($request)->id;
        $layoutData += [
            'counters' => $this->counters($request),
            'flash' => [],
            'notifications' => $notifier->latest($userId),
            'unread' => $notifier->unreadCount($userId),
        ];
        $layoutData['flash'] = [...$this->app->session()->flashes(), ...$layoutData['flash']];

        return $this->page('cmsadmin/layouts/app', 'cmsadmin/pages/' . $view, $data + $shared, $layoutData + $shared, $status);
    }

    /**
     * Compteurs affichés dans le menu (pastilles). Les annonces en attente et contacts arriveront avec leurs modules.
     *
     * @return array<string, int>
     */
    protected function counters(Request $request): array
    {
        $user = $this->user($request);
        $site = site();
        if ($site === null) {
            return [];
        }
        if ($user->isAgency()) {
            $counts = $this->app->properties()->countsByStatus($site->country->id, (int) $user->agencyId);

            return ['pending_properties' => $counts['pending'] + $counts['revision']];
        }

        return [
            'partner_requests' => (int) $this->app->db()->scalar(
                "SELECT COUNT(*) FROM partner_requests WHERE country_id = :country AND status = 'new'",
                ['country' => $site->country->id]
            ),
            'pending_properties' => $this->app->properties()->toReviewCount($site->country->id),
        ];
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
     * Journalise une action d'écriture du back-office (activity_logs).
     *
     * @param array{before?: array<string, mixed>, after?: array<string, mixed>}|null $changes
     */
    protected function log(Request $request, string $action, string $entityType, int|string|null $entityId, ?string $description = null, ?array $changes = null, ?int $countryId = null): void
    {
        $this->app->activity()->log(
            $action,
            $this->user($request)->id,
            $countryId ?? site()?->country->id,
            $entityType,
            $entityId,
            $description,
            $changes,
            $request
        );
    }

    /**
     * Différences entre deux états (seuls les champs modifiés), pour le journal.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array{before: array<string, mixed>, after: array<string, mixed>}|null
     */
    protected function diff(array $before, array $after): ?array
    {
        $changes = ['before' => [], 'after' => []];
        foreach ($after as $key => $value) {
            if (array_key_exists($key, $before) && (string) $before[$key] === (string) $value) {
                continue;
            }
            $changes['before'][$key] = $before[$key] ?? null;
            $changes['after'][$key] = $value;
        }

        return $changes['after'] === [] ? null : $changes;
    }

    /** Retour à une liste en conservant ses filtres (paramètre « _back » / « retour » limité au back-office). */
    protected function backTo(Request $request, string $default): Response
    {
        $back = $this->backPath($request);

        return Response::redirect(url($back !== '' ? $back : $default), 303);
    }

    /** Chemin de retour validé (chemin interne à /cmsadmin, sinon chaîne vide). */
    protected function backPath(Request $request): string
    {
        $back = (string) ($request->input('_back') ?? $request->query('retour') ?? '');

        return preg_match('#^/cmsadmin/[A-Za-z0-9/_\-]*(\?[A-Za-z0-9=&%_.\-+]*)?$#', $back) === 1 && !str_contains($back, '//') ? $back : '';
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
