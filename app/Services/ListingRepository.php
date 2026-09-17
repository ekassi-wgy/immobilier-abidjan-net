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
 *
 * Weblogy est l'intermédiaire exclusif : aucune requête publique ne lit l'identité ni les
 * coordonnées de l'agence partenaire, de son agent ou le contact propre à l'annonce. Le
 * rattachement (`p.agency_id`) n'est lu que pour enregistrer une demande côté back-office.
 */
final class ListingRepository
{
    /** Longueur minimale d'un mot indexé par InnoDB (innodb_ft_min_token_size). */
    private const FULLTEXT_MIN_WORD = 3;

    /** Colonnes nécessaires à une carte annonce (jamais SELECT *). */
    private const CARD_COLUMNS = "p.id, p.reference, p.title, p.slug, p.price, p.currency_code, p.price_period,
        p.living_area, p.land_area, p.rooms, p.bedrooms, p.bathrooms, p.is_featured, p.published_at,
        c.name AS category_name, t.name AS transaction_name, t.slug AS transaction_slug,
        ci.name AS city_name, m.name AS commune_name, d.name AS district_name,
        (SELECT i.path FROM property_images i WHERE i.property_id = p.id AND i.revision_id IS NULL ORDER BY i.sort_order, i.id LIMIT 1) AS cover_path,
        (SELECT i.alt_text FROM property_images i WHERE i.property_id = p.id AND i.revision_id IS NULL ORDER BY i.sort_order, i.id LIMIT 1) AS cover_alt,
        (SELECT COUNT(*) FROM property_images i WHERE i.property_id = p.id AND i.revision_id IS NULL) AS photos_count";

    private const CARD_JOINS = "FROM properties p
        JOIN property_categories c ON c.id = p.category_id
        JOIN transaction_types t ON t.id = p.transaction_type_id
        JOIN cities ci ON ci.id = p.city_id
        LEFT JOIN communes m ON m.id = p.commune_id
        LEFT JOIN districts d ON d.id = p.district_id";

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

        // En deux temps volontairement : l'ordre chronologique se lit directement dans
        // `idx_properties_published` tant que la requête ne porte que sur `properties`. Avec les
        // jointures de la carte dans le même SELECT, l'optimiseur change d'ordre de jointure et
        // trie toutes les annonces en ligne du pays (37 ms contre 1 ms sur 10 000 annonces).
        $ids = array_map(
            static fn (array $row): int => (int) $row['id'],
            $this->db->select(
                'SELECT p.id FROM properties p
                 WHERE ' . $this->publishedScope() . ($exclude !== '' ? " AND p.id NOT IN ({$exclude})" : '') . '
                 ORDER BY p.published_at DESC, p.id DESC
                 LIMIT :limit',
                $params
            )
        );

        return $this->cardsByIds($countryId, $ids);
    }

    /**
     * Cartes annonce d'une liste d'identifiants, dans l'ordre reçu.
     *
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    private function cardsByIds(int $countryId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = ['country' => $countryId];
        foreach ($ids as $index => $id) {
            $placeholders[] = ":i{$index}";
            $params["i{$index}"] = $id;
        }

        $rows = [];
        foreach ($this->db->select(
            'SELECT ' . self::CARD_COLUMNS . ' ' . self::CARD_JOINS . '
             WHERE ' . $this->publishedScope() . ' AND p.id IN (' . implode(', ', $placeholders) . ')',
            $params
        ) as $row) {
            $rows[(int) $row['id']] = $row;
        }

        return array_values(array_intersect_key($rows, array_flip($ids)));
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
            "SELECT a.id, a.name, a.logo_path, a.partner_type, a.is_verified, ci.name AS city_name, m.name AS commune_name
             FROM agencies a
             LEFT JOIN cities ci ON ci.id = a.city_id
             LEFT JOIN communes m ON m.id = a.commune_id
             WHERE a.country_id = :country AND a.status = 'active' AND a.deleted_at IS NULL AND a.is_featured = 1
             ORDER BY a.is_verified DESC, a.name
             LIMIT :limit",
            ['country' => $countryId, 'limit' => $limit]
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
     * Fiche publique d'une annonce. `property_private_details` (notaire, référence de dossier)
     * n'est jamais joint, et `internal_reference` comme les compteurs internes restent en base.
     *
     * @return array<string, mixed>|null
     */
    public function findPublished(int $id, int $countryId): ?array
    {
        return $this->db->selectOne(
            "SELECT p.id, p.reference, p.title, p.slug, p.description, p.source,
                    p.price, p.currency_code, p.price_period, p.is_negotiable, p.charges, p.agency_fee_percent,
                    p.address, p.latitude, p.longitude, p.show_exact_location,
                    p.living_area, p.land_area, p.rooms, p.bedrooms, p.bathrooms,
                    p.availability, p.available_from, p.video_url, p.virtual_tour_url, p.document_path,
                    p.published_at, p.updated_at, p.is_featured, p.featured_until,
                    p.meta_title, p.meta_description,
                    p.category_id, p.transaction_type_id, p.city_id, p.commune_id, p.district_id,
                    p.agency_id,
                    c.name AS category_name, c.slug AS category_slug, c.parent_id AS category_parent_id,
                    f.name AS family_name, f.slug AS family_slug,
                    t.name AS transaction_name, t.slug AS transaction_slug,
                    ci.name AS city_name, ci.slug AS city_slug,
                    m.name AS commune_name, m.slug AS commune_slug,
                    d.name AS district_name, d.slug AS district_slug
             FROM properties p
             JOIN property_categories c ON c.id = p.category_id
             LEFT JOIN property_categories f ON f.id = c.parent_id
             JOIN transaction_types t ON t.id = p.transaction_type_id
             JOIN cities ci ON ci.id = p.city_id
             LEFT JOIN communes m ON m.id = p.commune_id
             LEFT JOIN districts d ON d.id = p.district_id
             WHERE p.id = :id AND " . $this->publishedScope(),
            ['id' => $id, 'country' => $countryId]
        );
    }

    /**
     * Galerie publique : une photo de révision en attente (`revision_id`) n'apparaît jamais.
     *
     * @return list<array<string, mixed>>
     */
    public function publicImages(int $propertyId): array
    {
        return $this->db->select(
            'SELECT path, alt_text, width, height FROM property_images
             WHERE property_id = :id AND revision_id IS NULL ORDER BY sort_order, id',
            ['id' => $propertyId]
        );
    }

    /**
     * Critères affichés sur la fiche : ceux rangés dans une colonne indexée de `properties`
     * (surface, pièces…) et ceux de `property_attribute_values`, tous limités aux critères
     * publics (`is_public`) de la catégorie et de sa famille. Un multi-choix produit une ligne
     * par option : le présentateur les regroupe.
     *
     * @param array<string, mixed> $property Ligne de findPublished(), pour les valeurs en colonne
     * @return list<array<string, mixed>>
     */
    public function criteriaRows(int $propertyId, int $categoryId, ?int $parentId, array $property): array
    {
        $scope = array_values(array_filter([$categoryId, $parentId]));
        $placeholders = implode(', ', array_map(static fn (int $i): string => ":cat{$i}", array_keys($scope)));
        $params = [];
        foreach ($scope as $index => $id) {
            $params["cat{$index}"] = $id;
        }

        $columns = "a.id, a.code, a.name, a.name_translations, a.input_type, a.unit, a.storage, a.column_name,
                    g.code AS group_code, g.name AS group_name, g.name_translations AS group_translations,
                    g.sort_order AS group_sort, a.sort_order";
        $joins = "FROM property_attributes a
                  JOIN attribute_groups g ON g.id = a.group_id
                  WHERE a.is_active = 1 AND a.is_public = 1";

        // Critères rangés dans une colonne : leur valeur est déjà dans la ligne de l'annonce.
        $rows = [];
        foreach ($this->db->select(
            "SELECT {$columns} {$joins} AND a.storage = 'column'
               AND a.id IN (SELECT ca.attribute_id FROM category_attributes ca WHERE ca.category_id IN ({$placeholders}))
             ORDER BY g.sort_order, a.sort_order",
            $params
        ) as $row) {
            $value = $property[(string) $row['column_name']] ?? null;
            if ($value !== null && $value !== '' && (float) $value > 0) {
                $rows[] = $row + ['value' => $value, 'option_label' => null, 'option_translations' => null];
            }
        }

        // Critères EAV : une ligne par valeur enregistrée.
        foreach ($this->db->select(
            "SELECT {$columns}, v.value_integer, v.value_decimal, v.value_text, v.value_boolean, v.value_date,
                    o.label AS option_label, o.label_translations AS option_translations, o.sort_order AS option_sort
             FROM property_attribute_values v
             JOIN property_attributes a ON a.id = v.attribute_id
             JOIN attribute_groups g ON g.id = a.group_id
             LEFT JOIN property_attribute_options o ON o.id = v.value_option_id
             WHERE v.property_id = :property AND a.is_active = 1 AND a.is_public = 1
             ORDER BY g.sort_order, a.sort_order, o.sort_order",
            ['property' => $propertyId]
        ) as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Équipements de l'annonce, groupés par famille par le présentateur.
     *
     * @return list<array<string, mixed>>
     */
    public function publicFeatures(int $propertyId): array
    {
        return $this->db->select(
            'SELECT f.code, f.name, f.name_translations, f.feature_group, f.icon
             FROM property_features pf
             JOIN features f ON f.id = pf.feature_id AND f.is_active = 1
             WHERE pf.property_id = :id
             ORDER BY f.feature_group, f.sort_order, f.name',
            ['id' => $propertyId]
        );
    }

    /**
     * Biens similaires : même transaction, même famille de catégorie, même ville, prix proche.
     * Les plus proches géographiquement (commune identique) remontent en premier.
     *
     * @param array<string, mixed> $property Ligne de findPublished()
     * @return list<array<string, mixed>>
     */
    public function similar(array $property, int $countryId, int $limit = 3): array
    {
        $familyId = $property['category_parent_id'] !== null ? (int) $property['category_parent_id'] : (int) $property['category_id'];
        $price = $property['price'] !== null ? (float) $property['price'] : null;

        $params = [
            'country' => $countryId,
            'self' => (int) $property['id'],
            'family' => $familyId,
            'family2' => $familyId,
            'transaction' => (int) $property['transaction_type_id'],
            'city' => (int) $property['city_id'],
            'commune' => (int) ($property['commune_id'] ?? 0),
            'limit' => $limit,
        ];

        $priceScope = '';
        if ($price !== null) {
            $priceScope = ' AND p.price BETWEEN :price_low AND :price_high';
            $params['price_low'] = $price * 0.6;
            $params['price_high'] = $price * 1.6;
        }

        return $this->db->select(
            'SELECT ' . self::CARD_COLUMNS . ' ' . self::CARD_JOINS . '
             WHERE ' . $this->publishedScope() . '
               AND p.id <> :self
               AND p.transaction_type_id = :transaction
               AND p.city_id = :city
               AND p.category_id IN (SELECT c2.id FROM property_categories c2 WHERE c2.id = :family OR c2.parent_id = :family2)'
               . $priceScope . '
             ORDER BY (p.commune_id = :commune) DESC, p.published_at DESC
             LIMIT :limit',
            $params
        );
    }

    /**
     * Une consultation de plus. Le compteur dénormalisé de l'annonce et la statistique du jour
     * (tableaux de bord des lots 1.7 et 1.12) sont mis à jour ensemble.
     */
    public function recordView(int $propertyId): void
    {
        $this->db->execute('UPDATE properties SET views_count = views_count + 1 WHERE id = :id', ['id' => $propertyId]);
        $this->db->execute(
            'INSERT INTO property_stats_daily (property_id, stat_date, views) VALUES (:id, UTC_DATE(), 1)
             ON DUPLICATE KEY UPDATE views = views + 1',
            ['id' => $propertyId]
        );
    }

    /** Une demande de contact de plus sur l'annonce (compteur et statistique du jour). */
    public function recordLead(int $propertyId): void
    {
        $this->db->execute('UPDATE properties SET leads_count = leads_count + 1 WHERE id = :id', ['id' => $propertyId]);
        $this->db->execute(
            'INSERT INTO property_stats_daily (property_id, stat_date, leads) VALUES (:id, UTC_DATE(), 1)
             ON DUPLICATE KEY UPDATE leads = leads + 1',
            ['id' => $propertyId]
        );
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

    /**
     * Annuaire public des agences partenaires (lot 1.11). Seules les agences actives et non
     * supprimées y figurent ; le nombre d'annonces est recompté, jamais lu dans le compteur
     * dénormalisé, pour rester juste après une expiration ou un archivage.
     *
     * @param array{q?: string, ville?: string, commune?: string, verifiee?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function agencies(int $countryId, array $filters, int $limit, int $offset): array
    {
        $params = ['country' => $countryId];
        $where = $this->agencyScope($filters, $params);

        return $this->db->select(
            "SELECT a.id, a.name, a.logo_path, a.partner_type, a.is_verified, ci.name AS city_name, m.name AS commune_name
             {$where}
             ORDER BY a.is_featured DESC, a.is_verified DESC, a.name
             LIMIT :limit OFFSET :offset",
            $params + ['limit' => $limit, 'offset' => $offset]
        );
    }

    /** Nombre d'agences de l'annuaire correspondant aux filtres. */
    public function countAgencies(int $countryId, array $filters): int
    {
        $params = ['country' => $countryId];
        $where = $this->agencyScope($filters, $params);

        return (int) $this->db->scalar("SELECT COUNT(*) {$where}", $params);
    }

    /**
     * Clause FROM/WHERE commune à l'annuaire. Une ville ou une commune correspond soit à
     * l'implantation de l'agence, soit à l'une de ses zones de couverture déclarées.
     *
     * @param array<string, mixed> $params Complété par référence
     */
    private function agencyScope(array $filters, array &$params): string
    {
        $where = "a.country_id = :country AND a.status = 'active' AND a.deleted_at IS NULL";

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND a.name LIKE :q';
            $params['q'] = '%' . addcslashes($q, '%_\\') . '%';
        }
        if (!empty($filters['ville'])) {
            $where .= ' AND (ci.slug = :city OR EXISTS (SELECT 1 FROM agency_zones z JOIN communes zm ON zm.id = z.commune_id
                                JOIN cities zc ON zc.id = zm.city_id WHERE z.agency_id = a.id AND zc.slug = :city2))';
            $params['city'] = (string) $filters['ville'];
            $params['city2'] = (string) $filters['ville'];
        }
        if (!empty($filters['commune'])) {
            $where .= ' AND (m.slug = :commune OR EXISTS (SELECT 1 FROM agency_zones z2 JOIN communes zm2 ON zm2.id = z2.commune_id
                                WHERE z2.agency_id = a.id AND zm2.slug = :commune2))';
            $params['commune'] = (string) $filters['commune'];
            $params['commune2'] = (string) $filters['commune'];
        }
        if (($filters['verifiee'] ?? '') === 'oui') {
            $where .= ' AND a.is_verified = 1';
        }

        return "FROM agencies a
                LEFT JOIN cities ci ON ci.id = a.city_id
                LEFT JOIN communes m ON m.id = a.commune_id
                WHERE {$where}";
    }

    /**
     * Villes proposées en filtre de l'annuaire : celles où une agence active est implantée
     * ou déclare une zone de couverture.
     *
     * @return list<array{slug: string, name: string}>
     */
    public function agencyCities(int $countryId): array
    {
        return $this->db->select(
            // `sort_order` figure dans le SELECT : un ORDER BY sur une colonne absente est refusé
            // en présence de DISTINCT (ONLY_FULL_GROUP_BY).
            "SELECT DISTINCT ci.slug, ci.name, ci.sort_order
             FROM cities ci
             WHERE ci.country_id = :country AND ci.is_active = 1
               AND (EXISTS (SELECT 1 FROM agencies a WHERE a.city_id = ci.id AND a.status = 'active' AND a.deleted_at IS NULL)
                 OR EXISTS (SELECT 1 FROM agency_zones z JOIN communes m ON m.id = z.commune_id
                              JOIN agencies a2 ON a2.id = z.agency_id AND a2.status = 'active' AND a2.deleted_at IS NULL
                             WHERE m.city_id = ci.id))
             ORDER BY ci.sort_order, ci.name",
            ['country' => $countryId]
        );
    }

    /**
     * Annonces en ligne pour le sitemap (lot 2.1), avec leur date de dernière modification.
     * L'URL est composée par `ListingPresenter::url()` : une seule façon de l'écrire.
     *
     * @return list<array{id: int, slug: string, lastmod: ?string}>
     */
    public function sitemapProperties(int $countryId, int $limit = 20000): array
    {
        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'slug' => (string) $row['slug'],
                'lastmod' => $row['updated_at'] !== null ? (string) $row['updated_at'] : (string) $row['published_at'],
            ],
            $this->db->select(
                'SELECT p.id, p.slug, p.updated_at, p.published_at FROM properties p
                 WHERE ' . $this->publishedScope() . '
                 ORDER BY p.published_at DESC LIMIT :limit',
                ['country' => $countryId, 'limit' => $limit]
            )
        );
    }

    /**
     * Combinaisons transaction × type et transaction × ville qui portent au moins une annonce :
     * ce sont les seules pages de résultats qui méritent d'être proposées aux moteurs.
     *
     * @return list<string> Chemins relatifs
     */
    public function sitemapSearchPaths(int $countryId): array
    {
        $paths = [];

        foreach ($this->db->select(
            'SELECT DISTINCT t.slug AS transaction, c.slug AS category
             FROM properties p
             JOIN transaction_types t ON t.id = p.transaction_type_id AND t.is_active = 1
             JOIN property_categories c ON c.id = p.category_id AND c.is_active = 1
             WHERE ' . $this->publishedScope() . '
             ORDER BY t.slug, c.slug',
            ['country' => $countryId]
        ) as $row) {
            $paths[] = (string) $row['transaction'];
            $paths[] = $row['transaction'] . '/' . $row['category'];
        }

        foreach ($this->db->select(
            'SELECT DISTINCT t.slug AS transaction, ci.slug AS city, m.slug AS commune
             FROM properties p
             JOIN transaction_types t ON t.id = p.transaction_type_id AND t.is_active = 1
             JOIN cities ci ON ci.id = p.city_id AND ci.is_active = 1
             LEFT JOIN communes m ON m.id = p.commune_id AND m.is_active = 1
             WHERE ' . $this->publishedScope() . '
             ORDER BY t.slug, ci.slug, m.slug',
            ['country' => $countryId]
        ) as $row) {
            $paths[] = $row['transaction'] . '/' . $row['city'];
            if ($row['commune'] !== null) {
                $paths[] = $row['transaction'] . '/' . $row['city'] . '/' . $row['commune'];
            }
        }

        return array_values(array_unique($paths));
    }

    /** Annonces en ligne du pays : condition commune à toutes les requêtes publiques. */
    private function publishedScope(string $countryParam = ':country'): string
    {
        return "p.country_id = {$countryParam} AND p.status = 'published' AND p.deleted_at IS NULL";
    }
}
