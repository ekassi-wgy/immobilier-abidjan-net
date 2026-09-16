<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Support\Str;

/**
 * Traduction d'une URL publique en critères de recherche, et données du panneau de filtres.
 *
 * URL : /{transaction}[/{type}][/{ville}[/{commune}[/{quartier}]]]
 * Les segments qui suivent la transaction sont résolus dans l'ordre : type de bien (uniquement en
 * première position), puis ville, commune et quartier. Un segment inconnu répond 404 — une URL de
 * résultats doit être soit valide, soit absente de l'index.
 *
 * Toutes les lectures sont limitées au pays du site courant.
 */
final class SearchFilters
{
    /** Bornes de saisie : évite qu'un paramètre forgé produise une requête absurde. */
    private const MAX_PRICE = 100_000_000_000.0;
    private const MAX_AREA = 10_000_000.0;
    private const MAX_COUNT = 99;
    private const MAX_KEYWORD = 80;

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param list<string>         $segments Segments d'URL après la transaction
     * @param array<string, mixed> $query    Chaîne de requête brute
     *
     * @throws HttpException 404 si la transaction ou un segment de localisation est inconnu
     */
    public function resolve(string $transactionSlug, array $segments, array $query, int $countryId): SearchCriteria
    {
        $transaction = $this->transaction($transactionSlug) ?? throw new HttpException(404);

        [$category, $city, $commune, $district] = $this->resolveSegments($segments, $countryId);

        $attributes = $this->selectedAttributes($query, $category['id'] ?? null);

        return new SearchCriteria(
            transaction: $transaction,
            category: $category,
            city: $city,
            commune: $commune,
            district: $district,
            keyword: $this->keyword($query['q'] ?? null),
            priceMin: $this->decimal($query['prix_min'] ?? null, self::MAX_PRICE),
            priceMax: $this->decimal($query['prix_max'] ?? null, self::MAX_PRICE),
            areaMin: $this->decimal($query['surface_min'] ?? null, self::MAX_AREA),
            landMin: $this->decimal($query['terrain_min'] ?? null, self::MAX_AREA),
            rooms: $this->count($query['pieces'] ?? null),
            bedrooms: $this->count($query['chambres'] ?? null),
            bathrooms: $this->count($query['sdb'] ?? null),
            features: $this->selectedFeatures($query['equipements'] ?? null),
            attributes: $attributes,
            sort: in_array($query['tri'] ?? '', SearchCriteria::SORTS, true) ? (string) $query['tri'] : SearchCriteria::DEFAULT_SORT,
            view: in_array($query['vue'] ?? '', SearchCriteria::VIEWS, true) ? (string) $query['vue'] : SearchCriteria::DEFAULT_VIEW,
            page: max(1, (int) ($query['page'] ?? 1)),
        );
    }

    /**
     * Transaction active dont le slug est le premier segment de l'URL.
     *
     * @return array{id: int, slug: string, name: string, period: string}|null
     */
    public function transaction(string $slug): ?array
    {
        $row = $this->db->selectOne(
            'SELECT id, slug, name, name_translations, default_price_period
             FROM transaction_types WHERE slug = :slug AND is_active = 1',
            ['slug' => $slug]
        );

        return $row === null ? null : [
            'id' => (int) $row['id'],
            'slug' => (string) $row['slug'],
            'name' => $this->localized($row['name'], $row['name_translations']),
            'period' => (string) $row['default_price_period'],
        ];
    }

    /** Paramètres de la recherche rapide et du panneau de filtres qui deviennent des segments d'URL. */
    public const PATH_PARAMS = ['type', 'ville', 'commune', 'quartier', 'lieu'];

    /**
     * URL canonique d'une recherche soumise par un formulaire : les choix de type de bien et de
     * localisation deviennent des segments de chemin, le reste des filtres reste en chaîne de requête.
     * Une saisie libre qui ne correspond à aucun lieu connu devient le mot-clé `q`.
     *
     * @param array<string, mixed> $query
     * @return array{0: string, 1: array<string, mixed>} Chemin et chaîne de requête restante
     */
    public function canonical(string $transactionSlug, array $query, int $countryId): array
    {
        $category = ($slug = $this->string($query['type'] ?? null)) !== '' ? $this->category($slug, $countryId) : null;

        $city = ($slug = $this->string($query['ville'] ?? null)) !== '' ? $this->city($slug, $countryId) : null;
        $commune = $city !== null && ($slug = $this->string($query['commune'] ?? null)) !== ''
            ? $this->commune($slug, $city['id']) : null;
        $district = $commune !== null && ($slug = $this->string($query['quartier'] ?? null)) !== ''
            ? $this->district($slug, $commune['id']) : null;

        $lieu = trim($this->string($query['lieu'] ?? null));
        if ($city === null && $lieu !== '') {
            [$city, $commune, $district] = $this->findPlace($lieu, $countryId);
            if ($city === null) {
                $query['q'] = $lieu;
            }
        }

        foreach (self::PATH_PARAMS as $param) {
            unset($query[$param]);
        }
        unset($query['page']);

        $segments = [$transactionSlug];
        foreach ([$category, $city, $commune, $district] as $level) {
            if ($level !== null) {
                $segments[] = $level['slug'];
            }
        }

        return [implode('/', $segments), $query];
    }

    /**
     * Saisie libre de localisation : ville, puis commune, puis quartier — par slug ou par nom exact.
     *
     * @return array{0: ?array, 1: ?array, 2: ?array} Ville, commune et quartier retenus
     */
    private function findPlace(string $lieu, int $countryId): array
    {
        $slug = Str::slug($lieu);
        if ($slug === '') {
            return [null, null, null];
        }
        $params = ['slug' => $slug, 'name' => $lieu, 'country' => $countryId];

        $city = $this->db->selectOne(
            'SELECT id, slug, name FROM cities
             WHERE country_id = :country AND is_active = 1 AND (slug = :slug OR name = :name) LIMIT 1',
            $params
        );
        if ($city !== null) {
            return [$this->row($city), null, null];
        }

        $commune = $this->db->selectOne(
            'SELECT m.id, m.slug, m.name, ci.id AS city_id, ci.slug AS city_slug, ci.name AS city_name
             FROM communes m JOIN cities ci ON ci.id = m.city_id
             WHERE ci.country_id = :country AND m.is_active = 1 AND ci.is_active = 1
               AND (m.slug = :slug OR m.name = :name) LIMIT 1',
            $params
        );
        if ($commune !== null) {
            return [$this->parent($commune, 'city'), $this->row($commune), null];
        }

        $district = $this->db->selectOne(
            'SELECT d.id, d.slug, d.name,
                    m.id AS commune_id, m.slug AS commune_slug, m.name AS commune_name,
                    ci.id AS city_id, ci.slug AS city_slug, ci.name AS city_name
             FROM districts d
             JOIN communes m ON m.id = d.commune_id
             JOIN cities ci ON ci.id = m.city_id
             WHERE ci.country_id = :country AND d.is_active = 1 AND m.is_active = 1 AND ci.is_active = 1
               AND (d.slug = :slug OR d.name = :name) LIMIT 1',
            $params
        );
        if ($district !== null) {
            return [$this->parent($district, 'city'), $this->parent($district, 'commune'), $this->row($district)];
        }

        return [null, null, null];
    }

    /** @return array{id: int, slug: string, name: string} */
    private function row(array $row): array
    {
        return ['id' => (int) $row['id'], 'slug' => (string) $row['slug'], 'name' => (string) $row['name']];
    }

    /** @return array{id: int, slug: string, name: string} */
    private function parent(array $row, string $prefix): array
    {
        return [
            'id' => (int) $row[$prefix . '_id'],
            'slug' => (string) $row[$prefix . '_slug'],
            'name' => (string) $row[$prefix . '_name'],
        ];
    }

    /**
     * Données du panneau de filtres, pour la recherche courante.
     *
     * @return array{cities: list<array{slug: string, name: string}>,
     *               communes: list<array{slug: string, name: string}>,
     *               districts: list<array{slug: string, name: string}>,
     *               types: array<string, array<string, string>>,
     *               features: array<string, array<string, string>>,
     *               attributes: list<array<string, mixed>>}
     */
    public function panel(SearchCriteria $criteria, int $countryId): array
    {
        return [
            'cities' => $this->cityChoices($countryId),
            'communes' => $criteria->city !== null ? $this->communeChoices($criteria->city['id']) : [],
            'districts' => $criteria->commune !== null ? $this->districtChoices($criteria->commune['id']) : [],
            'types' => $this->typeChoices($countryId, $criteria->transaction['id']),
            'features' => $this->featureChoices(),
            'attributes' => $this->attributeChoices($criteria->category['id'] ?? null),
        ];
    }

    /**
     * Segments de localisation et type de bien.
     *
     * @param list<string> $segments
     * @return array{0: ?array, 1: ?array, 2: ?array, 3: ?array}
     */
    private function resolveSegments(array $segments, int $countryId): array
    {
        $category = $city = $commune = $district = null;

        foreach ($segments as $index => $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                throw new HttpException(404);
            }

            if ($index === 0 && ($found = $this->category($segment, $countryId)) !== null) {
                $category = $found;
                continue;
            }
            if ($city === null && ($found = $this->city($segment, $countryId)) !== null) {
                $city = $found;
                continue;
            }
            if ($city !== null && $commune === null && ($found = $this->commune($segment, $city['id'])) !== null) {
                $commune = $found;
                continue;
            }
            if ($commune !== null && $district === null && ($found = $this->district($segment, $commune['id'])) !== null) {
                $district = $found;
                continue;
            }

            throw new HttpException(404);
        }

        return [$category, $city, $commune, $district];
    }

    /** @return array{id: int, slug: string, name: string, plural: string, family: bool}|null */
    private function category(string $slug, int $countryId): ?array
    {
        $row = $this->db->selectOne(
            'SELECT id, slug, name, name_plural, name_translations, parent_id
             FROM property_categories
             WHERE slug = :slug AND is_active = 1 AND (country_id IS NULL OR country_id = :country)',
            ['slug' => $slug, 'country' => $countryId]
        );
        if ($row === null) {
            return null;
        }
        $name = $this->localized($row['name'], $row['name_translations']);

        return [
            'id' => (int) $row['id'],
            'slug' => (string) $row['slug'],
            'name' => $name,
            'plural' => (string) ($row['name_plural'] ?? '') !== '' ? (string) $row['name_plural'] : $name,
            'family' => $row['parent_id'] === null,
        ];
    }

    /** @return array{id: int, slug: string, name: string}|null */
    private function city(string $slug, int $countryId): ?array
    {
        return $this->level('SELECT id, slug, name FROM cities WHERE slug = :slug AND country_id = :parent AND is_active = 1', $slug, $countryId);
    }

    /** @return array{id: int, slug: string, name: string}|null */
    private function commune(string $slug, int $cityId): ?array
    {
        return $this->level('SELECT id, slug, name FROM communes WHERE slug = :slug AND city_id = :parent AND is_active = 1', $slug, $cityId);
    }

    /** @return array{id: int, slug: string, name: string}|null */
    private function district(string $slug, int $communeId): ?array
    {
        return $this->level('SELECT id, slug, name FROM districts WHERE slug = :slug AND commune_id = :parent AND is_active = 1', $slug, $communeId);
    }

    /** @return array{id: int, slug: string, name: string}|null */
    private function level(string $sql, string $slug, int $parentId): ?array
    {
        $row = $this->db->selectOne($sql, ['slug' => $slug, 'parent' => $parentId]);

        return $row === null ? null : ['id' => (int) $row['id'], 'slug' => (string) $row['slug'], 'name' => (string) $row['name']];
    }

    /** @return list<array{slug: string, name: string}> */
    private function cityChoices(int $countryId): array
    {
        return $this->choices(
            'SELECT slug, name FROM cities WHERE country_id = :parent AND is_active = 1 ORDER BY sort_order, name',
            $countryId
        );
    }

    /** @return list<array{slug: string, name: string}> */
    private function communeChoices(int $cityId): array
    {
        return $this->choices(
            'SELECT slug, name FROM communes WHERE city_id = :parent AND is_active = 1 ORDER BY sort_order, name',
            $cityId
        );
    }

    /** @return list<array{slug: string, name: string}> */
    private function districtChoices(int $communeId): array
    {
        return $this->choices(
            'SELECT slug, name FROM districts WHERE commune_id = :parent AND is_active = 1 ORDER BY sort_order, name',
            $communeId
        );
    }

    /** @return list<array{slug: string, name: string}> */
    private function choices(string $sql, int $parentId): array
    {
        return array_map(
            static fn (array $row): array => ['slug' => (string) $row['slug'], 'name' => (string) $row['name']],
            $this->db->select($sql, ['parent' => $parentId])
        );
    }

    /**
     * Types de biens proposés pour la transaction courante (familles en optgroup).
     * Une sous-catégorie sans transaction déclarée reprend celles de sa famille.
     *
     * @return array<string, array<string, string>>
     */
    private function typeChoices(int $countryId, int $transactionId): array
    {
        $rows = $this->db->select(
            'SELECT c.slug, c.name, c.name_translations, f.name AS family_name, f.name_translations AS family_translations
             FROM property_categories c
             JOIN property_categories f ON f.id = c.parent_id
             WHERE c.is_active = 1 AND f.is_active = 1
               AND (c.country_id IS NULL OR c.country_id = :country)
               AND (
                 EXISTS (SELECT 1 FROM category_transaction_types ct WHERE ct.category_id = c.id AND ct.transaction_type_id = :transaction)
                 OR (NOT EXISTS (SELECT 1 FROM category_transaction_types ct WHERE ct.category_id = c.id)
                     AND EXISTS (SELECT 1 FROM category_transaction_types ct WHERE ct.category_id = f.id AND ct.transaction_type_id = :transaction2))
               )
             ORDER BY f.sort_order, c.sort_order, c.name',
            ['country' => $countryId, 'transaction' => $transactionId, 'transaction2' => $transactionId]
        );

        $groups = [];
        foreach ($rows as $row) {
            $family = $this->localized($row['family_name'], $row['family_translations']);
            $groups[$family][(string) $row['slug']] = $this->localized($row['name'], $row['name_translations']);
        }

        return $groups;
    }

    /**
     * Équipements filtrables, groupés par famille.
     *
     * @return array<string, array<string, string>>
     */
    private function featureChoices(): array
    {
        $rows = $this->db->select(
            'SELECT code, name, name_translations, feature_group
             FROM features WHERE is_active = 1 AND is_filterable = 1 ORDER BY feature_group, sort_order, name'
        );

        $groups = [];
        foreach ($rows as $row) {
            $groups[(string) $row['feature_group']][(string) $row['code']] = $this->localized($row['name'], $row['name_translations']);
        }

        return $groups;
    }

    /**
     * Critères dynamiques filtrables : ceux de la catégorie choisie (et de sa famille),
     * sinon tous les critères filtrables du catalogue.
     *
     * Les critères « colonne » (surface, pièces, chambres…) ont leurs propres champs dans le panneau.
     *
     * @return list<array{id: int, code: string, label: string, type: string, options: array<string, string>}>
     */
    private function attributeChoices(?int $categoryId): array
    {
        $params = [];
        $scope = '';
        if ($categoryId !== null) {
            $scope = 'AND a.id IN (
                SELECT ca.attribute_id FROM category_attributes ca
                 WHERE ca.category_id = :category
                    OR ca.category_id = (SELECT c.parent_id FROM property_categories c WHERE c.id = :category2)
            )';
            $params = ['category' => $categoryId, 'category2' => $categoryId];
        }

        $rows = $this->db->select(
            "SELECT a.id, a.code, a.name, a.name_translations, a.input_type
             FROM property_attributes a
             WHERE a.is_active = 1 AND a.is_filterable = 1
               AND a.input_type IN ('select', 'multiselect', 'boolean')
               {$scope}
             ORDER BY a.sort_order, a.name",
            $params
        );

        $attributes = [];
        foreach ($rows as $row) {
            $attributes[] = [
                'id' => (int) $row['id'],
                'code' => (string) $row['code'],
                'label' => $this->localized($row['name'], $row['name_translations']),
                'type' => (string) $row['input_type'],
                'options' => $row['input_type'] === 'boolean' ? [] : $this->options((int) $row['id']),
            ];
        }

        return $attributes;
    }

    /** @return array<string, string> [code => libellé] */
    private function options(int $attributeId): array
    {
        $options = [];
        foreach ($this->db->select(
            'SELECT code, label, label_translations FROM property_attribute_options
             WHERE attribute_id = :attribute AND is_active = 1 ORDER BY sort_order, label',
            ['attribute' => $attributeId]
        ) as $row) {
            $options[(string) $row['code']] = $this->localized($row['label'], $row['label_translations']);
        }

        return $options;
    }

    /**
     * Équipements cochés : seuls les codes réellement filtrables sont retenus.
     *
     * @return array<string, string> [code => libellé]
     */
    private function selectedFeatures(mixed $codes): array
    {
        $codes = $this->codeList($codes);
        if ($codes === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($codes as $index => $code) {
            $placeholders[] = ":f{$index}";
            $params["f{$index}"] = $code;
        }

        $selected = [];
        foreach ($this->db->select(
            'SELECT code, name, name_translations FROM features
             WHERE is_active = 1 AND is_filterable = 1 AND code IN (' . implode(', ', $placeholders) . ')
             ORDER BY sort_order, name',
            $params
        ) as $row) {
            $selected[(string) $row['code']] = $this->localized($row['name'], $row['name_translations']);
        }

        return $selected;
    }

    /**
     * Critères dynamiques cochés (`?c_standing[]=haut-standing`) : attribut et options vérifiés en base.
     *
     * @param array<string, mixed> $query
     * @return array<string, array{id: int, label: string, values: array<string, string>}>
     */
    private function selectedAttributes(array $query, ?int $categoryId): array
    {
        $wanted = [];
        foreach ($query as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'c_')) {
                $codes = $this->codeList($value);
                if ($codes !== []) {
                    $wanted[substr($key, 2)] = $codes;
                }
            }
        }
        if ($wanted === []) {
            return [];
        }

        $selected = [];
        foreach ($this->attributeChoices($categoryId) as $attribute) {
            $codes = $wanted[$attribute['code']] ?? null;
            if ($codes === null) {
                continue;
            }

            $values = [];
            if ($attribute['type'] === 'boolean') {
                if (in_array('1', $codes, true)) {
                    $values['1'] = $attribute['label'];
                }
            } else {
                foreach ($codes as $code) {
                    if (isset($attribute['options'][$code])) {
                        $values[$code] = $attribute['options'][$code];
                    }
                }
            }

            if ($values !== []) {
                $selected[$attribute['code']] = ['id' => $attribute['id'], 'label' => $attribute['label'], 'values' => $values];
            }
        }

        return $selected;
    }

    /**
     * Liste de codes issue de la chaîne de requête (tableau ou valeur unique).
     *
     * @return list<string>
     */
    private function codeList(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $codes = [];
        foreach ($values as $item) {
            if (!is_string($item) && !is_int($item)) {
                continue;
            }
            $code = trim((string) $item);
            if ($code !== '' && preg_match('/^[a-z0-9_-]{1,60}$/i', $code) === 1) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    private function keyword(mixed $value): string
    {
        $keyword = preg_replace('/\s+/u', ' ', trim($this->string($value)));

        return mb_substr((string) $keyword, 0, self::MAX_KEYWORD);
    }

    private function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : '';
    }

    private function decimal(mixed $value, float $max): ?float
    {
        $raw = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $this->string($value));
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }
        $number = (float) $raw;

        return $number > 0 ? min($number, $max) : null;
    }

    private function count(mixed $value): ?int
    {
        $number = filter_var($this->string($value), FILTER_VALIDATE_INT);

        return $number === false || $number <= 0 ? null : min($number, self::MAX_COUNT);
    }

    /** Libellé dans la langue du site, avec repli sur le libellé français du référentiel. */
    private function localized(mixed $name, mixed $translations): string
    {
        $locale = locale();
        if ($locale !== 'fr' && is_string($translations) && $translations !== '') {
            $decoded = json_decode($translations, true);
            if (is_array($decoded) && !empty($decoded[$locale])) {
                return (string) $decoded[$locale];
            }
        }

        return (string) $name;
    }
}
