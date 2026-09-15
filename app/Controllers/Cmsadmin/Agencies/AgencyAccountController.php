<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Agencies;

use App\Controllers\Cmsadmin\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

/**
 * Comptes d'une agence partenaire (responsable, agents), gérés par le Super Admin ou l'Admin Pays.
 * Chaque action vérifie que le compte appartient bien à l'agence, et l'agence au pays du site.
 */
final class AgencyAccountController extends Controller
{
    use AccountSupport;

    private const ROLES = [User::AGENCY_OWNER, User::AGENCY_AGENT];

    public function create(Request $request, string $agency): Response
    {
        return $this->form($request, $this->agency((int) $agency), null, ['role' => User::AGENCY_AGENT]);
    }

    public function store(Request $request, string $agency): Response
    {
        $agencyRow = $this->agency((int) $agency);
        [$data, $errors] = $this->validate($request, null);
        if ($errors !== []) {
            return $this->form($request, $agencyRow, null, $request->all(), $errors, 422);
        }

        $this->createAccount($request, $data + ['country_id' => (int) $agencyRow['country_id'], 'agency_id' => (int) $agencyRow['id']], (string) $agencyRow['name']);

        return $this->toAgency($agencyRow);
    }

    public function edit(Request $request, string $agency, string $id): Response
    {
        $agencyRow = $this->agency((int) $agency);

        return $this->form($request, $agencyRow, $this->account($agencyRow, (int) $id));
    }

    public function update(Request $request, string $agency, string $id): Response
    {
        $agencyRow = $this->agency((int) $agency);
        $account = $this->account($agencyRow, (int) $id);
        [$data, $errors] = $this->validate($request, $account);
        if ($errors !== []) {
            return $this->form($request, $agencyRow, $account, $request->all() + $account, $errors, 422);
        }

        $this->app->users()->update((int) $account['id'], $data);
        if ($account['email'] !== $data['email']) {
            $this->app->users()->revokeAccess((int) $account['id']);
        }
        $this->log($request, 'user.updated', 'user', (int) $account['id'], $data['email'], $this->diff($account, $data), (int) $agencyRow['country_id']);
        $this->flash('success', __('users.flash.updated', ['name' => $data['first_name'] . ' ' . $data['last_name']]));

        return $this->toAgency($agencyRow);
    }

    public function toggle(Request $request, string $agency, string $id): Response
    {
        $agencyRow = $this->agency((int) $agency);
        $account = $this->account($agencyRow, (int) $id);
        $active = !(bool) $account['is_active'];

        $this->app->users()->update((int) $account['id'], ['is_active' => $active ? 1 : 0]);
        if (!$active) {
            $this->app->users()->revokeAccess((int) $account['id']);
        }
        $this->log($request, $active ? 'user.activated' : 'user.deactivated', 'user', (int) $account['id'], (string) $account['email'], null, (int) $agencyRow['country_id']);
        $this->flash('success', __($active ? 'users.flash.activated' : 'users.flash.deactivated', ['name' => $account['first_name'] . ' ' . $account['last_name']]));

        return $this->toAgency($agencyRow);
    }

    public function invite(Request $request, string $agency, string $id): Response
    {
        $agencyRow = $this->agency((int) $agency);
        $account = $this->account($agencyRow, (int) $id);
        if ((int) $account['is_active'] === 0) {
            $this->flash('error', __('users.flash.invite_inactive'));
        } else {
            $this->sendInvitation($request, (int) $account['id'], (string) $agencyRow['name']);
        }

        return $this->toAgency($agencyRow);
    }

    public function destroy(Request $request, string $agency, string $id): Response
    {
        $agencyRow = $this->agency((int) $agency);
        $account = $this->account($agencyRow, (int) $id);

        $this->app->users()->softDelete((int) $account['id']);
        $this->log($request, 'user.deleted', 'user', (int) $account['id'], (string) $account['email'], ['before' => $account], (int) $agencyRow['country_id']);
        $this->flash('success', __('users.flash.deleted', ['name' => $account['first_name'] . ' ' . $account['last_name']]));

        return $this->toAgency($agencyRow);
    }

    /**
     * @param array<string, mixed>      $agency
     * @param array<string, mixed>|null $account
     * @param array<string, mixed>      $values
     * @param array<string, string>     $errors
     */
    private function form(Request $request, array $agency, ?array $account, array $values = [], array $errors = [], int $status = 200): Response
    {
        return $this->render($request, 'users/form', [
            'account' => $account,
            'values' => $values + ($account ?? []),
            'errors' => $errors,
            'agency' => $agency,
            'roles' => array_combine(self::ROLES, array_map(static fn (string $role): string => __('auth.roles.' . $role), self::ROLES)),
            'countries' => [],
            'action' => $account !== null
                ? route('cmsadmin.agencies.accounts.update', ['agency' => (int) $agency['id'], 'id' => (int) $account['id']])
                : route('cmsadmin.agencies.accounts.store', ['agency' => (int) $agency['id']]),
            'backUrl' => route('cmsadmin.agencies.edit', ['id' => (int) $agency['id']]) . '#comptes',
            'isSelf' => $account !== null && (int) $account['id'] === $this->user($request)->id,
        ], [
            'title' => $account !== null ? $account['first_name'] . ' ' . $account['last_name'] : __('users.create_agency_account'),
            'activeMenu' => 'agencies.all',
        ], $status);
    }

    /**
     * @param array<string, mixed>|null $account
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validate(Request $request, ?array $account): array
    {
        [$v, $data] = $this->validateIdentity($request, $account);
        $v->required('role')->in('role', self::ROLES);

        return [$data + ['role' => $v->string('role')], $v->errors()];
    }

    /** @return array<string, mixed> */
    private function agency(int $id): array
    {
        return $this->app->agencies()->find($id, $this->countryId()) ?? throw new HttpException(404);
    }

    /**
     * @param array<string, mixed> $agency
     * @return array<string, mixed>
     */
    private function account(array $agency, int $id): array
    {
        $account = $this->app->users()->row($id);
        if ($account === null || (int) $account['agency_id'] !== (int) $agency['id']) {
            throw new HttpException(404);
        }

        return $account;
    }

    /** @param array<string, mixed> $agency */
    private function toAgency(array $agency): Response
    {
        return Response::redirect(route('cmsadmin.agencies.edit', ['id' => (int) $agency['id']]) . '#comptes', 303);
    }
}
