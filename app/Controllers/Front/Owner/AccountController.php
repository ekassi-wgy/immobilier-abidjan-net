<?php

declare(strict_types=1);

namespace App\Controllers\Front\Owner;

use App\Controllers\Front\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\Auth;
use App\Support\Validator;

/**
 * Compte d'un particulier qui confie un bien à Weblogy : inscription, connexion, mot de passe oublié,
 * confirmation de l'adresse email.
 *
 * Le compte ne donne aucun droit de publication : il permet seulement de transmettre un bien à Weblogy
 * et d'en suivre le traitement. Garde « owner » d'Auth, session distincte du back-office.
 */
final class AccountController extends Controller
{
    public function showLogin(Request $request): Response
    {
        if ($this->app->ownerAuth()->check($request)) {
            return $this->redirect('mon-espace');
        }

        return $this->screen('front/pages/owner/login', [], [], 200, __('owner.login.title'));
    }

    public function login(Request $request): Response
    {
        $site = $this->site();
        $auth = $this->app->ownerAuth();
        $email = trim((string) $request->input('email', ''));

        $result = $auth->attempt($request, $site, $email, (string) $request->input('password', ''), false);
        if ($result === Auth::OK) {
            return Response::redirect(url(ltrim($auth->pullIntendedUrl('/mon-espace'), '/')), 303);
        }

        $message = match ($result) {
            Auth::LOCKED => __('auth.login.locked', ['minutes' => (int) settings('security.login_lockout_minutes', 15)]),
            Auth::WRONG_SITE => __('owner.login.wrong_site'),
            default => __('owner.login.invalid'),
        };

        return $this->screen('front/pages/owner/login', ['message' => $message], ['email' => $email], $result === Auth::LOCKED ? 429 : 422, __('owner.login.title'));
    }

    public function logout(Request $request): Response
    {
        $this->app->ownerAuth()->logout($request);

        return $this->redirect('', 303);
    }

    public function showRegister(Request $request): Response
    {
        if ($this->app->ownerAuth()->check($request)) {
            return $this->redirect('mon-espace');
        }

        return $this->screen('front/pages/owner/register', [], [], 200, __('owner.register.title'));
    }

    public function register(Request $request): Response
    {
        $site = $this->site();
        $input = $request->all();
        unset($input['password'], $input['password_confirmation']);

        $v = new Validator($request->all());
        $v->required('first_name', 'last_name', 'email', 'phone', 'password')
            ->maxLength('first_name', 80)->maxLength('last_name', 80)
            ->maxLength('email', 190)->email('email')
            ->maxLength('phone', 30)->phone('phone');

        $hasher = $this->app->hasher();
        foreach ($hasher->validate((string) $request->input('password', ''), (string) $request->input('password_confirmation', ''), $v->string('email')) as $key) {
            $field = $key === 'auth.password.mismatch' ? 'password_confirmation' : 'password';
            if (!$v->has($field)) {
                $v->add($field, __($key, ['min' => $hasher->minLength()]));
            }
        }
        // Le message « adresse déjà utilisée » révèle qu'un compte existe : cette vérification a son
        // propre quota par adresse IP, pour qu'un robot ne puisse pas tester une liste d'emails.
        if (!$v->has('email') && !$this->app->rateLimiter()->attempt('public-form:owner-register-email:' . $request->ip(), 20, 3600)) {
            return $this->screen('front/pages/owner/register', ['message' => __('front.contact.too_many')], $input, 429, __('owner.register.title'));
        }
        if (!$v->has('email') && $this->app->users()->emailExists($v->string('email'))) {
            $v->add('email', __('owner.register.email_taken'));
        }
        $v->rule('consent', !empty($input['consent']), __('owner.register.consent_required'));

        if ($v->fails()) {
            return $this->screen('front/pages/owner/register', $v->errors(), $input, 422, __('owner.register.title'));
        }
        if (!$this->withinQuota($request, 'owner-register')) {
            return $this->screen('front/pages/owner/register', ['message' => __('front.contact.too_many')], $input, 429, __('owner.register.title'));
        }
        // Robot : même parcours apparent, aucun compte créé.
        if ($this->isTrapped($request)) {
            return $this->redirect('mon-espace/connexion', 303);
        }

        $userId = $this->app->db()->insert('users', [
            'role' => User::OWNER,
            'country_id' => $site->country->id,
            'first_name' => $v->string('first_name'),
            'last_name' => $v->string('last_name'),
            'email' => mb_strtolower($v->string('email')),
            'phone' => $v->string('phone'),
            'password_hash' => $hasher->hash((string) $request->input('password')),
            'must_change_password' => 0,
            'is_active' => 1,
            'password_changed_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $user = $this->app->users()->findById($userId);
        if ($user === null) {
            return $this->redirect('mon-espace/connexion', 303);
        }

        $this->app->activity()->log('owner.registered', $user->id, $site->country->id, 'user', $user->id, $user->email, request: $request);
        $this->app->ownerAuth()->login($request, $user);
        $sent = $this->app->ownerMessages()->sendVerification($user, $site);
        $this->flash($sent ? 'success' : 'info', __($sent ? 'owner.register.done' : 'owner.verify.send_failed', ['email' => $user->email]));

        return $this->redirect('mon-espace', 303);
    }

    public function confirmEmail(Request $request, string $token): Response
    {
        $userId = $this->app->ownerMessages()->confirm($token);
        if ($userId === null) {
            $this->flash('info', __('owner.verify.invalid'));

            return $this->redirect('mon-espace', 303);
        }

        $this->app->activity()->log('owner.email_verified', $userId, $this->site()->country->id, 'user', $userId, request: $request);
        $this->flash('success', __('owner.verify.done'));

        return $this->redirect('mon-espace', 303);
    }

    public function resendVerification(Request $request): Response
    {
        $user = $this->owner($request);
        if ($user->hasVerifiedEmail()) {
            return $this->redirect('mon-espace', 303);
        }
        $sent = $this->app->ownerMessages()->sendVerification($user, $this->site());
        $this->flash($sent ? 'success' : 'info', __($sent ? 'owner.verify.resent' : 'owner.verify.wait', ['email' => $user->email]));

        return $this->redirect('mon-espace', 303);
    }

    public function showForgot(Request $request): Response
    {
        return $this->screen('front/pages/owner/forgot', [], [], 200, __('owner.forgot.title'));
    }

    public function forgot(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        // Même réponse que l'adresse existe ou non : aucune énumération des comptes.
        if (!$this->isTrapped($request) && $this->withinQuota($request, 'owner-forgot') && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $this->app->passwordReset()->request($request, $this->site(), $email);
        }
        $this->flash('success', __('owner.forgot.sent', ['minutes' => (int) config('auth.reset_expires', 60)]));

        return $this->redirect('mon-espace/mot-de-passe-oublie', 303);
    }

    public function showReset(Request $request, string $token): Response
    {
        $user = $this->app->passwordReset()->findUser($token, true);

        return $this->screen('front/pages/owner/reset', [], ['token' => $token, 'valid' => $user !== null], $user !== null ? 200 : 410, __('owner.reset.title'));
    }

    public function reset(Request $request, string $token): Response
    {
        $reset = $this->app->passwordReset();
        $user = $reset->findUser($token, true);
        if ($user === null) {
            return $this->showReset($request, $token);
        }

        $hasher = $this->app->hasher();
        $errors = [];
        foreach ($hasher->validate((string) $request->input('password', ''), (string) $request->input('password_confirmation', ''), $user->email) as $key) {
            $field = $key === 'auth.password.mismatch' ? 'password_confirmation' : 'password';
            $errors[$field] ??= __($key, ['min' => $hasher->minLength()]);
        }
        if ($errors !== []) {
            return $this->screen('front/pages/owner/reset', $errors, ['token' => $token, 'valid' => true], 422, __('owner.reset.title'));
        }

        $reset->reset($request, $token, (string) $request->input('password'), true);
        $this->flash('success', __('owner.reset.done'));

        return $this->redirect('mon-espace/connexion', 303);
    }

    /** Particulier connecté (posé par AuthenticateOwner). */
    private function owner(Request $request): User
    {
        $user = $request->attribute('owner');

        return $user instanceof User ? $user : throw new \LogicException('Route sans AuthenticateOwner.');
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>  $old
     */
    private function screen(string $view, array $errors, array $old, int $status, string $title): Response
    {
        return $this->page('front/layouts/app', $view, [
            'errors' => $errors,
            'old' => $old,
            'flash' => $this->app->session()->flashes(),
            'csrfToken' => $this->app->csrf()->token(),
        ], [
            'title' => $title,
            'description' => __('owner.meta_description', ['site' => $this->site()->name]),
            'noindex' => true,
        ], $status);
    }
}
