<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;

/**
 * Référencement (lot 2.1) : surcharges de métadonnées par URL (`seo_meta`) et redirections
 * permanentes gérées en base (`redirects`).
 *
 * Les redirections sont consultées à **chaque** requête : la table entière du site est mise en
 * cache fichier, comme les sites et les pages. Toute écriture doit appeler `flush()`.
 *
 * Les métadonnées, elles, ne concernent qu'une page à la fois : une requête préparée suffit, et
 * seules les pages réellement surchargées ont une ligne.
 */
final class SeoRepository
{
    private const CACHE_KEY = 'redirects';

    /** Redirections acceptées (contrainte `chk_redirects_code`). 410 = page définitivement retirée. */
    public const CODES = [301, 302, 307, 308, 410];

    public function __construct(
        private readonly Database $db,
        private readonly Cache $cache,
        private readonly ?int $ttl,
    ) {
    }

    // -- Site public ----------------------------------------------------------------------

    /**
     * Surcharge de métadonnées d'une URL, si elle existe.
     *
     * @return array{meta_title: ?string, meta_description: ?string, og_image_path: ?string, intro_text: ?string, noindex: bool}|null
     */
    public function meta(string $path, int $siteId): ?array
    {
        $row = $this->db->selectOne(
            'SELECT meta_title, meta_description, og_image_path, intro_text, noindex
             FROM seo_meta WHERE site_id = :site AND path = :path',
            ['site' => $siteId, 'path' => $this->normalize($path)]
        );

        return $row === null ? null : [
            'meta_title' => $this->value($row['meta_title']),
            'meta_description' => $this->value($row['meta_description']),
            'og_image_path' => $this->value($row['og_image_path']),
            'intro_text' => $this->value($row['intro_text']),
            'noindex' => (int) $row['noindex'] === 1,
        ];
    }

    /**
     * URL mises en `noindex` pour ce site, en une seule requête.
     *
     * Le sitemap doit écarter ces pages : les interroger une par une ferait autant de requêtes
     * qu'il y a d'URL (près de 10 000 sur un site chargé).
     *
     * @return array<string, true> Chemins normalisés, en clés
     */
    public function noindexPaths(int $siteId): array
    {
        $paths = [];
        foreach ($this->db->select(
            'SELECT path FROM seo_meta WHERE site_id = :site AND noindex = 1',
            ['site' => $siteId]
        ) as $row) {
            $paths[(string) $row['path']] = true;
        }

        return $paths;
    }

    /**
     * Redirection active pour une URL, ou null.
     *
     * @return array{id: int, target: string, code: int}|null
     */
    public function redirect(string $path, int $siteId): ?array
    {
        return $this->redirects($siteId)[$this->normalize($path)] ?? null;
    }

    /** Compteur d'utilisation d'une redirection (sert à repérer celles devenues inutiles). */
    public function recordHit(int $id): void
    {
        $this->db->execute(
            'UPDATE redirects SET hits = hits + 1, last_hit_at = UTC_TIMESTAMP() WHERE id = :id',
            ['id' => $id]
        );
    }

    /**
     * Toutes les redirections actives d'un site, indexées par chemin source (en cache).
     *
     * @return array<string, array{id: int, target: string, code: int}>
     */
    private function redirects(int $siteId): array
    {
        $all = $this->cache->remember(self::CACHE_KEY, $this->ttl, function (): array {
            $bySite = [];
            foreach ($this->db->select(
                'SELECT id, site_id, source_path, target_path, http_code FROM redirects WHERE is_active = 1'
            ) as $row) {
                $bySite[(int) $row['site_id']][(string) $row['source_path']] = [
                    'id' => (int) $row['id'],
                    'target' => (string) $row['target_path'],
                    'code' => (int) $row['http_code'],
                ];
            }

            return $bySite;
        });

        return is_array($all) ? ($all[$siteId] ?? []) : [];
    }

    // -- Back-office ----------------------------------------------------------------------

    /**
     * Surcharges de métadonnées du site, les plus récentes d'abord.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function paginateMeta(int $siteId, string $search, int $limit, int $offset): array
    {
        $where = 'site_id = :site';
        $params = ['site' => $siteId];
        if ($search !== '') {
            $where .= ' AND (path LIKE :q OR meta_title LIKE :q2)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $params += ['q' => $like, 'q2' => $like];
        }

        return [
            'total' => (int) $this->db->scalar("SELECT COUNT(*) FROM seo_meta WHERE {$where}", $params),
            'rows' => $this->db->select(
                "SELECT * FROM seo_meta WHERE {$where} ORDER BY path LIMIT :limit OFFSET :offset",
                $params + ['limit' => $limit, 'offset' => $offset]
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    public function findMeta(int $id, int $siteId): ?array
    {
        return $this->db->selectOne('SELECT * FROM seo_meta WHERE id = :id AND site_id = :site', ['id' => $id, 'site' => $siteId]);
    }

    /** @param array<string, mixed> $data */
    public function saveMeta(?int $id, int $siteId, array $data): int
    {
        $data['path'] = $this->normalize((string) $data['path']);

        if ($id === null) {
            return $this->db->insert('seo_meta', $data + ['site_id' => $siteId]);
        }
        $this->db->execute(
            'UPDATE seo_meta SET path = :path, meta_title = :meta_title, meta_description = :meta_description,
                    og_image_path = :og_image_path, intro_text = :intro_text, noindex = :noindex
             WHERE id = :id AND site_id = :site',
            $data + ['id' => $id, 'site' => $siteId]
        );

        return $id;
    }

    public function deleteMeta(int $id, int $siteId): void
    {
        $this->db->execute('DELETE FROM seo_meta WHERE id = :id AND site_id = :site', ['id' => $id, 'site' => $siteId]);
    }

    public function metaPathExists(int $siteId, string $path, ?int $exceptId = null): bool
    {
        return $this->db->scalar(
            'SELECT 1 FROM seo_meta WHERE site_id = :site AND path = :path AND id <> :except LIMIT 1',
            ['site' => $siteId, 'path' => $this->normalize($path), 'except' => $exceptId ?? 0]
        ) !== null;
    }

    /**
     * Redirections du site, les plus utilisées d'abord.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function paginateRedirects(int $siteId, string $search, int $limit, int $offset): array
    {
        $where = 'site_id = :site';
        $params = ['site' => $siteId];
        if ($search !== '') {
            $where .= ' AND (source_path LIKE :q OR target_path LIKE :q2)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $params += ['q' => $like, 'q2' => $like];
        }

        return [
            'total' => (int) $this->db->scalar("SELECT COUNT(*) FROM redirects WHERE {$where}", $params),
            'rows' => $this->db->select(
                "SELECT * FROM redirects WHERE {$where} ORDER BY hits DESC, source_path LIMIT :limit OFFSET :offset",
                $params + ['limit' => $limit, 'offset' => $offset]
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    public function findRedirect(int $id, int $siteId): ?array
    {
        return $this->db->selectOne('SELECT * FROM redirects WHERE id = :id AND site_id = :site', ['id' => $id, 'site' => $siteId]);
    }

    /** @param array<string, mixed> $data */
    public function saveRedirect(?int $id, int $siteId, array $data): int
    {
        $data['source_path'] = $this->normalize((string) $data['source_path']);
        $data['target_path'] = $this->normalize((string) $data['target_path']);

        if ($id === null) {
            $newId = $this->db->insert('redirects', $data + ['site_id' => $siteId]);
        } else {
            $this->db->execute(
                'UPDATE redirects SET source_path = :source_path, target_path = :target_path,
                        http_code = :http_code, is_active = :is_active
                 WHERE id = :id AND site_id = :site',
                $data + ['id' => $id, 'site' => $siteId]
            );
            $newId = $id;
        }
        $this->flush();

        return $newId;
    }

    public function deleteRedirect(int $id, int $siteId): void
    {
        $this->db->execute('DELETE FROM redirects WHERE id = :id AND site_id = :site', ['id' => $id, 'site' => $siteId]);
        $this->flush();
    }

    public function redirectSourceExists(int $siteId, string $path, ?int $exceptId = null): bool
    {
        return $this->db->scalar(
            'SELECT 1 FROM redirects WHERE site_id = :site AND source_path = :path AND id <> :except LIMIT 1',
            ['site' => $siteId, 'path' => $this->normalize($path), 'except' => $exceptId ?? 0]
        ) !== null;
    }

    /** À appeler après toute écriture dans `redirects` : elles sont lues à chaque requête. */
    public function flush(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /** Chemin comparable : toujours un « / » au début, jamais à la fin, sans chaîne de requête. */
    public function normalize(string $path): string
    {
        $path = trim(explode('?', trim($path))[0]);
        $path = '/' . trim($path, '/');

        return $path === '//' ? '/' : $path;
    }

    private function value(mixed $raw): ?string
    {
        $value = trim((string) ($raw ?? ''));

        return $value === '' ? null : $value;
    }
}
