<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Contenu éditorial du back-office (lot 2.2) : pages, actualités et bannières du site courant.
 *
 * Les lectures publiques ne passent pas par ici : les pages sont servies par `PageRepository`
 * et les bannières du hero par `ListingRepository::heroBanners()`. Ce dépôt ne fait que la
 * gestion depuis `/cmsadmin`.
 *
 * Toute écriture sur `pages` doit être suivie de `PageRepository::flush()` : la liste des slugs
 * publiés sert à déclarer les routes.
 */
final class ContentRepository
{
    /** Emplacements de bannières prévus par la maquette. */
    public const PLACEMENTS = ['home_hero', 'home_middle', 'listing_sidebar'];

    public function __construct(private readonly Database $db)
    {
    }

    // -- Pages ----------------------------------------------------------------------------

    /** @return array{rows: list<array<string, mixed>>, total: int} */
    public function pages(int $siteId, string $locale, int $limit, int $offset): array
    {
        $params = ['site' => $siteId, 'locale' => $locale];

        return [
            'total' => (int) $this->db->scalar('SELECT COUNT(*) FROM pages WHERE site_id = :site AND locale = :locale', $params),
            'rows' => $this->db->select(
                'SELECT id, code, slug, title, is_published, updated_at FROM pages
                 WHERE site_id = :site AND locale = :locale
                 ORDER BY code IS NULL, code, title
                 LIMIT :limit OFFSET :offset',
                $params + ['limit' => $limit, 'offset' => $offset]
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    public function page(int $id, int $siteId): ?array
    {
        return $this->db->selectOne('SELECT * FROM pages WHERE id = :id AND site_id = :site', ['id' => $id, 'site' => $siteId]);
    }

    /** @param array<string, mixed> $data */
    public function savePage(?int $id, int $siteId, array $data, int $userId): int
    {
        $data['updated_by_user_id'] = $userId;

        if ($id === null) {
            return $this->db->insert('pages', $data + ['site_id' => $siteId, 'locale' => 'fr']);
        }
        $this->db->execute(
            'UPDATE pages SET slug = :slug, title = :title, content = :content, meta_title = :meta_title,
                    meta_description = :meta_description, is_published = :is_published, updated_by_user_id = :updated_by_user_id
             WHERE id = :id AND site_id = :site',
            $data + ['id' => $id, 'site' => $siteId]
        );

        return $id;
    }

    /** Une page système (code non nul) ne se supprime pas : elle est attendue par le pied de page. */
    public function deletePage(int $id, int $siteId): void
    {
        $this->db->execute('DELETE FROM pages WHERE id = :id AND site_id = :site AND code IS NULL', ['id' => $id, 'site' => $siteId]);
    }

    public function pageSlugExists(int $siteId, string $locale, string $slug, ?int $exceptId = null): bool
    {
        return $this->db->scalar(
            'SELECT 1 FROM pages WHERE site_id = :site AND locale = :locale AND slug = :slug AND id <> :except LIMIT 1',
            ['site' => $siteId, 'locale' => $locale, 'slug' => $slug, 'except' => $exceptId ?? 0]
        ) !== null;
    }

    // -- Actualités -----------------------------------------------------------------------

    /**
     * @param array{q?: string, statut?: string} $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function posts(int $siteId, string $locale, array $filters, int $limit, int $offset): array
    {
        $where = 'p.site_id = :site AND p.locale = :locale';
        $params = ['site' => $siteId, 'locale' => $locale];

        if (trim((string) ($filters['q'] ?? '')) !== '') {
            $where .= ' AND p.title LIKE :q';
            $params['q'] = '%' . addcslashes(trim((string) $filters['q']), '%_\\') . '%';
        }
        if (in_array($filters['statut'] ?? '', ['draft', 'published'], true)) {
            $where .= ' AND p.status = :status';
            $params['status'] = $filters['statut'];
        }

        return [
            'total' => (int) $this->db->scalar("SELECT COUNT(*) FROM blog_posts p WHERE {$where}", $params),
            'rows' => $this->db->select(
                "SELECT p.id, p.slug, p.title, p.status, p.published_at, p.cover_image_path, p.updated_at,
                        CONCAT(u.first_name, ' ', u.last_name) AS author
                 FROM blog_posts p
                 LEFT JOIN users u ON u.id = p.author_user_id
                 WHERE {$where}
                 ORDER BY COALESCE(p.published_at, p.created_at) DESC
                 LIMIT :limit OFFSET :offset",
                $params + ['limit' => $limit, 'offset' => $offset]
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    public function post(int $id, int $siteId): ?array
    {
        return $this->db->selectOne('SELECT * FROM blog_posts WHERE id = :id AND site_id = :site', ['id' => $id, 'site' => $siteId]);
    }

    /** @param array<string, mixed> $data */
    public function savePost(?int $id, int $siteId, array $data): int
    {
        if ($id === null) {
            return $this->db->insert('blog_posts', $data + ['site_id' => $siteId, 'locale' => 'fr']);
        }
        $this->db->execute(
            'UPDATE blog_posts SET slug = :slug, title = :title, excerpt = :excerpt, content = :content,
                    cover_image_path = :cover_image_path, status = :status, published_at = :published_at,
                    meta_title = :meta_title, meta_description = :meta_description
             WHERE id = :id AND site_id = :site',
            $data + ['id' => $id, 'site' => $siteId]
        );

        return $id;
    }

    public function deletePost(int $id, int $siteId): void
    {
        $this->db->execute('DELETE FROM blog_posts WHERE id = :id AND site_id = :site', ['id' => $id, 'site' => $siteId]);
    }

    public function postSlugExists(int $siteId, string $locale, string $slug, ?int $exceptId = null): bool
    {
        return $this->db->scalar(
            'SELECT 1 FROM blog_posts WHERE site_id = :site AND locale = :locale AND slug = :slug AND id <> :except LIMIT 1',
            ['site' => $siteId, 'locale' => $locale, 'slug' => $slug, 'except' => $exceptId ?? 0]
        ) !== null;
    }

    // -- Actualités : lectures publiques ---------------------------------------------------

    /**
     * Actualités en ligne, paginées.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function publishedPosts(int $siteId, string $locale, int $limit, int $offset): array
    {
        $params = ['site' => $siteId, 'locale' => $locale];
        $where = "site_id = :site AND locale = :locale AND status = 'published' AND published_at IS NOT NULL AND published_at <= UTC_TIMESTAMP()";

        return [
            'total' => (int) $this->db->scalar("SELECT COUNT(*) FROM blog_posts WHERE {$where}", $params),
            'rows' => $this->db->select(
                "SELECT slug, title, excerpt, cover_image_path, published_at FROM blog_posts
                 WHERE {$where} ORDER BY published_at DESC LIMIT :limit OFFSET :offset",
                $params + ['limit' => $limit, 'offset' => $offset]
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    public function publishedPost(string $slug, int $siteId, string $locale): ?array
    {
        return $this->db->selectOne(
            "SELECT b.slug, b.title, b.excerpt, b.content, b.cover_image_path, b.published_at, b.updated_at,
                    b.meta_title, b.meta_description, CONCAT(u.first_name, ' ', u.last_name) AS author
             FROM blog_posts b
             LEFT JOIN users u ON u.id = b.author_user_id
             WHERE b.slug = :slug AND b.site_id = :site AND b.locale = :locale
               AND b.status = 'published' AND b.published_at IS NOT NULL AND b.published_at <= UTC_TIMESTAMP()",
            ['slug' => $slug, 'site' => $siteId, 'locale' => $locale]
        );
    }

    // -- Bannières ------------------------------------------------------------------------

    /** @return array{rows: list<array<string, mixed>>, total: int} */
    public function banners(int $siteId, string $placement, int $limit, int $offset): array
    {
        $where = 'site_id = :site';
        $params = ['site' => $siteId];
        if (in_array($placement, self::PLACEMENTS, true)) {
            $where .= ' AND placement = :placement';
            $params['placement'] = $placement;
        }

        return [
            'total' => (int) $this->db->scalar("SELECT COUNT(*) FROM banners WHERE {$where}", $params),
            'rows' => $this->db->select(
                "SELECT * FROM banners WHERE {$where} ORDER BY placement, sort_order, id LIMIT :limit OFFSET :offset",
                $params + ['limit' => $limit, 'offset' => $offset]
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    public function banner(int $id, int $siteId): ?array
    {
        return $this->db->selectOne('SELECT * FROM banners WHERE id = :id AND site_id = :site', ['id' => $id, 'site' => $siteId]);
    }

    /** @param array<string, mixed> $data */
    public function saveBanner(?int $id, int $siteId, array $data): int
    {
        if ($id === null) {
            return $this->db->insert('banners', $data + ['site_id' => $siteId]);
        }
        $this->db->execute(
            'UPDATE banners SET placement = :placement, title = :title, subtitle = :subtitle, caption = :caption,
                    image_path = :image_path, link_url = :link_url, starts_at = :starts_at, ends_at = :ends_at,
                    is_active = :is_active, sort_order = :sort_order
             WHERE id = :id AND site_id = :site',
            $data + ['id' => $id, 'site' => $siteId]
        );

        return $id;
    }

    public function deleteBanner(int $id, int $siteId): void
    {
        $this->db->execute('DELETE FROM banners WHERE id = :id AND site_id = :site', ['id' => $id, 'site' => $siteId]);
    }

    /** Chemin de l'image d'une bannière, pour la supprimer du disque au remplacement. */
    public function bannerImage(int $id, int $siteId): ?string
    {
        $path = $this->db->scalar('SELECT image_path FROM banners WHERE id = :id AND site_id = :site', ['id' => $id, 'site' => $siteId]);

        return $path !== null ? (string) $path : null;
    }
}
