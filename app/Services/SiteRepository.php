<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;
use App\Models\Country;
use App\Models\Site;

/**
 * Lecture des sites, domaines, pays et paramètres.
 *
 * Ces tables sont petites et lues à chaque requête : elles sont chargées en une fois (3 requêtes)
 * puis gardées en cache fichier (config app.cache.sites_ttl). Toute écriture du back-office
 * sur sites, site_domains, countries ou settings doit appeler flush().
 */
final class SiteRepository
{
    private const CACHE_KEY = 'sites';

    /** @var array{domains: array<string, array{site_id: int, environment: string}>, sites: array<int, array<string, mixed>>, countries: array<int, array<string, mixed>>, settings: array<int, array<string, mixed>>}|null */
    private ?array $snapshot = null;

    public function __construct(
        private readonly Database $db,
        private readonly Cache $cache,
        private readonly ?int $ttl,
    ) {
    }

    /**
     * Site correspondant au nom d'hôte, ou null si le domaine est inconnu.
     *
     * @param bool $allowLocal Accepter les domaines d'environnement « local » (APP_ENV=local uniquement)
     */
    public function findByHost(string $host, bool $allowLocal): ?Site
    {
        $snapshot = $this->snapshot();
        $domain = $snapshot['domains'][$host] ?? null;

        if ($domain === null || ($domain['environment'] === 'local' && !$allowLocal)) {
            return null;
        }

        $site = $snapshot['sites'][$domain['site_id']] ?? null;
        $country = $site !== null ? ($snapshot['countries'][$site['country_id']] ?? null) : null;
        if ($site === null || $country === null) {
            return null;
        }

        $locales = json_decode((string) ($site['supported_locales'] ?? ''), true);

        return new Site(
            (int) $site['id'],
            (string) $site['code'],
            (string) $site['name'],
            (string) $site['theme'],
            (string) $site['default_locale'],
            is_array($locales) && $locales !== [] ? array_values(array_filter($locales, 'is_string')) : [(string) $site['default_locale']],
            $site['contact_email'] ?: null,
            $site['contact_phone'] ?: null,
            $site['contact_whatsapp'] ?: null,
            $site['address'] ?: null,
            (string) $site['status'],
            Country::fromRow($country),
            $host,
            $domain['environment'],
            $site['primary_host'],
            isset($site['latitude']) && $site['latitude'] !== null ? (float) $site['latitude'] : null,
            isset($site['longitude']) && $site['longitude'] !== null ? (float) $site['longitude'] : null,
            self::socialLinks($site['social_links'] ?? null),
            trim((string) ($site['analytics_id'] ?? '')) ?: null,
        );
    }

    /**
     * Liens de réseaux sociaux lisibles : réseaux connus seulement, URL non vides, ordre d'affichage fixe.
     *
     * @return array<string, string>
     */
    public static function socialLinks(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($decoded)) {
            return [];
        }

        $links = [];
        foreach (array_keys(Site::SOCIAL_NETWORKS) as $network) {
            $url = trim((string) ($decoded[$network] ?? ''));
            if ($url !== '') {
                $links[$network] = $url;
            }
        }

        return $links;
    }

    /**
     * Paramètres effectifs : valeurs globales (site_id NULL) surchargées par celles du site.
     *
     * @return array<string, mixed>
     */
    public function settingsFor(?int $siteId): array
    {
        $settings = $this->snapshot()['settings'];

        return ($siteId !== null ? ($settings[$siteId] ?? []) : []) + ($settings[0] ?? []);
    }

    /** À appeler après toute modification des sites, domaines, pays ou paramètres. */
    public function flush(): void
    {
        $this->snapshot = null;
        $this->cache->forget(self::CACHE_KEY);
    }

    /** @return array{domains: array<string, array{site_id: int, environment: string}>, sites: array<int, array<string, mixed>>, countries: array<int, array<string, mixed>>, settings: array<int, array<string, mixed>>} */
    private function snapshot(): array
    {
        return $this->snapshot ??= $this->cache->remember(self::CACHE_KEY, $this->ttl, fn (): array => $this->load());
    }

    /** @return array{domains: array<string, array{site_id: int, environment: string}>, sites: array<int, array<string, mixed>>, countries: array<int, array<string, mixed>>, settings: array<int, array<string, mixed>>} */
    private function load(): array
    {
        $snapshot = ['domains' => [], 'sites' => [], 'countries' => [], 'settings' => []];

        foreach ($this->db->select('SELECT * FROM countries') as $row) {
            $snapshot['countries'][(int) $row['id']] = $row;
        }

        $primaryHosts = [];
        foreach ($this->db->select('SELECT site_id, host, environment, is_primary FROM site_domains ORDER BY is_primary DESC, id') as $row) {
            $host = strtolower(rtrim((string) $row['host'], '.'));
            $snapshot['domains'][$host] = ['site_id' => (int) $row['site_id'], 'environment' => (string) $row['environment']];
            if ((int) $row['is_primary'] === 1 && $row['environment'] === 'production') {
                $primaryHosts[(int) $row['site_id']] ??= $host;
            }
        }

        foreach ($this->db->select('SELECT * FROM sites') as $row) {
            $row['primary_host'] = $primaryHosts[(int) $row['id']] ?? null;
            $snapshot['sites'][(int) $row['id']] = $row;
        }

        foreach ($this->db->select('SELECT site_scope, setting_key, value FROM settings') as $row) {
            $snapshot['settings'][(int) $row['site_scope']][(string) $row['setting_key']] = $row['value'] === null
                ? null
                : json_decode((string) $row['value'], true);
        }

        return $snapshot;
    }
}
