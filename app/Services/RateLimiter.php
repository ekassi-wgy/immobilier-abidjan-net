<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;

/**
 * Limiteur simple à fenêtre fixe, stocké dans le cache fichier (formulaires sensibles : mot de passe oublié…).
 * Approximation acceptable pour des volumes faibles ; la limitation des connexions utilise la base (LoginThrottle).
 */
final class RateLimiter
{
    public function __construct(private readonly Cache $cache)
    {
    }

    /** Enregistre une tentative ; retourne false si la limite est dépassée sur la fenêtre. */
    public function attempt(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $cacheKey = 'ratelimit_' . hash('sha256', $key);
        $entry = $this->cache->get($cacheKey);
        $now = time();

        if (!is_array($entry) || ($entry['reset_at'] ?? 0) <= $now) {
            $entry = ['hits' => 0, 'reset_at' => $now + $windowSeconds];
        }

        $entry['hits']++;
        $this->cache->set($cacheKey, $entry, max(1, $entry['reset_at'] - $now));

        return $entry['hits'] <= $maxAttempts;
    }

    public function clear(string $key): void
    {
        $this->cache->forget('ratelimit_' . hash('sha256', $key));
    }
}
