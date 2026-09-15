<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Agencies;

use App\Controllers\Cmsadmin\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

/**
 * Utilisateurs internes : Super Admins et Admins Pays (réservé au Super Admin).
 *
 * Garde-fous : on ne modifie pas son propre rôle, on ne se désactive ni ne se supprime soi-même,
 * et il reste toujours au moins un Super Admin actif.
 */
final class StaffUserController extends Controller
{
    use AccountSupport;

    private const ROLES = [User::SUPER_ADMIN, User::COUNTRY_ADMIN];

    public function index(Request $request): Response
    {
        $role = (string) $request->query('role', '');
        $etat = (string) $request->query('etat', '');
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'role' => in_array($role, self::ROLES, true) ? $role : '',
            'etat' => in_array($etat, ['actifs', 'inactifs'], true) ? $etat : '',
        ];

        return $this->render($request, 'users/index', [
            'rows' => $this->app->users()->staff($filters),
            'filters' => $filters,
            'roles' => $this->roleOptions(),
            'currentUserId' => $this->user($request)->id,
        ], [
            'title' => __('users.title'),
            'activeMenu' => 'users',
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, null, ['role' => User::COUNTRY_ADMIN, 'country_id' => site()?->country->id]);
    }

    public function store(Request $request): Response
    {
        [$data, $errors] = $this->validate($request, null);
        if ($errors !== []) {
            return $this->form($request, null, $request->all(), $errors, 422);
        }

        $id = $this->createAccount($request, $data);

        return $this->redirectToRoute('cmsadmin.users.edit', ['id' => $id], 303);
    }

    public function edit(Request $request, string $id): Response
    {
        return $this->form($request, $this->find((int) $id));
    }

    public function update(Request $request, string $id): Response
    {
        $account = $this->find((int) $id);
        [$data, $errors] = $this->validate($request, $account);
        if ($errors !== []) {
            return $this->form($request, $account, $request->all() + $account, $errors, 422);
        }

        $users = $this->app->users();
        $users->update((int) $account['id'], $data);
        if ($account['email'] !== $data['email'] || $account['role'] !== $data['role'] || (string) $account['country_id'] !== (string) $data['country_id']) {
            $users->revokeAccess((int) $account['id']);
        }
        $this->log($request, 'user.updated', 'user', (int) $account['id'], $data['email'], $this->diff($account, $data), $data['country_id']);
        $this->flash('success', __('users.flash.updated', ['name' => $data['first_name'] . ' ' . $data['last_name']]));

        return $this->redirectToRoute('cmsadmin.users.edit', ['id' => (int) $account['id']], 303);
    }

    public function toggle(Request $request, string $id): Response
    {
        $account = $this->find((int) $id);
        $active = !(bool) $account['is_active'];
        $name = $account['first_name'] . ' ' . $account['last_name'];

        if (!$active && ($error = $this->guardRemoval($request, $account)) !== null) {
            $this->flash('error', $error);

            return $this->backTo($request, '/cmsadmin/utilisateurs');
        }

        $users = $this->app->users();
        $users->update((int) $account['id'], ['is_active' => $active ? 1 : 0]);
        if (!$active) {
            $users->revokeAccess((int) $account['id']);
        }
        $this->log($request, $active ? 'user.activated' : 'user.deactivated', 'user', (int) $account['id'], (string) $account['email'], null, $account['country_id'] !== null ? (int) $account['country_id'] : null);
        $this->flash('success', __($active ? 'users.flash.activated' : 'users.flash.deactivated', ['name' => $name]));

        return $this->backTo($request, '/cmsadmin/utilisateurs');
    }

    public function invite(Request $request, string $id): Response
    {
        $account = $this->find((int) $id);
        if ((int) $account['is_active'] === 0) {
            $this->flash('error', __('users.flash.invite_inactive'));
        } else {
            $this->sendInvitation($request, (int) $account['id']);
        }

        return $this->backTo($request, '/cmsadmin/utilisateurs');
    }

    public function destroy(Request $request, string $id): Response
    {
        $account = $this->find((int) $id);
        if (($error = $this->guardRemoval($request, $account)) !== null) {
            $this->flash('error', $error);

            return $this->backTo($request, '/cmsadmin/utilisateurs');
        }

        $this->app->users()->softDelete((int) $account['id']);
        $this->log($request, 'user.deleted', 'user', (int) $account['id'], (string) $account['email'], ['before' => $account], $account['country_id'] !== null ? (int) $account['country_id'] : null);
        $this->flash('success', __('users.flash.deleted', ['name' => $account['first_name'] . ' ' . $account['last_name']]));

        return $this->redirectToRoute('cmsadmin.users.index', status: 303);
    }

    /**
     * @param array<string, mixed>|null $account
     * @param array<string, mixed>      $values
     * @param array<string, string>     $errors
     */
    private function form(Request $request, ?array $account, array $values = [], array $errors = [], int $status = 200): Response
    {
        return $this->render($request, 'users/form', [
            'account' => $account,
            'values' => $values + ($account ?? []),
            'errors' => $errors,
            'agency' => null,
            'roles' => $this->roleOptions(),
            'countries' => $this->app->countries()->options(true),
            'action' => $account !== null ? route('cmsadmin.users.update', ['id' => (int) $account['id']]) : route('cmsadmin.users.store'),
            'backUrl' => route('cmsadmin.users.index'),
            'isSelf' => $account !== null && (int) $account['id'] === $this->user($request)->id,
        ], [
            'title' => $account !== null ? $account['first_name'] . ' ' . $account['last_name'] : __('users.create'),
            'activeMenu' => 'users',
        ], $status);
    }

    /**
     * @param array<string, mixed>|null $account
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validate(Request $request, ?array $account): array
    {
        [$v, $data] = $this->validateIdentity($request, $account);
        $isSelf = $account !== null && (int) $account['id'] === $this->user($request)->id;

        if ($isSelf) {
            // Son propre rôle et son pays ne se modifient pas depuis cet écran
            $role = (string) $account['role'];
            $countryId = $account['country_id'] !== null ? (int) $account['country_id'] : null;
        } else {
            $v->required('role')->in('role', self::ROLES);
            $role = $v->string('role');
            $countryId = $v->nullableInt('country_id');
            if ($role === User::COUNTRY_ADMIN) {
                $v->required('country_id')->rule('country_id', $countryId === null || isset($this->app->countries()->options(true)[$countryId]), __('validation.in'));
            } else {
                $countryId = null;
            }
            if ($account !== null && $account['role'] === User::SUPER_ADMIN && $role !== User::SUPER_ADMIN && (int) $account['is_active'] === 1
                && $this->app->users()->activeSuperAdminsCount() <= 1) {
                $v->add('role', __('users.last_super_admin'));
            }
        }

        unset($data['whatsapp']);

        return [$data + ['role' => $role, 'country_id' => $countryId], $v->errors()];
    }

    /** @param array<string, mixed> $account */
    private function guardRemoval(Request $request, array $account): ?string
    {
        if ((int) $account['id'] === $this->user($request)->id) {
            return __('users.cannot_remove_self');
        }
        if ($account['role'] === User::SUPER_ADMIN && (int) $account['is_active'] === 1 && $this->app->users()->activeSuperAdminsCount() <= 1) {
            return __('users.last_super_admin');
        }

        return null;
    }

    /** @return array<string, string> */
    private function roleOptions(): array
    {
        return array_combine(self::ROLES, array_map(static fn (string $role): string => __('auth.roles.' . $role), self::ROLES));
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        $account = $this->app->users()->row($id);
        if ($account === null || !in_array($account['role'], self::ROLES, true)) {
            throw new HttpException(404);
        }

        return $account;
    }
}
