<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Compte du back-office (table users) : équipe interne ou agence partenaire.
 * Aucun compte visiteur n'existe sur la plateforme.
 */
final class User
{
    public const SUPER_ADMIN = 'super_admin';
    public const COUNTRY_ADMIN = 'country_admin';
    public const AGENCY_OWNER = 'agency_owner';
    public const AGENCY_AGENT = 'agency_agent';

    public const ROLES = [self::SUPER_ADMIN, self::COUNTRY_ADMIN, self::AGENCY_OWNER, self::AGENCY_AGENT];

    /** Colonnes lues pour construire un utilisateur (jamais de SELECT * sur users). */
    public const COLUMNS = 'u.id, u.role, u.country_id, u.agency_id, u.first_name, u.last_name, u.email, u.password_hash,
        u.is_active, u.must_change_password, u.last_login_at, a.name AS agency_name, a.status AS agency_status, a.deleted_at AS agency_deleted_at';

    public function __construct(
        public readonly int $id,
        public readonly string $role,
        public readonly ?int $countryId,
        public readonly ?int $agencyId,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly bool $isActive,
        public readonly bool $mustChangePassword,
        public readonly ?string $lastLoginAt,
        public readonly ?string $agencyName,
        public readonly bool $agencyIsActive,
    ) {
    }

    /** @param array<string, mixed> $row Ligne issue de User::COLUMNS (users u LEFT JOIN agencies a) */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['role'],
            $row['country_id'] !== null ? (int) $row['country_id'] : null,
            $row['agency_id'] !== null ? (int) $row['agency_id'] : null,
            (string) $row['first_name'],
            (string) $row['last_name'],
            (string) $row['email'],
            (string) $row['password_hash'],
            (bool) $row['is_active'],
            (bool) $row['must_change_password'],
            $row['last_login_at'] !== null ? (string) $row['last_login_at'] : null,
            $row['agency_name'] !== null ? (string) $row['agency_name'] : null,
            $row['agency_id'] === null || (($row['agency_status'] ?? null) === 'active' && ($row['agency_deleted_at'] ?? null) === null),
        );
    }

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::SUPER_ADMIN;
    }

    public function isCountryAdmin(): bool
    {
        return $this->role === self::COUNTRY_ADMIN;
    }

    /** Équipe interne : Super Admin ou Admin Pays. */
    public function isStaff(): bool
    {
        return $this->isSuperAdmin() || $this->isCountryAdmin();
    }

    public function isAgency(): bool
    {
        return $this->role === self::AGENCY_OWNER || $this->role === self::AGENCY_AGENT;
    }

    /** Le compte peut-il se connecter (compte actif, agence active pour les comptes agence) ? */
    public function canLogin(): bool
    {
        return $this->isActive && $this->agencyIsActive;
    }

    /** Le compte peut-il travailler sur le site de ce pays ? Le Super Admin couvre tous les pays. */
    public function canAccessCountry(int $countryId): bool
    {
        return $this->isSuperAdmin() || $this->countryId === $countryId;
    }

    /**
     * Rôle du menu et des écrans du back-office (config/cmsadmin-menu.php) :
     * super_admin | country_admin | agency (responsable et agent d'agence partagent les mêmes écrans).
     */
    public function menuRole(): string
    {
        return $this->isAgency() ? 'agency' : $this->role;
    }

    /**
     * Vérifie un ou plusieurs rôles. Accepte aussi l'alias « agency » (responsable ou agent) et « staff ».
     *
     * @param string ...$roles
     */
    public function hasRole(string ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($role === $this->role || ($role === 'agency' && $this->isAgency()) || ($role === 'staff' && $this->isStaff())) {
                return true;
            }
        }

        return false;
    }
}
