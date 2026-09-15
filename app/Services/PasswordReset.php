<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\View;
use App\Models\Site;
use App\Models\User;
use Throwable;

/**
 * « Mot de passe oublié » : lien à usage unique envoyé par email.
 *
 * - la réponse affichée est identique que l'adresse existe ou non (pas d'énumération des comptes) ;
 * - seul le SHA-256 du jeton est stocké ; validité limitée ; un nouveau lien annule les précédents ;
 * - 3 liens maximum par compte sur une heure ; limite par adresse IP (RateLimiter) ;
 * - après réinitialisation : autres liens supprimés, jetons « Rester connecté » révoqués.
 */
final class PasswordReset
{
    private const MAX_PER_HOUR = 3;

    public function __construct(
        private readonly Database $db,
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly Mailer $mailer,
        private readonly View $view,
        private readonly ActivityLogger $activity,
        private readonly Logger $logger,
        private readonly int $expiresMinutes,
    ) {
    }

    /** Envoie un lien si l'adresse correspond à un compte actif de ce pays. Ne révèle jamais le résultat. */
    public function request(Request $request, Site $site, string $email): void
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || !$user->canLogin() || !$user->canAccessCountry($site->country->id)) {
            return;
        }

        $recent = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM password_resets WHERE user_id = :id AND created_at >= UTC_TIMESTAMP() - INTERVAL 1 HOUR',
            ['id' => $user->id]
        );
        if ($recent >= self::MAX_PER_HOUR) {
            return;
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->db->transaction(function (Database $db) use ($user, $token): void {
            $db->execute('DELETE FROM password_resets WHERE user_id = :id AND used_at IS NULL', ['id' => $user->id]);
            $db->execute(
                'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:id, :hash, UTC_TIMESTAMP() + INTERVAL :minutes MINUTE)',
                ['id' => $user->id, 'hash' => hash('sha256', $token), 'minutes' => $this->expiresMinutes]
            );
        });

        $link = absolute_url(route('cmsadmin.password.reset', ['token' => $token]));
        $data = ['user' => $user, 'site' => $site, 'link' => $link, 'minutes' => $this->expiresMinutes, 'ip' => $request->ip()];

        try {
            $this->mailer->send(
                $user->email,
                __('auth.reset.email_subject', ['site' => $site->name]),
                $this->view->page('emails/layout', 'emails/password-reset', $data, ['site' => $site, 'preheader' => __('auth.reset.email_preheader')]),
                $this->view->render('emails/password-reset.text', $data),
                $user->fullName()
            );
            $this->activity->log('user.password_reset_requested', $user->id, $site->country->id, 'user', $user->id, request: $request);
        } catch (Throwable $exception) {
            $this->logger->exception($exception, ['user_id' => $user->id]);
        }
    }

    /** Compte associé à un jeton valide (non utilisé, non expiré), null sinon. */
    public function findUser(string $token): ?User
    {
        if (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) !== 1) {
            return null;
        }

        $userId = $this->db->scalar(
            'SELECT user_id FROM password_resets WHERE token_hash = :hash AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()',
            ['hash' => hash('sha256', $token)]
        );
        $user = $userId !== null ? $this->users->findById((int) $userId) : null;

        return $user !== null && $user->canLogin() ? $user : null;
    }

    /** Enregistre le nouveau mot de passe et consomme le jeton. Retourne l'utilisateur, ou null si le jeton n'est plus valide. */
    public function reset(Request $request, string $token, string $password): ?User
    {
        $user = $this->findUser($token);
        if ($user === null) {
            return null;
        }

        $this->db->transaction(function (Database $db) use ($user, $token, $password): void {
            $this->users->updatePassword($user->id, $this->hasher->hash($password));
            $db->execute('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE token_hash = :hash', ['hash' => hash('sha256', $token)]);
            $db->execute('DELETE FROM password_resets WHERE user_id = :id AND used_at IS NULL', ['id' => $user->id]);
            $db->execute('DELETE FROM remember_tokens WHERE user_id = :id', ['id' => $user->id]);
        });
        $this->activity->log('user.password_reset', $user->id, $user->countryId, 'user', $user->id, request: $request);

        return $user;
    }
}
