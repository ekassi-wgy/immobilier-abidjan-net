<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;

/**
 * Pages éditoriales et légales (table `pages`) : À propos, Comment ça marche, mentions légales,
 * CGU, confidentialité, cookies. Le back-office de rédaction arrive au lot 2.2 ; ce dépôt ne fait
 * que servir les pages déjà publiées.
 *
 * Une page non publiée n'existe pas pour le site public : elle répond 404 et son lien disparaît
 * du pied de page. Tant que le client n'a pas fourni ses textes légaux, aucune page vide n'est
 * donc servie.
 *
 * Routage : les slugs publiés sont mis en cache (`publishedSlugs()`) et lus au moment de déclarer
 * les routes, avant que le site courant ne soit résolu — d'où une liste tous sites confondus,
 * `find()` vérifiant ensuite que la page existe bien sur le site demandé. Un slug de page ne doit
 * jamais reprendre un slug de `transaction_types` (les routes de recherche passeraient avant).
 */
final class PageRepository
{
    private const CACHE_KEY = 'page_slugs';

    public function __construct(
        private readonly Database $db,
        private readonly Cache $cache,
        private readonly ?int $ttl,
    ) {
    }

    /**
     * Slugs de toutes les pages publiées, pour déclarer une route par page.
     *
     * @return list<string>
     */
    public function publishedSlugs(): array
    {
        $slugs = $this->cache->remember(self::CACHE_KEY, $this->ttl, fn (): array => array_map(
            'strval',
            array_column($this->db->select('SELECT DISTINCT slug FROM pages WHERE is_published = 1 ORDER BY slug'), 'slug')
        ));

        return is_array($slugs) ? $slugs : [];
    }

    /**
     * Page publiée du site et de la langue courante.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $slug, int $siteId, string $locale): ?array
    {
        return $this->db->selectOne(
            'SELECT slug, code, title, content, meta_title, meta_description, updated_at
             FROM pages WHERE slug = :slug AND site_id = :site AND locale = :locale AND is_published = 1',
            ['slug' => $slug, 'site' => $siteId, 'locale' => $locale]
        );
    }

    /**
     * Pages publiées d'un code donné, pour les liens du pied de page.
     *
     * @param list<string> $codes
     * @return array<string, array{slug: string, title: string}> [code => page]
     */
    public function byCodes(array $codes, int $siteId, string $locale): array
    {
        if ($codes === []) {
            return [];
        }

        $placeholders = [];
        $params = ['site' => $siteId, 'locale' => $locale];
        foreach (array_values($codes) as $index => $code) {
            $placeholders[] = ":c{$index}";
            $params["c{$index}"] = $code;
        }

        $pages = [];
        foreach ($this->db->select(
            'SELECT code, slug, title FROM pages
             WHERE site_id = :site AND locale = :locale AND is_published = 1
               AND code IN (' . implode(', ', $placeholders) . ')',
            $params
        ) as $row) {
            $pages[(string) $row['code']] = ['slug' => (string) $row['slug'], 'title' => (string) $row['title']];
        }

        return $pages;
    }

    /** À appeler après toute écriture dans `pages` (lot 2.2) : les routes en dépendent. */
    public function flush(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }
}
