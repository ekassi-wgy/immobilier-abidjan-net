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
    /** Longueur minimale d'un mot indexé par InnoDB (innodb_ft_min_token_size). */
    private const FULLTEXT_MIN_WORD = 3;

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

    /**
     * Page de résultats : annonces correspondant aux critères, triées et paginées.
     *
     * @return list<array<string, mixed>>
     */
    public function search(int $countryId, SearchCriteria $criteria, int $limit, int $offset): array
    {
        $params = ['country' => $countryId, 'limit' => $limit, 'offset' => $offset];

        return $this->db->select(
            'SELECT ' . self::CARD_COLUMNS . ', p.description ' . self::CARD_JOINS . '
             WHERE ' . $this->publishedScope() . $this->conditions($criteria, $params) . '
             ORDER BY ' . $this->order($criteria->sort) . '
             LIMIT :limit OFFSET :offset',
            $params
        );
    }

    /** Nombre total de résultats (pagination et titre de la page). */
    public function countSearch(int $countryId, SearchCriteria $criteria): int
    {
        $params = ['country' => $countryId];

        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM properties p
             WHERE ' . $this->publishedScope() . $this->conditions($criteria, $params),
            $params
        );
    }

    /**
     * Points de la vue carte. Les annonces dont la localisation exacte n'est pas publique sont
     * arrondies au centième de degré (environ 1 km) : le quartier reste lisible, pas l'adresse.
     *
     * @return list<array<string, mixed>>
     */
    public function mapPoints(int $countryId, SearchCriteria $criteria, int $limit): array
    {
        $params = ['country' => $countryId, 'limit' => $limit];

        return $this->db->select(
            'SELECT ' . self::CARD_COLUMNS . ',
                    IF(p.show_exact_location = 1, p.latitude, ROUND(p.latitude, 2))  AS latitude,
                    IF(p.show_exact_location = 1, p.longitude, ROUND(p.longitude, 2)) AS longitude
             ' . self::CARD_JOINS . '
             WHERE ' . $this->publishedScope() . '
               AND p.latitude IS NOT NULL AND p.longitude IS NOT NULL' . $this->conditions($criteria, $params) . '
             ORDER BY ' . $this->order($criteria->sort) . '
             LIMIT :limit',
            $params
        );
    }

    /**
     * Annonces reprises depuis les favoris du visiteur (références mémorisées par le navigateur).
     * L'ordre du cookie est conservé : la dernière annonce ajoutée reste en tête côté vue.
     *
     * @param list<string> $references
     * @return list<array<string, mixed>>
     */
    public function byReferences(int $countryId, array $references): array
    {
        $references = array_slice(array_values(array_unique($references)), 0, 60);
        if ($references === []) {
            return [];
        }

        $placeholders = [];
        $params = ['country' => $countryId];
        foreach ($references as $index => $reference) {
            $placeholders[] = ":r{$index}";
            $params["r{$index}"] = $reference;
        }

        return $this->db->select(
            'SELECT ' . self::CARD_COLUMNS . ' ' . self::CARD_JOINS . '
             WHERE ' . $this->publishedScope() . ' AND p.reference IN (' . implode(', ', $placeholders) . ')',
            $params
        );
    }

    /**
     * Conditions SQL des critères de recherche. Chaque valeur passe par un paramètre préparé ;
     * seuls des noms de colonnes construits ici entrent dans la requête.
     *
     * @param array<string, mixed> $params Paramètres de la requête, complétés par référence
     */
    private function conditions(SearchCriteria $criteria, array &$params): string
    {
        $sql = ' AND p.transaction_type_id = :transaction';
        $params['transaction'] = $criteria->transaction['id'];

        if ($criteria->category !== null) {
            $params['category'] = $criteria->category['id'];
            if ($criteria->category['family']) {
                // MySQL n'accepte pas deux fois le même paramètre nommé (EMULATE_PREPARES=false).
                $params['category_family'] = $criteria->category['id'];
                $sql .= ' AND p.category_id IN (SELECT c2.id FROM property_categories c2
                                                 WHERE c2.id = :category OR c2.parent_id = :category_family)';
            } else {
                $sql .= ' AND p.category_id = :category';
            }
        }
        foreach (['city' => 'city_id', 'commune' => 'commune_id', 'district' => 'district_id'] as $level => $column) {
            if ($criteria->{$level} !== null) {
                $sql .= " AND p.{$column} = :{$level}";
                $params[$level] = $criteria->{$level}['id'];
            }
        }

        foreach ([
            'price >= :price_min' => ['price_min', $criteria->priceMin],
            'price <= :price_max' => ['price_max', $criteria->priceMax],
            'living_area >= :area_min' => ['area_min', $criteria->areaMin],
            'land_area >= :land_min' => ['land_min', $criteria->landMin],
            'rooms >= :rooms' => ['rooms', $criteria->rooms],
            'bedrooms >= :bedrooms' => ['bedrooms', $criteria->bedrooms],
            'bathrooms >= :bathrooms' => ['bathrooms', $criteria->bathrooms],
        ] as $condition => [$name, $value]) {
            if ($value !== null) {
                $sql .= " AND p.{$condition}";
                $params[$name] = $value;
            }
        }

        foreach (array_keys($criteria->features) as $index => $code) {
            $sql .= " AND EXISTS (SELECT 1 FROM property_features pf JOIN features f2 ON f2.id = pf.feature_id
                                   WHERE pf.property_id = p.id AND f2.code = :feature{$index})";
            $params["feature{$index}"] = $code;
        }

        $index = 0;
        foreach ($criteria->attributes as $attribute) {
            $params["attr{$index}"] = $attribute['id'];
            if (array_key_exists('1', $attribute['values']) && count($attribute['values']) === 1) {
                $sql .= " AND EXISTS (SELECT 1 FROM property_attribute_values v WHERE v.property_id = p.id
                                       AND v.attribute_id = :attr{$index} AND v.value_boolean = 1)";
            } else {
                $codes = [];
                foreach (array_keys($attribute['values']) as $position => $code) {
                    $codes[] = ":attr{$index}_{$position}";
                    $params["attr{$index}_{$position}"] = $code;
                }
                $sql .= " AND EXISTS (SELECT 1 FROM property_attribute_values v
                                        JOIN property_attribute_options o ON o.id = v.value_option_id
                                       WHERE v.property_id = p.id AND v.attribute_id = :attr{$index}
                                         AND o.code IN (" . implode(', ', $codes) . '))';
            }
            $index++;
        }

        return $sql . $this->keywordCondition($criteria->keyword, $params);
    }

    /**
     * Mot-clé : recherche plein texte sur le titre et la description, ou référence exacte.
     * Les mots trop courts pour l'index plein texte retombent sur un LIKE sur le titre.
     *
     * @param array<string, mixed> $params
     */
    private function keywordCondition(string $keyword, array &$params): string
    {
        if ($keyword === '') {
            return '';
        }

        $params['keyword_reference'] = $keyword;

        $tokens = [];
        foreach (preg_split('/[^\p{L}\p{N}]+/u', $keyword, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (mb_strlen($word) >= self::FULLTEXT_MIN_WORD) {
                $tokens[] = '+' . $word . '*';
            }
        }

        if ($tokens === []) {
            $params['keyword_like'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $keyword) . '%';

            return ' AND (p.title LIKE :keyword_like OR p.reference = :keyword_reference)';
        }

        $params['keyword'] = implode(' ', $tokens);

        return ' AND (MATCH (p.title, p.description) AGAINST (:keyword IN BOOLEAN MODE) OR p.reference = :keyword_reference)';
    }

    /** Ordre SQL d'un tri de la page de résultats (valeur déjà validée par SearchFilters). */
    private function order(string $sort): string
    {
        return match ($sort) {
            'prix-asc' => 'p.price IS NULL, p.price ASC, p.id DESC',
            'prix-desc' => 'p.price DESC, p.id DESC',
            'surface-desc' => 'COALESCE(p.living_area, p.land_area) DESC, p.id DESC',
            // Par défaut, les annonces mises en avant (et non expirées) ouvrent la liste.
            default => '(p.is_featured = 1 AND (p.featured_until IS NULL OR p.featured_until > UTC_TIMESTAMP())) DESC,
                        p.published_at DESC, p.id DESC',
        };
    }

    /** Annonces en ligne du pays : condition commune à toutes les requêtes publiques. */
    private function publishedScope(string $countryParam = ':country'): string
    {
        return "p.country_id = {$countryParam} AND p.status = 'published' AND p.deleted_at IS NULL";
    }
}
