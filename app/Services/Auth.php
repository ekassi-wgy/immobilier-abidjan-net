<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Models\Site;
use App\Models\User;

/**
 * Authentification du back-office : connexion, session, « Rester connecté », déconnexion.
 *
 * Sécurité :
 * - message d'échec générique, temps de réponse identique que le compte existe ou non ;
 * - blocage temporaire après N échecs (LoginThrottle) ;
 * - nouvel identifiant de session et nouveau jeton CSRF à la connexion ;
 * - session invalidée si le mot de passe change ailleurs (empreinte du hachage) ou après inactivité ;
 * - « Rester connecté » : jeton sélecteur/validateur (seul le SHA-256 du validateur est stocké), renouvelé à chaque usage.
 */
final class Auth
{
    public const OK = 'ok';
    public const INVALID = 'invalid';
    public const LOCKED = 'locked';
    public const WRONG_SITE = 'wrong_site';

    private const SESSION_USER = '_auth.user_id';
    private const SESSION_FINGERPRINT = '_auth.fingerprint';
    private const SESSION_ACTIVITY = '_auth.last_activity';
    private const SESSION_INTENDED = '_auth.intended';
    private const SESSION_EXPIRED = '_auth.expired';

    private ?User $user = null;
    private bool $resolved = false;

    /** @param array{idle_timeout: int, remember_days: int, remember_cookie: string} $config */
    public function __construct(
        private readonly Database $db,
        private readonly Session $session,
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly LoginThrottle $throttle,
        private readonly ActivityLogger $activity,
        private readonly array $config,
    ) {
    }

    /** Tentative de connexion. Retourne Auth::OK, INVALID, LOCKED ou WRONG_SITE. */
    public function attempt(Request $request, Site $site, string $email, string $password, bool $remember): string
    {
        $email = UserRepository::normalizeEmail($email);
        if ($this->throttle->isLocked($email, $request->ip())) {
            return self::LOCKED;
        }

        $user = $email !== '' ? $this->users->findByEmail($email) : null;
        if ($user === null) {
            // Adresse inconnue : calcul de coût équivalent, pour ne pas révéler par le temps de réponse qu'elle n'existe pas
            $this->hasher->hash($password);
        }
        $valid = $user !== null && $this->hasher->verify($password, $user->passwordHash) && $user->canLogin();

        $this->throttle->record($email, $request->ip(), $valid);
        if (!$valid) {
            return $this->throttle->isLocked($email, $request->ip()) ? self::LOCKED : self::INVALID;
        }

        if (!$user->canAccessCountry($site->country->id)) {
            $this->activity->log('user.login_refused', $user->id, $site->country->id, 'user', $user->id, 'Compte d’un autre pays', request: $request);

            return self::WRONG_SITE;
        }

        if ($this->hasher->needsRehash($user->passwordHash)) {
            $this->users->rehashPassword($user->id, $this->hasher->hash($password));
            $user = $this->users->findById($user->id) ?? $user;
        }

        $this->login($request, $user, $remember);
        $this->activity->log('user.login', $user->id, $site->country->id, 'user', $user->id, request: $request);

        return self::OK;
    }

    /** Ouvre la session authentifiée (après vérification du mot de passe ou d'un jeton « Rester connecté »). */
    public function login(Request $request, User $user, bool $remember = false): void
    {
        $this->session->regenerate();
        $this->session->forget('_csrf_token');
        $this->session->forget(self::SESSION_EXPIRED);
        $this->session->set(self::SESSION_USER, $user->id);
        $this->session->set(self::SESSION_FINGERPRINT, $this->fingerprint($user));
        $this->session->set(self::SESSION_ACTIVITY, time());

        $this->users->recordLogin($user->id, $request->ip());
        if ($remember) {
            $this->issueRememberToken($request, $user);
        }

        $this->user = $user;
        $this->resolved = true;
    }

    /** Utilisateur connecté (session valide ou jeton « Rester connecté »), null sinon. */
    public function user(Request $request): ?User
    {
        if ($this->resolved) {
            return $this->user;
        }
        $this->resolved = true;

        $userId = $this->session->isStarted() ? $this->session->get(self::SESSION_USER) : null;

        if (is_int($userId)) {
            $user = $this->users->findById($userId);
            $idle = time() - (int) $this->session->get(self::SESSION_ACTIVITY, 0) > $this->config['idle_timeout'] * 60;

            if ($user !== null && $user->canLogin() && !$idle
                && hash_equals((string) $this->session->get(self::SESSION_FINGERPRINT, ''), $this->fingerprint($user))) {
                $this->session->set(self::SESSION_ACTIVITY, time());

                return $this->user = $user;
            }

            $this->clearSession();
            if ($idle) {
                $this->session->set(self::SESSION_EXPIRED, true);
            }
        }

        $user = $this->userFromRememberCookie($request);
        if ($user !== null) {
            $this->login($request, $user);
            $this->activity->log('user.login_remembered', $user->id, $user->countryId, 'user', $user->id, request: $request);
        }

        return $this->user = $user;
    }

    public function check(Request $request): bool
    {
        return $this->user($request) !== null;
    }

    public function logout(Request $request): void
    {
        $user = $this->user($request);
        $this->forgetRememberCookie($request, deleteToken: true);
        $this->session->destroy();
        $this->session->start();
        $this->session->regenerate();

        if ($user !== null) {
            $this->activity->log('user.logout', $user->id, $user->countryId, 'user', $user->id, request: $request);
        }

        $this->user = null;
        $this->resolved = true;
    }

    /** La session vient-elle d'expirer pour inactivité ? (message sur l'écran de connexion, lu une seule fois) */
    public function pullSessionExpired(): bool
    {
        if (!$this->session->isStarted() || !$this->session->get(self::SESSION_EXPIRED)) {
            return false;
        }
        $this->session->forget(self::SESSION_EXPIRED);

        return true;
    }

    /** Mémorise la page demandée avant la redirection vers la connexion. */
    public function setIntendedUrl(string $path): void
    {
        $this->session->set(self::SESSION_INTENDED, $path);
    }

    /** Page à ouvrir après connexion : uniquement un chemin interne au back-office (pas de redirection ouverte). */
    public function pullIntendedUrl(string $default): string
    {
        $path = $this->session->get(self::SESSION_INTENDED);
        $this->session->forget(self::SESSION_INTENDED);

        return is_string($path) && preg_match('#^/cmsadmin(/[A-Za-z0-9/_\-.?=&%]*)?$#', $path) === 1 && !str_contains($path, '//')
            ? $path
            : $default;
    }

    /**
     * Après un changement de mot de passe : session courante mise à jour, autres sessions et jetons « Rester connecté » révoqués.
     */
    public function refreshAfterPasswordChange(Request $request, int $userId): void
    {
        $this->db->execute('DELETE FROM remember_tokens WHERE user_id = :id', ['id' => $userId]);
        $this->forgetRememberCookie($request, deleteToken: false);

        $user = $this->users->findById($userId);
        if ($user !== null) {
            $this->session->regenerate();
            $this->session->set(self::SESSION_FINGERPRINT, $this->fingerprint($user));
            $this->user = $user;
        }
    }

    /**
     * Changement de mot de passe par l'utilisateur connecté (provisoire ou volontaire).
     *
     * @return array<string, string> Erreurs [champ => message], vide si le mot de passe a été changé
     */
    public function changePassword(Request $request, User $user, string $current, string $password, string $confirmation): array
    {
        if (!$this->hasher->verify($current, $user->passwordHash)) {
            return ['current_password' => __('auth.change.current_invalid')];
        }

        $errors = [];
        foreach ($this->hasher->validate($password, $confirmation, $user->email) as $key) {
            $field = $key === 'auth.password.mismatch' ? 'password_confirmation' : 'password';
            $errors[$field] ??= __($key, ['min' => $this->hasher->minLength()]);
        }
        if ($errors === [] && hash_equals($current, $password)) {
            $errors['password'] = __('auth.change.same_as_current');
        }
        if ($errors !== []) {
            return $errors;
        }

        $this->users->updatePassword($user->id, $this->hasher->hash($password));
        $this->refreshAfterPasswordChange($request, $user->id);
        $this->activity->log('user.password_changed', $user->id, $user->countryId, 'user', $user->id, request: $request);

        return [];
    }

    private function clearSession(): void
    {
        foreach ([self::SESSION_USER, self::SESSION_FINGERPRINT, self::SESSION_ACTIVITY] as $key) {
            $this->session->forget($key);
        }
    }

    /** Empreinte du hachage : un changement de mot de passe invalide les sessions ouvertes ailleurs. */
    private function fingerprint(User $user): string
    {
        return hash('sha256', 'session|' . $user->id . '|' . $user->passwordHash);
    }

    private function issueRememberToken(Request $request, User $user): void
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires = time() + $this->config['remember_days'] * 86400;

        $this->db->execute(
            'INSERT INTO remember_tokens (user_id, selector, validator_hash, user_agent, expires_at) VALUES (:user, :selector, :hash, :agent, :expires)',
            [
                'user' => $user->id,
                'selector' => $selector,
                'hash' => hash('sha256', $validator),
                'agent' => mb_substr($request->userAgent(), 0, 255),
                'expires' => gmdate('Y-m-d H:i:s', $expires),
            ]
        );
        $this->setRememberCookie($request, $selector . ':' . $validator, $expires);
    }

    private function userFromRememberCookie(Request $request): ?User
    {
        $cookie = $request->cookie($this->config['remember_cookie']);
        if ($cookie === null || preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $cookie, $matches) !== 1) {
            return null;
        }
        [, $selector, $validator] = $matches;

        $token = $this->db->selectOne(
            'SELECT id, user_id, validator_hash FROM remember_tokens WHERE selector = :selector AND expires_at > UTC_TIMESTAMP()',
            ['selector' => $selector]
        );
        if ($token === null) {
            $this->forgetRememberCookie($request, deleteToken: false);

            return null;
        }

        if (!hash_equals((string) $token['validator_hash'], hash('sha256', $validator))) {
            // Sélecteur valide mais validateur faux : jeton probablement volé → tous les jetons du compte sont révoqués
            $this->db->execute('DELETE FROM remember_tokens WHERE user_id = :id', ['id' => $token['user_id']]);
            $this->forgetRememberCookie($request, deleteToken: false);

            return null;
        }

        $user = $this->users->findById((int) $token['user_id']);
        if ($user === null || !$user->canLogin()) {
            $this->db->execute('DELETE FROM remember_tokens WHERE id = :id', ['id' => $token['id']]);
            $this->forgetRememberCookie($request, deleteToken: false);

            return null;
        }

        // Rotation du validateur à chaque usage
        $newValidator = bin2hex(random_bytes(32));
        $expires = time() + $this->config['remember_days'] * 86400;
        $this->db->execute(
            'UPDATE remember_tokens SET validator_hash = :hash, last_used_at = UTC_TIMESTAMP(), expires_at = :expires WHERE id = :id',
            ['hash' => hash('sha256', $newValidator), 'expires' => gmdate('Y-m-d H:i:s', $expires), 'id' => $token['id']]
        );
        $this->setRememberCookie($request, $selector . ':' . $newValidator, $expires);

        return $user;
    }

    private function forgetRememberCookie(Request $request, bool $deleteToken): void
    {
        $cookie = $request->cookie($this->config['remember_cookie']);
        if ($cookie === null) {
            return;
        }
        if ($deleteToken && preg_match('/^([a-f0-9]{24}):/', $cookie, $matches) === 1) {
            $this->db->execute('DELETE FROM remember_tokens WHERE selector = :selector', ['selector' => $matches[1]]);
        }
        $this->setRememberCookie($request, '', time() - 3600);
    }

    private function setRememberCookie(Request $request, string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie($this->config['remember_cookie'], $value, [
            'expires' => $expires,
            'path' => '/cmsadmin',
            'secure' => $request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
