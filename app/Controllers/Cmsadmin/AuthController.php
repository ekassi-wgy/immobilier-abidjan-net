<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin;

use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

/**
 * Connexion et déconnexion du back-office.
 */
final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        $flash = $this->app->session()->flashes();
        if ($this->app->auth()->pullSessionExpired()) {
            array_unshift($flash, ['type' => 'info', 'message' => __('auth.login.expired')]);
        }

        return $this->renderAuth('auth/login', ['flash' => $flash], __('auth.login.title'));
    }

    public function login(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $site = $this->app->site();

        if ($email === '' || $password === '' || $site === null) {
            return $this->loginError($email, __('auth.login.required'), 422);
        }

        $result = $this->app->auth()->attempt($request, $site, $email, $password, $request->input('remember') === '1');

        return match ($result) {
            Auth::OK => Response::redirect(url($this->app->auth()->pullIntendedUrl('/cmsadmin')), 303),
            Auth::LOCKED => $this->loginError($email, __('auth.login.locked', ['minutes' => (int) settings('security.login_lockout_minutes', 15)]), 429, locked: true),
            Auth::WRONG_SITE => $this->loginError($email, __('auth.login.wrong_site'), 403),
            default => $this->loginError($email, __('auth.login.invalid'), 422),
        };
    }

    public function logout(Request $request): Response
    {
        $this->app->auth()->logout($request);
        $this->flash('success', __('auth.login.logged_out'));

        return $this->redirectToRoute('cmsadmin.login', status: 303);
    }

    private function loginError(string $email, string $message, int $status, bool $locked = false): Response
    {
        return $this->renderAuth('auth/login', [
            'email' => mb_substr($email, 0, 190),
            'errorMessage' => $message,
            'locked' => $locked,
        ], __('auth.login.title'), $status);
    }
}
