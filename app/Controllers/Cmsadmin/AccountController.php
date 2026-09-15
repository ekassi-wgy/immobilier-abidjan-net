<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin;

use App\Core\Request;
use App\Core\Response;

/**
 * Mon compte : informations de connexion et changement de mot de passe.
 */
final class AccountController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->screen($request);
    }

    public function updatePassword(Request $request): Response
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
            return $this->screen($request, $errors, 422);
        }

        $this->flash('success', __('auth.change.done'));

        return $this->redirectToRoute('cmsadmin.account', status: 303);
    }

    /** @param array<string, string> $errors */
    private function screen(Request $request, array $errors = [], int $status = 200): Response
    {
        return $this->render($request, 'account/index', [
            'account' => $this->user($request),
            'errors' => $errors,
            'minLength' => $this->app->hasher()->minLength(),
        ], [
            'title' => __('auth.account.title'),
            'activeMenu' => 'account',
        ], $status);
    }
}
