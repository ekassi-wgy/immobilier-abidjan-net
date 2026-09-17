<?php

declare(strict_types=1);

namespace App\Controllers\Front\Owner;

use App\Controllers\Front\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Support\Validator;

/**
 * Espace propriétaire : suivi des biens confiés à Weblogy et coordonnées du compte.
 * Toutes les routes passent par AuthenticateOwner.
 */
final class SpaceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->owner($request);

        return $this->screen($request, 'front/pages/owner/dashboard', [
            'user' => $user,
            'submissions' => $this->app->submissions()->forOwner($user->id, $this->site()->country->id),
        ], __('owner.dashboard.title'));
    }

    public function profile(Request $request): Response
    {
        $user = $this->owner($request);

        return $this->screen($request, 'front/pages/owner/profile', [
            'user' => $user,
            'old' => ['first_name' => $user->firstName, 'last_name' => $user->lastName, 'phone' => $user->phone],
        ], __('owner.profile.title'));
    }

    public function updateProfile(Request $request): Response
    {
        $user = $this->owner($request);
        $v = new Validator($request->all());
        $v->required('first_name', 'last_name', 'phone')
            ->maxLength('first_name', 80)->maxLength('last_name', 80)
            ->maxLength('phone', 30)->phone('phone');
        if ($v->fails()) {
            return $this->screen($request, 'front/pages/owner/profile', ['user' => $user, 'errors' => $v->errors(), 'old' => $request->all()], __('owner.profile.title'), 422);
        }

        $data = ['first_name' => $v->string('first_name'), 'last_name' => $v->string('last_name'), 'phone' => $v->string('phone')];
        $this->app->users()->update($user->id, $data);
        $this->app->activity()->log('owner.profile_updated', $user->id, $user->countryId, 'user', $user->id, request: $request);
        $this->flash('success', __('owner.profile.saved'));

        return $this->redirect('mon-espace/profil', 303);
    }

    public function updatePassword(Request $request): Response
    {
        $user = $this->owner($request);
        $errors = $this->app->ownerAuth()->changePassword(
            $request,
            $user,
            (string) $request->input('current_password', ''),
            (string) $request->input('password', ''),
            (string) $request->input('password_confirmation', '')
        );
        if ($errors !== []) {
            return $this->screen($request, 'front/pages/owner/profile', [
                'user' => $user,
                'passwordErrors' => $errors,
                'old' => ['first_name' => $user->firstName, 'last_name' => $user->lastName, 'phone' => $user->phone],
            ], __('owner.profile.title'), 422);
        }
        $this->flash('success', __('owner.profile.password_saved'));

        return $this->redirect('mon-espace/profil', 303);
    }

    private function owner(Request $request): User
    {
        $user = $request->attribute('owner');

        return $user instanceof User ? $user : throw new \LogicException('Route sans AuthenticateOwner.');
    }

    /** @param array<string, mixed> $data */
    private function screen(Request $request, string $view, array $data, string $title, int $status = 200): Response
    {
        return $this->page('front/layouts/app', $view, $data + [
            'errors' => [],
            'passwordErrors' => [],
            'old' => [],
            'flash' => $this->app->session()->flashes(),
            'csrfToken' => $this->app->csrf()->token(),
        ], ['title' => $title, 'description' => __('owner.meta_description', ['site' => $this->site()->name]), 'noindex' => true], $status);
    }
}
