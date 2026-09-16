<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Critères d'une recherche d'annonces : segments d'URL déjà résolus (transaction, type de bien,
 * ville, commune, quartier) et filtres de la chaîne de requête.
 *
 * Construit par `SearchFilters` (qui fait les lectures en base), consommé par `ListingRepository`
 * pour le SQL et par les vues pour l'URL canonique, les filtres actifs et les liens de tri.
 *
 * URL publique : /{transaction}[/{type}][/{ville}[/{commune}[/{quartier}]]]
 * Le reste passe en chaîne de requête (mot-clé, prix, surfaces, équipements, critères, tri, vue, page).
 */
final class SearchCriteria
{
    /** Tris proposés (clé d'URL => colonne SQL construite par ListingRepository). */
    public const SORTS = ['recent', 'prix-asc', 'prix-desc', 'surface-desc'];

    /** Présentations des résultats. */
    public const VIEWS = ['grille', 'liste', 'carte'];

    public const DEFAULT_SORT = 'recent';
    public const DEFAULT_VIEW = 'grille';

    /**
     * @param array{id: int, slug: string, name: string, period: string}         $transaction
     * @param array{id: int, slug: string, name: string, plural: string, family: bool}|null $category
     * @param array{id: int, slug: string, name: string}|null                    $city
     * @param array{id: int, slug: string, name: string}|null                    $commune
     * @param array{id: int, slug: string, name: string}|null                    $district
     * @param array<string, string>                                              $features   [code => libellé]
     * @param array<string, array{id: int, label: string, values: array<string, string>}> $attributes
     */
    public function __construct(
        public readonly array $transaction,
        public readonly ?array $category = null,
        public readonly ?array $city = null,
        public readonly ?array $commune = null,
        public readonly ?array $district = null,
        public readonly string $keyword = '',
        public readonly ?float $priceMin = null,
        public readonly ?float $priceMax = null,
        public readonly ?float $areaMin = null,
        public readonly ?float $landMin = null,
        public readonly ?int $rooms = null,
        public readonly ?int $bedrooms = null,
        public readonly ?int $bathrooms = null,
        public readonly array $features = [],
        public readonly array $attributes = [],
        public readonly string $sort = self::DEFAULT_SORT,
        public readonly string $view = self::DEFAULT_VIEW,
        public readonly int $page = 1,
    ) {
    }

    /** Chemin canonique, sans chaîne de requête ni préfixe d'installation. */
    public function path(): string
    {
        $segments = [$this->transaction['slug']];
        foreach ([$this->category, $this->city, $this->commune, $this->district] as $level) {
            if ($level !== null) {
                $segments[] = $level['slug'];
            }
        }

        return implode('/', $segments);
    }

    /**
     * Filtres portés par la chaîne de requête (les valeurs par défaut sont omises).
     *
     * @return array<string, string|list<string>>
     */
    public function queryParams(): array
    {
        $params = [];
        foreach ([
            'q' => $this->keyword,
            'prix_min' => $this->priceMin,
            'prix_max' => $this->priceMax,
            'surface_min' => $this->areaMin,
            'terrain_min' => $this->landMin,
            'pieces' => $this->rooms,
            'chambres' => $this->bedrooms,
            'sdb' => $this->bathrooms,
        ] as $key => $value) {
            if ($value !== null && $value !== '') {
                $params[$key] = is_float($value) ? self::number($value) : (string) $value;
            }
        }

        if ($this->features !== []) {
            $params['equipements'] = array_keys($this->features);
        }
        foreach ($this->attributes as $code => $attribute) {
            $params['c_' . $code] = array_keys($attribute['values']);
        }
        if ($this->sort !== self::DEFAULT_SORT) {
            $params['tri'] = $this->sort;
        }
        if ($this->view !== self::DEFAULT_VIEW) {
            $params['vue'] = $this->view;
        }
        if ($this->page > 1) {
            $params['page'] = (string) $this->page;
        }

        return $params;
    }

    /**
     * URL de la recherche courante, avec des paramètres remplacés (valeur `null` = paramètre retiré).
     * La page est remise à 1 dès qu'un autre paramètre change.
     *
     * @param array<string, string|list<string>|null> $override
     */
    public function url(array $override = []): string
    {
        $params = $this->queryParams();
        if ($override !== [] && !array_key_exists('page', $override)) {
            unset($params['page']);
        }
        foreach ($override as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                unset($params[$key]);
            } else {
                $params[$key] = $value;
            }
        }

        // « equipements[0]= » → « equipements[]= » : même écriture que celle produite par le
        // formulaire de filtres, donc une seule URL par recherche.
        $query = (string) preg_replace('/%5B\d+%5D=/', '%5B%5D=', http_build_query($params));

        return url($this->path() . ($query !== '' ? '?' . $query : ''));
    }

    /** URL d'une autre page de résultats (pagination). */
    public function pageUrl(int $page): string
    {
        return $this->url(['page' => $page > 1 ? (string) $page : null]);
    }

    /** URL de la même recherche dans une autre présentation (grille, liste, carte). */
    public function viewUrl(string $view): string
    {
        return $this->url(['vue' => $view === self::DEFAULT_VIEW ? null : $view, 'page' => null]);
    }

    /** URL de la même recherche dans un autre ordre de tri. */
    public function sortUrl(string $sort): string
    {
        return $this->url(['tri' => $sort === self::DEFAULT_SORT ? null : $sort]);
    }

    /**
     * Filtres actifs, affichés en puces au-dessus des résultats. Chaque puce porte l'URL de la
     * même recherche sans ce filtre — un seul à la fois, les autres sont conservés.
     *
     * @return list<array{label: string, url: string}>
     */
    public function chips(): array
    {
        $chips = [];

        if ($this->keyword !== '') {
            $chips[] = ['label' => '« ' . $this->keyword . ' »', 'url' => $this->url(['q' => null])];
        }
        foreach ([
            ['prix_min', $this->priceMin, 'front.filters.price_min', true],
            ['prix_max', $this->priceMax, 'front.filters.price_max', true],
            ['surface_min', $this->areaMin, 'front.filters.area_min', false],
            ['terrain_min', $this->landMin, 'front.filters.land_min', false],
        ] as [$key, $value, $label, $isPrice]) {
            if ($value !== null) {
                $formatted = $isPrice ? format_price($value) : format_number($value) . "\u{00A0}m²";
                $chips[] = ['label' => __($label) . ' : ' . $formatted, 'url' => $this->url([$key => null])];
            }
        }
        foreach ([
            ['pieces', $this->rooms, 'front.filters.rooms'],
            ['chambres', $this->bedrooms, 'front.filters.bedrooms'],
            ['sdb', $this->bathrooms, 'front.filters.bathrooms'],
        ] as [$key, $value, $label]) {
            if ($value !== null) {
                $chips[] = ['label' => __($label) . ' : ' . $value . '+', 'url' => $this->url([$key => null])];
            }
        }

        foreach ($this->features as $code => $label) {
            $remaining = array_values(array_diff(array_keys($this->features), [$code]));
            $chips[] = ['label' => $label, 'url' => $this->url(['equipements' => $remaining ?: null])];
        }

        foreach ($this->attributes as $attributeCode => $attribute) {
            foreach ($attribute['values'] as $code => $label) {
                $remaining = array_values(array_diff(array_keys($attribute['values']), [$code]));
                $chips[] = [
                    'label' => $code === '1' ? $attribute['label'] : $attribute['label'] . ' : ' . $label,
                    'url' => $this->url(['c_' . $attributeCode => $remaining ?: null]),
                ];
            }
        }

        return $chips;
    }

    /** URL de la même recherche sans aucun filtre de la chaîne de requête. */
    public function resetUrl(): string
    {
        return url($this->path());
    }

    /**
     * Un filtre au moins restreint la recherche : l'état vide propose alors de les retirer,
     * au lieu d'annoncer une rubrique encore vide. Le tri, la présentation et la page n'en sont pas.
     */
    public function hasFilters(): bool
    {
        return $this->category !== null
            || $this->city !== null
            || array_diff_key($this->queryParams(), array_flip(['tri', 'vue', 'page'])) !== [];
    }

    /** Nombre de filtres actifs, affiché sur le bouton « Filtres » en mobile. */
    public function filterCount(): int
    {
        $count = count($this->features);
        foreach ([$this->keyword, $this->priceMin, $this->priceMax, $this->areaMin, $this->landMin,
                  $this->rooms, $this->bedrooms, $this->bathrooms] as $value) {
            if ($value !== null && $value !== '') {
                $count++;
            }
        }
        foreach ($this->attributes as $attribute) {
            $count += count($attribute['values']);
        }

        return $count;
    }

    /** Décimales inutiles retirées : « 150 » plutôt que « 150.00 » dans l'URL. */
    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
