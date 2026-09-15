<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Limitation des tentatives de connexion (table login_attempts).
 *
 * - par adresse email : N échecs (settings security.login_max_attempts) depuis la dernière connexion réussie,
 *   sur la fenêtre de blocage (security.login_lockout_minutes) → compte bloqué temporairement ;
 * - par adresse IP : plafond global d'échecs sur la même fenêtre (essais sur de nombreuses adresses email).
 */
final class LoginThrottle
{
    public function __construct(
        private readonly Database $db,
        private readonly int $maxAttempts,
        private readonly int $lockoutMinutes,
        private readonly int $ipFailureLimit,
    ) {
    }

    public function isLocked(string $email, string $ip): bool
    {
        $email = UserRepository::normalizeEmail($email);
        $since = gmdate('Y-m-d H:i:s', time() - $this->lockoutMinutes * 60);

        $emailFailures = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = :email AND succeeded = 0 AND attempted_at >= :since
               AND attempted_at > COALESCE((SELECT MAX(attempted_at) FROM login_attempts WHERE email = :email2 AND succeeded = 1), \'1970-01-01\')',
            ['email' => $email, 'since' => $since, 'email2' => $email]
        );
        if ($emailFailures >= $this->maxAttempts) {
            return true;
        }

        $ipFailures = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND succeeded = 0 AND attempted_at >= :since',
            ['ip' => IpAddress::toBinary($ip), 'since' => $since]
        );

        return $ipFailures >= $this->ipFailureLimit;
    }

    public function record(string $email, string $ip, bool $succeeded): void
    {
        $this->db->execute(
            'INSERT INTO login_attempts (email, ip, succeeded, attempted_at) VALUES (:email, :ip, :succeeded, UTC_TIMESTAMP())',
            ['email' => mb_substr(UserRepository::normalizeEmail($email), 0, 190), 'ip' => IpAddress::toBinary($ip), 'succeeded' => $succeeded]
        );

        // Purge occasionnelle de l'historique (> 90 jours)
        if (random_int(1, 100) === 1) {
            $this->db->execute('DELETE FROM login_attempts WHERE attempted_at < UTC_TIMESTAMP() - INTERVAL 90 DAY');
        }
    }

    public function lockoutMinutes(): int
    {
        return $this->lockoutMinutes;
    }
}
