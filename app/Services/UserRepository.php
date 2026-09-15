<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\User;

/**
 * Accès aux comptes du back-office (table users). Les comptes supprimés (deleted_at) sont ignorés.
 */
final class UserRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findById(int $id): ?User
    {
        return $this->findOne('u.id = :id', ['id' => $id]);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOne('u.email = :email', ['email' => self::normalizeEmail($email)]);
    }

    public function updatePassword(int $userId, string $hash, bool $mustChange = false): void
    {
        $this->db->execute(
            'UPDATE users SET password_hash = :hash, must_change_password = :must, password_changed_at = UTC_TIMESTAMP() WHERE id = :id',
            ['hash' => $hash, 'must' => $mustChange, 'id' => $userId]
        );
    }

    /** Remplace le hachage sans toucher à la date de changement (ré-hachage transparent à la connexion). */
    public function rehashPassword(int $userId, string $hash): void
    {
        $this->db->execute('UPDATE users SET password_hash = :hash WHERE id = :id', ['hash' => $hash, 'id' => $userId]);
    }

    public function recordLogin(int $userId, string $ip): void
    {
        $this->db->execute(
            'UPDATE users SET last_login_at = UTC_TIMESTAMP(), last_login_ip = :ip WHERE id = :id',
            ['ip' => IpAddress::toBinary($ip), 'id' => $userId]
        );
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /** @param array<string, mixed> $params */
    private function findOne(string $condition, array $params): ?User
    {
        $row = $this->db->selectOne(
            'SELECT ' . User::COLUMNS . ' FROM users u LEFT JOIN agencies a ON a.id = u.agency_id
             WHERE ' . $condition . ' AND u.deleted_at IS NULL LIMIT 1',
            $params
        );

        return $row !== null ? User::fromRow($row) : null;
    }
}
