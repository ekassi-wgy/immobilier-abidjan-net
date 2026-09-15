<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session PHP durcie, démarrée seulement quand on en a besoin (jeton CSRF, flash, connexion) :
 * les pages publiques consultées sans formulaire n'ouvrent pas de session et restent cachables.
 */
final class Session
{
    private const FLASH_KEY = '_flash';

    private bool $flashAged = false;

    /** @param array{name: string, lifetime: int, secure: mixed} $config */
    public function __construct(private readonly array $config, private readonly bool $secureRequest)
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = $this->config['secure'];
        session_name($this->config['name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure === 'auto' || $secure === null ? $this->secureRequest : (bool) $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start([
            'use_strict_mode' => 1,
            'use_only_cookies' => 1,
            'use_trans_sid' => 0,
            'gc_maxlifetime' => $this->config['lifetime'] * 60,
        ]);

        $this->ageFlash();
    }

    public function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /** Démarre la session seulement si le navigateur en possède déjà une. */
    public function resumeIfExists(?string $cookie): void
    {
        if ($cookie !== null && $cookie !== '') {
            $this->start();
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $_SESSION);
    }

    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    /** Nouvel identifiant de session (à la connexion, contre la fixation de session). */
    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    /** Vide et détruit la session (déconnexion). */
    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
        session_destroy();
    }

    /** Message affiché à la requête suivante. Types : success, error, info. */
    public function flash(string $type, string $message): void
    {
        $this->start();
        $_SESSION[self::FLASH_KEY]['new'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Messages flash à afficher dans la requête courante.
     *
     * @return list<array{type: string, message: string}>
     */
    public function flashes(): array
    {
        if (!$this->isStarted()) {
            return [];
        }

        return $_SESSION[self::FLASH_KEY]['current'] ?? [];
    }

    /** Les messages posés à la requête précédente deviennent courants ; ceux déjà affichés disparaissent. */
    private function ageFlash(): void
    {
        if ($this->flashAged) {
            return;
        }
        $this->flashAged = true;

        $_SESSION[self::FLASH_KEY] = ['current' => $_SESSION[self::FLASH_KEY]['new'] ?? [], 'new' => []];
    }
}
