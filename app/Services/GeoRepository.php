<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Référentiel géographique : villes → communes → quartiers, toujours filtré par pays.
 * Les suppressions ne sont possibles que pour un élément inutilisé (sinon : désactivation).
 */
final class GeoRepository
{
    public const LEVELS = ['city', 'commune', 'district'];

    public function __construct(private readonly Database $db)
    {
    }

    // Villes -----------------------------------------------------------------------------------

    /**
     * @param array{q?: string, etat?: string} $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function cities(int $countryId, array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->commonFilters('c', $filters, ['country' => $countryId], 'c.country_id = :country');

        return $this->paginate(
            "FROM cities c WHERE {$where}",
            'c.*, (SELECT COUNT(*) FROM communes m WHERE m.city_id = c.id) AS communes_count',
            'c.sort_order, c.name',
            $params,
            $limit,
            $offset
        );
    }

    /** @return array<string, mixed>|null */
    public function city(int $id, int $countryId): ?array
    {
        return $this->db->selectOne('SELECT * FROM cities WHERE id = :id AND country_id = :country', ['id' => $id, 'country' => $countryId]);
    }

    /** @return array<int, string> [id => nom], villes du pays */
    public function cityOptions(int $countryId, bool $activeOnly = false): array
    {
        return $this->options(
            'SELECT id, name FROM cities WHERE country_id = :country' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, name',
            ['country' => $countryId]
        );
    }

    // Communes ---------------------------------------------------------------------------------

    /**
     * @param array{q?: string, etat?: string, ville?: int|null} $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function communes(int $countryId, array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->commonFilters('m', $filters, ['country' => $countryId], 'c.country_id = :country');
        if (!empty($filters['ville'])) {
            $where .= ' AND m.city_id = :city';
            $params['city'] = (int) $filters['ville'];
        }

        return $this->paginate(
            "FROM communes m JOIN cities c ON c.id = m.city_id WHERE {$where}",
            'm.*, c.name AS city_name, (SELECT COUNT(*) FROM districts d WHERE d.commune_id = m.id) AS districts_count',
            'c.sort_order, c.name, m.sort_order, m.name',
            $params,
            $limit,
            $offset
        );
    }

    /** @return array<string, mixed>|null */
    public function commune(int $id, int $countryId): ?array
    {
        return $this->db->selectOne(
            'SELECT m.*, c.name AS city_name FROM communes m JOIN cities c ON c.id = m.city_id WHERE m.id = :id AND c.country_id = :country',
            ['id' => $id, 'country' => $countryId]
        );
    }

    /** @return array<int, string> [id => « Ville · Commune »] */
    public function communeOptions(int $countryId, ?int $cityId = null): array
    {
        return $this->options(
            'SELECT m.id, CONCAT(c.name, \' · \', m.name) AS name FROM communes m JOIN cities c ON c.id = m.city_id
             WHERE c.country_id = :country' . ($cityId !== null ? ' AND c.id = :city' : '') . ' ORDER BY c.sort_order, c.name, m.sort_order, m.name',
            ['country' => $countryId] + ($cityId !== null ? ['city' => $cityId] : [])
        );
    }

    // Quartiers --------------------------------------------------------------------------------

    /**
     * @param array{q?: string, etat?: string, ville?: int|null, commune?: int|null} $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function districts(int $countryId, array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->commonFilters('d', $filters, ['country' => $countryId], 'c.country_id = :country');
        if (!empty($filters['ville'])) {
            $where .= ' AND c.id = :city';
            $params['city'] = (int) $filters['ville'];
        }
        if (!empty($filters['commune'])) {
            $where .= ' AND m.id = :commune';
            $params['commune'] = (int) $filters['commune'];
        }

        return $this->paginate(
            "FROM districts d JOIN communes m ON m.id = d.commune_id JOIN cities c ON c.id = m.city_id WHERE {$where}",
            'd.*, m.name AS commune_name, c.name AS city_name',
            'c.sort_order, c.name, m.sort_order, m.name, d.sort_order, d.name',
            $params,
            $limit,
            $offset
        );
    }

    /** @return array<string, mixed>|null */
    public function district(int $id, int $countryId): ?array
    {
        return $this->db->selectOne(
            'SELECT d.*, m.name AS commune_name, c.name AS city_name FROM districts d
             JOIN communes m ON m.id = d.commune_id JOIN cities c ON c.id = m.city_id
             WHERE d.id = :id AND c.country_id = :country',
            ['id' => $id, 'country' => $countryId]
        );
    }

    // Écriture commune aux trois niveaux -------------------------------------------------------

    /** @param array<string, mixed> $data */
    public function insert(string $level, array $data): int
    {
        return $this->db->insert($this->table($level), $data);
    }

    /** @param array<string, mixed> $data */
    public function update(string $level, int $id, array $data): void
    {
        $sets = implode(', ', array_map(static fn (string $column): string => "`{$column}` = :{$column}", array_keys($data)));
        $this->db->execute("UPDATE `{$this->table($level)}` SET {$sets} WHERE id = :id", $data + ['id' => $id]);
    }

    public function setActive(string $level, int $id, bool $active): void
    {
        $this->db->execute("UPDATE `{$this->table($level)}` SET is_active = :active WHERE id = :id", ['active' => $active, 'id' => $id]);
    }

    public function delete(string $level, int $id): void
    {
        $this->db->execute("DELETE FROM `{$this->table($level)}` WHERE id = :id", ['id' => $id]);
    }

    /** Le slug est-il déjà pris chez un « frère » (même pays / même ville / même commune) ? */
    public function slugExists(string $level, int $parentId, string $slug, ?int $exceptId = null): bool
    {
        $parentColumn = ['city' => 'country_id', 'commune' => 'city_id', 'district' => 'commune_id'][$level];

        return $this->db->scalar(
            "SELECT 1 FROM `{$this->table($level)}` WHERE {$parentColumn} = :parent AND slug = :slug AND id <> :except LIMIT 1",
            ['parent' => $parentId, 'slug' => $slug, 'except' => $exceptId ?? 0]
        ) !== null;
    }

    /**
     * Utilisations empêchant la suppression : [clé de traduction => nombre].
     *
     * @return array<string, int>
     */
    public function usage(string $level, int $id): array
    {
        $queries = [
            'city' => [
                'geo.usage.communes' => 'SELECT COUNT(*) FROM communes WHERE city_id = :id',
                'geo.usage.properties' => 'SELECT COUNT(*) FROM properties WHERE city_id = :id',
                'geo.usage.agencies' => 'SELECT COUNT(*) FROM agencies WHERE city_id = :id',
                'geo.usage.partner_requests' => 'SELECT COUNT(*) FROM partner_requests WHERE city_id = :id',
            ],
            'commune' => [
                'geo.usage.districts' => 'SELECT COUNT(*) FROM districts WHERE commune_id = :id',
                'geo.usage.properties' => 'SELECT COUNT(*) FROM properties WHERE commune_id = :id',
                'geo.usage.agencies' => 'SELECT (SELECT COUNT(*) FROM agencies WHERE commune_id = :id) + (SELECT COUNT(*) FROM agency_zones WHERE commune_id = :id2)',
                'geo.usage.partner_requests' => 'SELECT COUNT(*) FROM partner_requests WHERE commune_id = :id',
            ],
            'district' => [
                'geo.usage.properties' => 'SELECT COUNT(*) FROM properties WHERE district_id = :id',
            ],
        ][$level];

        $usage = [];
        foreach ($queries as $key => $sql) {
            $params = str_contains($sql, ':id2') ? ['id' => $id, 'id2' => $id] : ['id' => $id];
            $count = (int) $this->db->scalar($sql, $params);
            if ($count > 0) {
                $usage[$key] = $count;
            }
        }

        return $usage;
    }

    private function table(string $level): string
    {
        return ['city' => 'cities', 'commune' => 'communes', 'district' => 'districts'][$level];
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $params
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function commonFilters(string $alias, array $filters, array $params, string $where): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= " AND ({$alias}.name LIKE :q OR {$alias}.slug LIKE :q2)";
            $params['q'] = '%' . addcslashes($q, '%_\\') . '%';
            $params['q2'] = $params['q'];
        }
        if (($filters['etat'] ?? '') === 'actifs') {
            $where .= " AND {$alias}.is_active = 1";
        } elseif (($filters['etat'] ?? '') === 'inactifs') {
            $where .= " AND {$alias}.is_active = 0";
        }

        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    private function paginate(string $from, string $columns, string $order, array $params, int $limit, int $offset): array
    {
        return [
            'total' => (int) $this->db->scalar("SELECT COUNT(*) {$from}", $params),
            'rows' => $this->db->select("SELECT {$columns} {$from} ORDER BY {$order} LIMIT :limit OFFSET :offset", $params + ['limit' => $limit, 'offset' => $offset]),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, string>
     */
    private function options(string $sql, array $params): array
    {
        $options = [];
        foreach ($this->db->select($sql, $params) as $row) {
            $options[(int) $row['id']] = (string) $row['name'];
        }

        return $options;
    }
}
