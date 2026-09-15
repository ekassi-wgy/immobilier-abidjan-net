<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Agencies;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Support\Validator;
use Throwable;

/**
 * Comptes du back-office : validation commune, création avec invitation, pays de travail.
 * Utilisé par les écrans agences, comptes d'agence et utilisateurs internes.
 */
trait AccountSupport
{
    /** Pays du site courant : toutes les agences et leurs comptes y sont rattachés. */
    private function countryId(): int
    {
        return site()?->country->id ?? throw new HttpException(403);
    }

    /**
     * Champs d'identité d'un compte.
     *
     * @param array<string, mixed>|null $account
     * @return array{0: Validator, 1: array<string, mixed>}
     */
    private function validateIdentity(Request $request, ?array $account): array
    {
        $input = $request->all();
        $input['email'] = mb_strtolower(trim((string) ($input['email'] ?? '')));

        $v = new Validator($input);
        $v->required('first_name', 'last_name', 'email')
            ->maxLength('first_name', 80)->maxLength('last_name', 80)
            ->maxLength('email', 190)->email('email')
            ->maxLength('phone', 30)->phone('phone')
            ->maxLength('whatsapp', 30)->phone('whatsapp')
            ->maxLength('job_title', 100);
        if (!$v->has('email') && $this->app->users()->emailExists($v->string('email'), $account !== null ? (int) $account['id'] : null)) {
            $v->add('email', __('users.email_taken'));
        }

        return [$v, [
            'first_name' => $v->string('first_name'),
            'last_name' => $v->string('last_name'),
            'email' => $v->string('email'),
            'phone' => $v->nullableString('phone'),
            'whatsapp' => $v->nullableString('whatsapp'),
            'job_title' => $v->nullableString('job_title'),
        ]];
    }

    /**
     * Crée le compte puis envoie l'invitation (message flash selon le résultat).
     *
     * @param array<string, mixed> $data
     */
    private function createAccount(Request $request, array $data, ?string $agencyName = null): int
    {
        $users = $this->app->users();
        $creator = $this->user($request);
        $id = $users->create($data + ['created_by_user_id' => $creator->id], $this->app->hasher());
        $this->log($request, 'user.created', 'user', $id, $data['email'] . ' (' . $data['role'] . ')', ['after' => $data], $data['country_id'] ?? null);
        $this->sendInvitation($request, $id, $agencyName);

        return $id;
    }

    private function sendInvitation(Request $request, int $userId, ?string $agencyName = null): bool
    {
        $account = $this->app->users()->findById($userId);
        $site = site();
        if ($account === null || $site === null) {
            return false;
        }

        try {
            $this->app->passwordReset()->invite($request, $site, $account, $this->user($request)->fullName(), $agencyName ?? $account->agencyName);
            $this->flash('success', __('users.flash.invited', ['email' => $account->email]));

            return true;
        } catch (Throwable $exception) {
            $this->app->logger()->exception($exception, ['user_id' => $userId]);
            $this->flash('error', __('users.flash.invite_failed', ['email' => $account->email]));

            return false;
        }
    }
}
