<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;

/**
 * Photos envoyées en arrière-plan depuis le formulaire d'annonce, avant l'enregistrement de l'annonce.
 *
 * Chaque photo est ré-encodée tout de suite dans uploads/{pays}/tmp/, puis identifiée par un jeton aléatoire
 * mémorisé dans la session de l'utilisateur : seul celui qui a envoyé une photo peut la rattacher à une annonce.
 * Les fichiers temporaires jamais rattachés sont supprimés par bin/cleanup-uploads.php.
 */
final class PendingUploads
{
    private const SESSION_KEY = 'cmsadmin.pending_uploads';
    private const MAX_PENDING = 120;

    public function __construct(private readonly Session $session)
    {
    }

    /** @param array{path: string, width: int, height: int, size: int, original_name: string} $image */
    public function add(array $image): string
    {
        $pending = (array) $this->session->get(self::SESSION_KEY, []);
        $token = bin2hex(random_bytes(16));
        $pending[$token] = $image + ['created_at' => time()];
        // Session bornée : les plus anciens envois sont oubliés (leurs fichiers seront nettoyés)
        if (count($pending) > self::MAX_PENDING) {
            $pending = array_slice($pending, -self::MAX_PENDING, null, true);
        }
        $this->session->set(self::SESSION_KEY, $pending);

        return $token;
    }

    /** @return array{path: string, width: int, height: int, size: int, original_name: string}|null */
    public function get(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return null;
        }
        $pending = (array) $this->session->get(self::SESSION_KEY, []);

        return isset($pending[$token]) && is_array($pending[$token]) ? $pending[$token] : null;
    }

    public function forget(string $token): void
    {
        $pending = (array) $this->session->get(self::SESSION_KEY, []);
        unset($pending[$token]);
        $this->session->set(self::SESSION_KEY, $pending);
    }
}
