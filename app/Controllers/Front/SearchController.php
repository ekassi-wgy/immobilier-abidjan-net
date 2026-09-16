<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\SearchCriteria;
use App\Services\SearchFilters;
use App\Support\Paginator;

/**
 * Page de résultats (lot 1.9) : /acheter/…, /louer/…
 *
 * L'URL porte la transaction, le type de bien et la localisation ; les autres filtres, le tri,
 * la présentation (grille, liste, carte) et la page restent en chaîne de requête.
 * Un formulaire qui renvoie `type`, `ville`, `commune`, `quartier` ou `lieu` est redirigé vers
 * l'URL canonique : les visiteurs et les moteurs ne voient qu'une adresse par recherche.
 */
final class SearchController extends Controller
{
    private const PER_PAGE = 24;

    /** Points chargés d'un coup dans la vue carte (au-delà, le visiteur affine ses filtres). */
    private const MAP_POINTS = 400;

    public function index(
        Request $request,
        string $transaction,
        string $s1 = '',
        string $s2 = '',
        string $s3 = '',
        string $s4 = '',
    ): Response {
        $site = site() ?? throw new HttpException(404);
        $countryId = $site->country->id;
        $filters = $this->app->searchFilters();
        $query = $request->queryAll();

        if (array_intersect_key($query, array_flip(SearchFilters::PATH_PARAMS)) !== []) {
            return $this->canonicalRedirect($filters, $transaction, $query, $countryId);
        }

        $segments = array_values(array_filter([$s1, $s2, $s3, $s4], static fn (string $s): bool => $s !== ''));
        $criteria = $filters->resolve($transaction, $segments, $query, $countryId);

        $listings = $this->app->listings();
        $presenter = $this->app->listingPresenter();

        $total = $listings->countSearch($countryId, $criteria);
        $paginator = new Paginator($total, self::PER_PAGE, $criteria->page);
        if ($criteria->page > $paginator->pages && $total > 0) {
            throw new HttpException(404);
        }

        $results = $presenter->cards($listings->search($countryId, $criteria, self::PER_PAGE, $paginator->offset));
        $points = $criteria->view === 'carte'
            ? $presenter->points($listings->mapPoints($countryId, $criteria, self::MAP_POINTS))
            : [];

        $heading = $this->heading($criteria);

        return $this->page('front/layouts/app', 'front/pages/search', [
            'criteria' => $criteria,
            'heading' => $heading,
            'total' => $total,
            'results' => $results,
            'points' => $points,
            'paginator' => $paginator,
            'panel' => $filters->panel($criteria, $countryId),
            'breadcrumb' => $this->breadcrumb($criteria),
            'sorts' => $this->sorts(),
            'transactions' => $this->app->searchOptions()->transactions(),
        ], [
            'title' => __('front.results.meta_title', ['heading' => $heading, 'count' => format_number($total)]),
            'description' => __('front.results.meta_description', ['heading' => mb_strtolower($heading), 'site' => $site->name]),
            'canonical' => absolute_url($criteria->path()) . $this->canonicalQuery($criteria),
            // Les combinaisons de filtres secondaires ne sont pas indexées (contenu quasi dupliqué).
            'noindex' => $this->isFiltered($criteria) || $total === 0,
            // Leaflet et son plugin de regroupement ne sont chargés que sur la vue carte.
            'pageScripts' => $criteria->view === 'carte'
                ? ['vendors/leaflet/leaflet.js', 'vendors/leaflet/leaflet.markercluster.js', 'js/search.js']
                : ['js/search.js'],
            'pageStyles' => $criteria->view === 'carte' ? ['vendors/leaflet/leaflet.css', 'vendors/leaflet/MarkerCluster.css'] : [],
            'schema' => $this->schema($criteria, $this->breadcrumb($criteria)),
        ]);
    }

    /** Recherche soumise par un formulaire : redirection 302 vers l'URL canonique. */
    private function canonicalRedirect(SearchFilters $filters, string $transaction, array $query, int $countryId): Response
    {
        // Une transaction inconnue répond 404 plutôt que de rediriger vers une URL qui n'existe pas.
        $filters->transaction($transaction) ?? throw new HttpException(404);

        [$path, $rest] = $filters->canonical($transaction, $query, $countryId);
        $queryString = http_build_query($rest);

        return $this->redirect($path . ($queryString !== '' ? '?' . $queryString : ''));
    }

    /** « Appartements à vendre à Cocody, Abidjan » : titre H1 et base des balises SEO. */
    private function heading(SearchCriteria $criteria): string
    {
        $types = $criteria->category['plural'] ?? __('front.results.all_types');
        $place = $this->place($criteria);

        return trim(sprintf(
            '%s %s%s',
            $types,
            $this->transactionPhrase($criteria->transaction),
            $place !== '' ? ' ' . __('front.results.in_place', ['place' => $place]) : ''
        ));
    }

    /** « Cocody, Abidjan » : localisation la plus précise choisie, suivie de son parent. */
    private function place(SearchCriteria $criteria): string
    {
        $levels = array_values(array_filter([
            $criteria->district['name'] ?? null,
            $criteria->commune['name'] ?? null,
            $criteria->city['name'] ?? null,
        ]));

        return implode(', ', array_slice($levels, 0, 2));
    }

    /** « à vendre », « à louer »… : formulation propre à la transaction, sinon son libellé. */
    private function transactionPhrase(array $transaction): string
    {
        $key = 'front.results.transaction.' . $transaction['slug'];

        return $this->app->translator()->has($key) ? __($key) : mb_strtolower($transaction['name']);
    }

    /**
     * Fil d'Ariane, du site jusqu'au niveau le plus fin. Chaque étape est une recherche valide.
     * Les chemins sont relatifs à la racine publique : la vue les passe à `url()`, le JSON-LD
     * à `absolute_url()` — jamais de préfixe d'installation appliqué deux fois.
     *
     * @return list<array{label: string, path: string, current: bool}>
     */
    private function breadcrumb(SearchCriteria $criteria): array
    {
        $items = [['label' => __('front.nav.home'), 'path' => '', 'current' => false]];
        $path = $criteria->transaction['slug'];
        $items[] = ['label' => $criteria->transaction['name'], 'path' => $path, 'current' => false];

        foreach ([$criteria->category, $criteria->city, $criteria->commune, $criteria->district] as $level) {
            if ($level === null) {
                continue;
            }
            $path .= '/' . $level['slug'];
            $items[] = ['label' => $level['name'], 'path' => $path, 'current' => false];
        }

        $items[array_key_last($items)]['current'] = true;

        return $items;
    }

    /** @return array<string, string> [clé de tri => libellé] */
    private function sorts(): array
    {
        $labels = [];
        foreach (SearchCriteria::SORTS as $sort) {
            $labels[$sort] = __('front.results.sort.' . $sort);
        }

        return $labels;
    }

    /** Un filtre au-delà du chemin est actif : la page n'est pas indexable telle quelle. */
    private function isFiltered(SearchCriteria $criteria): bool
    {
        return array_diff_key($criteria->queryParams(), array_flip(['page'])) !== [];
    }

    /** Seule la pagination entre dans l'URL canonique (la page 2 reste une page à part entière). */
    private function canonicalQuery(SearchCriteria $criteria): string
    {
        return $criteria->page > 1 ? '?page=' . $criteria->page : '';
    }

    /**
     * Données structurées : page de résultats et fil d'Ariane.
     *
     * @param list<array{label: string, path: string, current: bool}> $breadcrumb
     * @return array<string, mixed>
     */
    private function schema(SearchCriteria $criteria, array $breadcrumb): array
    {
        $items = [];
        foreach ($breadcrumb as $position => $item) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $item['label'],
                'item' => absolute_url($item['path']),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'SearchResultsPage',
                    'name' => $this->heading($criteria),
                    'url' => absolute_url($criteria->path()),
                ],
                ['@type' => 'BreadcrumbList', 'itemListElement' => $items],
            ],
        ];
    }
}
