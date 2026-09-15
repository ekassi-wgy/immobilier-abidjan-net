<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Jeton anti-CSRF par session (champ caché « _csrf » ou en-tête X-CSRF-Token).
 */
final class Csrf
{
    public const FIELD = '_csrf';
    private const SESSION_KEY = '_csrf_token';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function validate(?string $token): bool
    {
        $expected = $this->session->get(self::SESSION_KEY);

        return is_string($expected) && is_string($token) && $token !== '' && hash_equals($expected, $token);
    }

    /** Nouveau jeton (après connexion ou déconnexion). */
    public function refresh(): string
    {
        $this->session->forget(self::SESSION_KEY);

        return $this->token();
    }
}
