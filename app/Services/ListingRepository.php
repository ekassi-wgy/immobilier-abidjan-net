<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Lectures du site public : annonces en ligne, catégories, agences et chiffres du pays courant.
 *
 * Trois règles valables pour TOUTE requête de ce dépôt :
 *   1. `country_id = :country` (pays du site résolu par SiteResolver) ;
 *   2. seules les annonces `status = 'published'` et non supprimées sont visibles ;
 *   3. photos : `revision_id IS NULL` (une photo de révision en attente n'est jamais publique).
 * `property_private_details` n'est jamais joint ici.
 */
final class ListingRepository
{
    /** Colonnes nécessaires à une carte annonce (jamais SELECT *). */
    private const CARD_COLUMNS = "p.id, p.reference, p.title, p.slug, p.price, p.currency_code, p.price_period,
        p.living_area, p.land_area, p.rooms, p.bedrooms, p.bathrooms, p.is_featured, p.published_at,
        p.contact_phone, p.contact_whatsapp,
        c.name AS category_name, t.name AS transaction_name, t.slug AS transaction_slug,
        ci.name AS city_name, m.name AS commune_name, d.name AS district_name,
        a.name AS agency_name, a.slug AS agency_slug, a.is_verified AS agency_verified,
        a.phone AS agency_phone, a.whatsapp AS agency_whatsapp,
        (SELECT i.path FROM property_images i WHERE i.property_id = p.id AND i.revision_id IS NULL ORDER BY i.sort_order, i.id LIMIT 1) AS cover_path,
        (SELECT i.alt_text FROM property_images i WHERE i.property_id = p.id AND i.revision_id IS NULL ORDER BY i.sort_order, i.id LIMIT 1) AS cover_alt,
        (SELECT COUNT(*) FROM property_images i WHERE i.property_id = p.id AND i.revision_id IS NULL) AS photos_count";

    private const CARD_JOINS = "FROM properties p
        JOIN property_categories c ON c.id = p.category_id
        JOIN transaction_types t ON t.id = p.transaction_type_id
        JOIN cities ci ON ci.id = p.city_id
        LEFT JOIN communes m ON m.id = p.commune_id
        LEFT JOIN districts d ON d.id = p.district_id
        LEFT JOIN agencies a ON a.id = p.agency_id AND a.deleted_at IS NULL";

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Annonces mises en avant par l'équipe (mise en avant expirée = annonce ordinaire).
     *
     * @return list<array<string, mixed>>
     */
    public function featured(int $countryId, int $limit = 6): array
    {
        return $this->db->select(
            'SELECT ' . self::CARD_COLUMNS . ' ' . self::CARD_JOINS . '
             WHERE ' . $this->publishedScope() . '
               AND p.is_featured = 1 AND (p.featured_until IS NULL OR p.featured_until > UTC_TIMESTAMP())
             ORDER BY p.published_at DESC
             LIMIT :limit',
            ['country' => $countryId, 'limit' => $limit]
        );
    }

    /**
     * Dernières annonces publiées, en excluant celles déjà affichées ailleurs sur la page.
     *
     * @param list<int> $excludeIds
     * @return list<array<string, mixed>>
     */
    public function latest(int $countryId, int $limit = 8, array $excludeIds = []): array
    {
        $params = ['country' => $countryId, 'limit' => $limit];
        $exclude = '';
        foreach (array_values(array_unique(array_map('intval', $excludeIds))) as $index => $id) {
            $exclude .= ($exclude === '' ? '' : ', ') . ":ex{$index}";
            $params["ex{$index}"] = $id;
        }

        return $this->db->select(
            'SELECT ' . self::CARD_COLUMNS . ' ' . self::CARD_JOINS . '
             WHERE ' . $this->publishedScope() . ($exclude !== '' ? " AND p.id NOT IN ({$exclude})" : '') . '
             ORDER BY p.published_at DESC, p.id DESC
             LIMIT :limit',
            $params
        );
    }

    /**
     * Chiffres clés du pays : annonces en ligne, agences partenaires, communes couvertes, villes.
     *
     * @return array{listings: int, agencies: int, communes: int, cities: int}
     */
    public function keyFigures(int $countryId): array
    {
        $row = $this->db->selectOne(
            'SELECT
                (SELECT COUNT(*) FROM properties p WHERE ' . $this->publishedScope() . ') AS listings,
                (SELECT COUNT(*) FROM agencies a WHERE a.country_id = :country2 AND a.status = \'active\' AND a.deleted_at IS NULL) AS agencies,
                (SELECT COUNT(DISTINCT p.commune_id) FROM properties p WHERE ' . $this->publishedScope(':country3') . ' AND p.commune_id IS NOT NULL) AS communes,
                (SELECT COUNT(DISTINCT p.city_id) FROM properties p WHERE ' . $this->publishedScope(':country4') . ') AS cities',
            ['country' => $countryId, 'country2' => $countryId, 'country3' => $countryId, 'country4' => $countryId]
        );

        return [
            'listings' => (int) ($row['listings'] ?? 0),
            'agencies' => (int) ($row['agencies'] ?? 0),
            'communes' => (int) ($row['communes'] ?? 0),
            'cities' => (int) ($row['cities'] ?? 0),
        ];
    }

    /**
     * Familles de catégories proposées sur l'accueil, avec le nombre d'annonces en ligne.
     *
     * @return list<array<string, mixed>>
     */
    public function categoryFamilies(int $countryId, int $limit = 6): array
    {
        return $this->db->select(
            "SELECT f.id, f.slug, f.name, f.name_plural, f.icon,
                    (SELECT COUNT(*) FROM properties p
                       JOIN property_categories c2 ON c2.id = p.category_id
                      WHERE " . $this->publishedScope() . " AND (c2.id = f.id OR c2.parent_id = f.id)) AS listings
             FROM property_categories f
             WHERE f.parent_id IS NULL AND f.is_active = 1 AND (f.country_id IS NULL OR f.country_id = :country2)
             ORDER BY f.sort_order, f.name
             LIMIT :limit",
            ['country' => $countryId, 'country2' => $countryId, 'limit' => $limit]
        );
    }

    /**
     * Communes les plus représentées (raccourcis de recherche).
     *
     * @return list<array<string, mixed>>
     */
    public function popularCommunes(int $countryId, int $limit = 8): array
    {
        return $this->db->select(
            'SELECT m.id, m.slug, m.name, ci.slug AS city_slug, ci.name AS city_name, COUNT(*) AS listings
             FROM properties p
             JOIN communes m ON m.id = p.commune_id
             JOIN cities ci ON ci.id = m.city_id
             WHERE ' . $this->publishedScope() . ' AND m.is_active = 1
             GROUP BY m.id, m.slug, m.name, ci.slug, ci.name
             ORDER BY listings DESC, m.name
             LIMIT :limit',
            ['country' => $countryId, 'limit' => $limit]
        );
    }

    /**
     * Agences partenaires mises en vedette, avec leur nombre d'annonces en ligne.
     *
     * @return list<array<string, mixed>>
     */
    public function featuredAgencies(int $countryId, int $limit = 6): array
    {
        return $this->db->select(
            "SELECT a.id, a.name, a.slug, a.logo_path, a.is_verified, ci.name AS city_name, m.name AS commune_name,
                    (SELECT COUNT(*) FROM properties p WHERE " . $this->publishedScope() . " AND p.agency_id = a.id) AS listings
             FROM agencies a
             LEFT JOIN cities ci ON ci.id = a.city_id
             LEFT JOIN communes m ON m.id = a.commune_id
             WHERE a.country_id = :country2 AND a.status = 'active' AND a.deleted_at IS NULL AND a.is_featured = 1
             ORDER BY listings DESC, a.name
             LIMIT :limit",
            ['country' => $countryId, 'country2' => $countryId, 'limit' => $limit]
        );
    }

    /**
     * Diapositives du hero (bannières « home_hero » actives et dans leur période).
     *
     * @return list<array<string, mixed>>
     */
    public function heroBanners(int $siteId, int $limit = 6): array
    {
        return $this->db->select(
            "SELECT id, title, subtitle, caption, image_path, link_url
             FROM banners
             WHERE site_id = :site AND placement = 'home_hero' AND is_active = 1
               AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP())
               AND (ends_at IS NULL OR ends_at >= UTC_TIMESTAMP())
             ORDER BY sort_order, id
             LIMIT :limit",
            ['site' => $siteId, 'limit' => $limit]
        );
    }

    /** Fourchettes de prix proposées dans la recherche rapide, par transaction. */
    public function priceSteps(int $countryId, int $transactionId): array
    {
        $row = $this->db->selectOne(
            'SELECT MIN(p.price) AS low, MAX(p.price) AS high FROM properties p
             WHERE ' . $this->publishedScope() . ' AND p.transaction_type_id = :transaction AND p.price IS NOT NULL',
            ['country' => $countryId, 'transaction' => $transactionId]
        );

        return ['low' => $row['low'] !== null ? (float) $row['low'] : null, 'high' => $row['high'] !== null ? (float) $row['high'] : null];
    }

    /** Annonces en ligne du pays : condition commune à toutes les requêtes publiques. */
    private function publishedScope(string $countryParam = ':country'): string
    {
        return "p.country_id = {$countryParam} AND p.status = 'published' AND p.deleted_at IS NULL";
    }
}
