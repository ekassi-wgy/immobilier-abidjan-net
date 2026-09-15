<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Agences partenaires (toujours filtrées par pays), zones couvertes et comptes rattachés.
 * Suppression logique (deleted_at) ; une agence fermée garde son historique.
 */
final class AgencyRepository
{
    public const STATUSES = ['active', 'suspended', 'closed'];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array{q?: string, statut?: string, verifiee?: string, ville?: int} $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function paginate(int $countryId, array $filters, int $limit, int $offset): array
    {
        $where = 'a.country_id = :country AND a.deleted_at IS NULL';
        $params = ['country' => $countryId];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (a.name LIKE :q OR a.legal_name LIKE :q2 OR a.rccm LIKE :q3 OR a.email LIKE :q4)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params += ['q' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        if (in_array($filters['statut'] ?? '', self::STATUSES, true)) {
            $where .= ' AND a.status = :status';
            $params['status'] = $filters['statut'];
        }
        if (($filters['verifiee'] ?? '') === 'oui') {
            $where .= ' AND a.is_verified = 1';
        } elseif (($filters['verifiee'] ?? '') === 'non') {
            $where .= ' AND a.is_verified = 0';
        }
        if (!empty($filters['ville'])) {
            $where .= ' AND a.city_id = :city';
            $params['city'] = (int) $filters['ville'];
        }

        $from = "FROM agencies a LEFT JOIN cities c ON c.id = a.city_id LEFT JOIN communes m ON m.id = a.commune_id WHERE {$where}";

        return [
            'total' => (int) $this->db->scalar("SELECT COUNT(*) {$from}", $params),
            'rows' => $this->db->select(
                "SELECT a.id, a.name, a.slug, a.logo_path, a.email, a.phone, a.status, a.is_verified, a.is_featured,
                        a.published_properties_count, a.created_at, c.name AS city_name, m.name AS commune_name,
                        (SELECT COUNT(*) FROM users u WHERE u.agency_id = a.id AND u.deleted_at IS NULL) AS users_count
                 {$from} ORDER BY a.status = 'active' DESC, a.name LIMIT :limit OFFSET :offset",
                $params + ['limit' => $limit, 'offset' => $offset]
            ),
        ];
    }

    /** @return array<string, int> Compteurs par statut pour les onglets */
    public function countsByStatus(int $countryId): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($this->db->select('SELECT status, COUNT(*) AS n FROM agencies WHERE country_id = :country AND deleted_at IS NULL GROUP BY status', ['country' => $countryId]) as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $countryId): ?array
    {
        return $this->db->selectOne(
            'SELECT a.*, c.name AS city_name, m.name AS commune_name FROM agencies a
             LEFT JOIN cities c ON c.id = a.city_id LEFT JOIN communes m ON m.id = a.commune_id
             WHERE a.id = :id AND a.country_id = :country AND a.deleted_at IS NULL',
            ['id' => $id, 'country' => $countryId]
        );
    }

    /** @return list<int> */
    public function zoneIds(int $agencyId): array
    {
        return array_map('intval', array_column($this->db->select('SELECT commune_id FROM agency_zones WHERE agency_id = :id', ['id' => $agencyId]), 'commune_id'));
    }

    public function slugExists(int $countryId, string $slug, ?int $exceptId = null): bool
    {
        return $this->db->scalar(
            'SELECT 1 FROM agencies WHERE country_id = :country AND slug = :slug AND id <> :except LIMIT 1',
            ['country' => $countryId, 'slug' => $slug, 'except' => $exceptId ?? 0]
        ) !== null;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<int>            $zones Communes couvertes
     */
    public function save(?int $id, array $data, array $zones): int
    {
        return $this->db->transaction(function (Database $db) use ($id, $data, $zones): int {
            if ($id === null) {
                $id = $db->insert('agencies', $data);
            } else {
                $sets = implode(', ', array_map(static fn (string $column): string => "`{$column}` = :{$column}", array_keys($data)));
                $db->execute("UPDATE agencies SET {$sets} WHERE id = :id", $data + ['id' => $id]);
                $db->execute('DELETE FROM agency_zones WHERE agency_id = :id', ['id' => $id]);
            }
            foreach (array_unique($zones) as $communeId) {
                $db->execute('INSERT INTO agency_zones (agency_id, commune_id) VALUES (:a, :c)', ['a' => $id, 'c' => $communeId]);
            }

            return $id;
        });
    }

    public function updateLogo(int $id, ?string $path): void
    {
        $this->db->execute('UPDATE agencies SET logo_path = :path WHERE id = :id', ['path' => $path, 'id' => $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->execute('UPDATE agencies SET status = :status WHERE id = :id', ['status' => $status, 'id' => $id]);
    }

    /** Suppression logique : l'agence disparaît des listes, ses comptes ne peuvent plus se connecter. */
    public function softDelete(int $id): void
    {
        $this->db->execute("UPDATE agencies SET deleted_at = UTC_TIMESTAMP(), status = 'closed', is_featured = 0 WHERE id = :id", ['id' => $id]);
    }

    public function propertiesCount(int $agencyId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM properties WHERE agency_id = :id AND deleted_at IS NULL', ['id' => $agencyId]);
    }
}
