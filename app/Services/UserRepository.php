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

    /**
     * Comptes internes (Super Admin, Admin Pays), tous pays confondus.
     *
     * @param array{q?: string, role?: string, etat?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function staff(array $filters): array
    {
        $where = "u.role IN ('super_admin', 'country_admin') AND u.deleted_at IS NULL";
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (u.first_name LIKE :q OR u.last_name LIKE :q2 OR u.email LIKE :q3)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params += ['q' => $like, 'q2' => $like, 'q3' => $like];
        }
        if (in_array($filters['role'] ?? '', ['super_admin', 'country_admin'], true)) {
            $where .= ' AND u.role = :role';
            $params['role'] = $filters['role'];
        }
        if (($filters['etat'] ?? '') === 'actifs') {
            $where .= ' AND u.is_active = 1';
        } elseif (($filters['etat'] ?? '') === 'inactifs') {
            $where .= ' AND u.is_active = 0';
        }

        return $this->db->select(
            "SELECT u.id, u.role, u.country_id, u.first_name, u.last_name, u.email, u.phone, u.job_title, u.is_active,
                    u.must_change_password, u.last_login_at, u.created_at, c.name AS country_name
             FROM users u LEFT JOIN countries c ON c.id = u.country_id
             WHERE {$where} ORDER BY u.role = 'super_admin' DESC, u.last_name, u.first_name",
            $params
        );
    }

    /** @return list<array<string, mixed>> Comptes d'une agence */
    public function forAgency(int $agencyId): array
    {
        return $this->db->select(
            'SELECT id, role, first_name, last_name, email, phone, whatsapp, job_title, is_active, must_change_password, last_login_at, created_at
             FROM users WHERE agency_id = :agency AND deleted_at IS NULL ORDER BY role = \'agency_owner\' DESC, last_name, first_name',
            ['agency' => $agencyId]
        );
    }

    /** @return array<string, mixed>|null Ligne brute (formulaires), sans le hachage du mot de passe */
    public function row(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT id, role, country_id, agency_id, first_name, last_name, email, phone, whatsapp, job_title, is_active,
                    must_change_password, last_login_at, created_at, created_by_user_id
             FROM users WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /** Adresse déjà utilisée (y compris par un compte supprimé dont l'adresse n'a pas été libérée) ? */
    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        return $this->db->scalar('SELECT 1 FROM users WHERE email = :email AND id <> :except LIMIT 1', ['email' => self::normalizeEmail($email), 'except' => $exceptId ?? 0]) !== null;
    }

    public function activeSuperAdminsCount(): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1 AND deleted_at IS NULL");
    }

    /**
     * Crée un compte sans mot de passe utilisable : l'utilisateur choisit le sien via le lien d'invitation.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data, PasswordHasher $hasher): int
    {
        return $this->db->insert('users', $data + [
            'password_hash' => $hasher->hash(bin2hex(random_bytes(32))),
            'must_change_password' => 1,
            'is_active' => 1,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $sets = implode(', ', array_map(static fn (string $column): string => "`{$column}` = :{$column}", array_keys($data)));
        $this->db->execute("UPDATE users SET {$sets} WHERE id = :id", $data + ['id' => $id]);
    }

    /** Déconnecte le compte partout (jetons « Rester connecté », liens de réinitialisation). */
    public function revokeAccess(int $id): void
    {
        $this->db->execute('DELETE FROM remember_tokens WHERE user_id = :id', ['id' => $id]);
        $this->db->execute('DELETE FROM password_resets WHERE user_id = :id AND used_at IS NULL', ['id' => $id]);
    }

    /**
     * Suppression logique : l'adresse email est libérée (remplacée) pour pouvoir être réutilisée, l'historique est conservé.
     */
    public function softDelete(int $id): void
    {
        $this->db->transaction(function (Database $db) use ($id): void {
            $this->revokeAccess($id);
            $db->execute(
                "UPDATE users SET deleted_at = UTC_TIMESTAMP(), is_active = 0, email = CONCAT('supprime-', id, '-', UNIX_TIMESTAMP(), '@invalid.local') WHERE id = :id",
                ['id' => $id]
            );
        });
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
