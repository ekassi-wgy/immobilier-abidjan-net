<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin;

use App\Core\Request;
use App\Core\Response;

/**
 * Mot de passe oublié (lien par email), réinitialisation et changement obligatoire du mot de passe provisoire.
 */
final class PasswordController extends Controller
{
    public function showForgot(Request $request): Response
    {
        return $this->renderAuth('auth/forgot-password', [], __('auth.forgot.title'));
    }

    public function sendResetLink(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190) {
            return $this->renderAuth('auth/forgot-password', ['email' => mb_substr($email, 0, 190), 'errorMessage' => __('auth.forgot.invalid_email')], __('auth.forgot.title'), 422);
        }

        $limit = (int) config('auth.reset_ip_limit', 5);
        if (!$this->app->rateLimiter()->attempt('password-reset|' . $request->ip(), $limit, 900)) {
            return $this->renderAuth('auth/forgot-password', ['email' => $email, 'errorMessage' => __('auth.forgot.too_many')], __('auth.forgot.title'), 429);
        }

        $site = $this->app->site();
        if ($site !== null) {
            $this->app->passwordReset()->request($request, $site, $email);
        }

        // Même réponse que l'adresse existe ou non
        $this->flash('success', __('auth.forgot.sent', ['minutes' => (int) config('auth.reset_expires', 60)]));

        return $this->redirectToRoute('cmsadmin.password.forgot', status: 303);
    }

    public function showReset(Request $request, string $token): Response
    {
        $user = $this->app->passwordReset()->findUser($token);

        return $this->withoutReferrer($this->renderAuth('auth/reset-password', [
            'user' => $user,
            'token' => $token,
            'minLength' => $this->app->hasher()->minLength(),
        ], $user !== null ? __('auth.reset.title') : __('auth.reset.invalid_title'), $user !== null ? 200 : 410));
    }

    public function reset(Request $request, string $token): Response
    {
        $reset = $this->app->passwordReset();
        $user = $reset->findUser($token);
        if ($user === null) {
            return $this->showReset($request, $token);
        }

        $password = (string) $request->input('password', '');
        $errors = $this->validationErrors($password, (string) $request->input('password_confirmation', ''), $user->email);
        if ($errors !== []) {
            return $this->withoutReferrer($this->renderAuth('auth/reset-password', [
                'user' => $user,
                'token' => $token,
                'errors' => $errors,
                'minLength' => $this->app->hasher()->minLength(),
            ], __('auth.reset.title'), 422));
        }

        $reset->reset($request, $token, $password);
        $this->flash('success', __('auth.reset.done'));

        return $this->redirectToRoute('cmsadmin.login', status: 303);
    }

    public function showChange(Request $request): Response
    {
        $user = $this->user($request);
        if (!$user->mustChangePassword) {
            return Response::redirect(route('cmsadmin.account') . '#mot-de-passe');
        }

        return $this->renderAuth('auth/change-password', ['user' => $user, 'minLength' => $this->app->hasher()->minLength()], __('auth.change.title'));
    }

    public function change(Request $request): Response
    {
        $user = $this->user($request);
        $errors = $this->app->auth()->changePassword(
            $request,
            $user,
            (string) $request->input('current_password', ''),
            (string) $request->input('password', ''),
            (string) $request->input('password_confirmation', '')
        );

        if ($errors !== []) {
            return $this->renderAuth('auth/change-password', [
                'user' => $user,
                'errors' => $errors,
                'minLength' => $this->app->hasher()->minLength(),
            ], __('auth.change.title'), 422);
        }

        $this->flash('success', __('auth.change.done'));

        return Response::redirect(url($this->app->auth()->pullIntendedUrl('/cmsadmin')), 303);
    }

    /** @return array<string, string> */
    private function validationErrors(string $password, string $confirmation, string $email): array
    {
        $hasher = $this->app->hasher();
        $errors = [];
        foreach ($hasher->validate($password, $confirmation, $email) as $key) {
            $field = $key === 'auth.password.mismatch' ? 'password_confirmation' : 'password';
            $errors[$field] ??= __($key, ['min' => $hasher->minLength()]);
        }

        return $errors;
    }

    /** Page contenant un jeton dans l'URL : l'adresse n'est jamais transmise aux sites liés. */
    private function withoutReferrer(Response $response): Response
    {
        return $response->setHeader('Referrer-Policy', 'no-referrer');
    }
}
