<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Pays, sites et domaines (écrans « Pays & sites » du Super Admin).
 * Après toute écriture : SiteRepository::flush() (cache de résolution des sites).
 */
final class CountryRepository
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $countries = null;

    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> [id => pays], triés */
    public function all(): array
    {
        if ($this->countries === null) {
            $this->countries = [];
            $rows = $this->db->select(
                'SELECT c.*, (SELECT COUNT(*) FROM sites s WHERE s.country_id = c.id) AS sites_count,
                        (SELECT COUNT(*) FROM cities ci WHERE ci.country_id = c.id) AS cities_count
                 FROM countries c ORDER BY c.sort_order, c.name'
            );
            foreach ($rows as $row) {
                $this->countries[(int) $row['id']] = $row;
            }
        }

        return $this->countries;
    }

    /** @return array<int, string> [id => nom] */
    public function options(bool $activeOnly = false): array
    {
        $options = [];
        foreach ($this->all() as $id => $country) {
            if (!$activeOnly || (int) $country['is_active'] === 1) {
                $options[$id] = (string) $country['name'];
            }
        }

        return $options;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->all()[$id] ?? null;
    }

    public function iso2Exists(string $iso2, ?int $exceptId = null): bool
    {
        return $this->db->scalar('SELECT 1 FROM countries WHERE iso2 = :iso AND id <> :except', ['iso' => $iso2, 'except' => $exceptId ?? 0]) !== null;
    }

    public function activeSitesCount(int $countryId): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM sites WHERE country_id = :id AND status <> 'disabled'", ['id' => $countryId]);
    }

    /** @param array<string, mixed> $data */
    public function insertCountry(array $data): int
    {
        $this->countries = null;

        return $this->db->insert('countries', $data);
    }

    /** @param array<string, mixed> $data */
    public function updateCountry(int $id, array $data): void
    {
        $this->countries = null;
        $this->update('countries', $id, $data);
    }

    // Sites ---------------------------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function sites(): array
    {
        return $this->db->select(
            "SELECT s.*, c.name AS country_name, c.iso2, c.currency_symbol,
                    (SELECT host FROM site_domains d WHERE d.site_id = s.id AND d.environment = 'production' ORDER BY d.is_primary DESC, d.id LIMIT 1) AS primary_host,
                    (SELECT COUNT(*) FROM site_domains d WHERE d.site_id = s.id) AS domains_count
             FROM sites s JOIN countries c ON c.id = s.country_id ORDER BY c.sort_order, s.name"
        );
    }

    /** @return array<string, mixed>|null */
    public function site(int $id): ?array
    {
        return $this->db->selectOne('SELECT s.*, c.name AS country_name FROM sites s JOIN countries c ON c.id = s.country_id WHERE s.id = :id', ['id' => $id]);
    }

    public function siteCodeExists(string $code, ?int $exceptId = null): bool
    {
        return $this->db->scalar('SELECT 1 FROM sites WHERE code = :code AND id <> :except', ['code' => $code, 'except' => $exceptId ?? 0]) !== null;
    }

    /** @param array<string, mixed> $data */
    public function insertSite(array $data): int
    {
        return $this->db->insert('sites', $data);
    }

    /** @param array<string, mixed> $data */
    public function updateSite(int $id, array $data): void
    {
        $this->update('sites', $id, $data);
    }

    // Domaines ------------------------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function domains(int $siteId): array
    {
        return $this->db->select(
            "SELECT * FROM site_domains WHERE site_id = :id ORDER BY FIELD(environment, 'production', 'staging', 'local'), is_primary DESC, host",
            ['id' => $siteId]
        );
    }

    /** @return array<string, mixed>|null */
    public function domain(int $siteId, int $domainId): ?array
    {
        return $this->db->selectOne('SELECT * FROM site_domains WHERE id = :id AND site_id = :site', ['id' => $domainId, 'site' => $siteId]);
    }

    public function hostExists(string $host): bool
    {
        return $this->db->scalar('SELECT 1 FROM site_domains WHERE host = :host', ['host' => $host]) !== null;
    }

    /** Ajoute un domaine ; un domaine principal remplace le précédent principal du même environnement. */
    public function addDomain(int $siteId, string $host, string $environment, bool $primary): int
    {
        return $this->db->transaction(function (Database $db) use ($siteId, $host, $environment, $primary): int {
            if ($primary) {
                $db->execute('UPDATE site_domains SET is_primary = 0 WHERE site_id = :site AND environment = :env', ['site' => $siteId, 'env' => $environment]);
            }

            return $db->insert('site_domains', ['site_id' => $siteId, 'host' => $host, 'environment' => $environment, 'is_primary' => $primary ? 1 : 0]);
        });
    }

    public function makePrimary(int $siteId, int $domainId, string $environment): void
    {
        $this->db->transaction(function (Database $db) use ($siteId, $domainId, $environment): void {
            $db->execute('UPDATE site_domains SET is_primary = 0 WHERE site_id = :site AND environment = :env', ['site' => $siteId, 'env' => $environment]);
            $db->execute('UPDATE site_domains SET is_primary = 1 WHERE id = :id', ['id' => $domainId]);
        });
    }

    public function deleteDomain(int $domainId): void
    {
        $this->db->execute('DELETE FROM site_domains WHERE id = :id', ['id' => $domainId]);
    }

    /** @param array<string, mixed> $data */
    private function update(string $table, int $id, array $data): void
    {
        $sets = implode(', ', array_map(static fn (string $column): string => "`{$column}` = :{$column}", array_keys($data)));
        $this->db->execute("UPDATE `{$table}` SET {$sets} WHERE id = :id", $data + ['id' => $id]);
    }
}
