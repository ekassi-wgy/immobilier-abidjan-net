<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;

/**
 * Section « Biens à la une » embarquée sur Abidjan.net (lot 2.6).
 *
 * Une seule adresse, deux usages :
 * - inclusion côté serveur (PHP d'Abidjan.net) : le fragment arrive dans le HTML de leur page, liens
 *   compris — aucun script, rien à charger en plus pour le visiteur ;
 * - script `public/widget/biens-a-la-une.js` : le navigateur charge le même fragment (CORS limité
 *   à `app.widget.origins`).
 *
 * Le fragment est une racine fantôme déclarative (`<template shadowrootmode="open">`) : le CSS de la
 * page hôte ne peut pas altérer la section, et le nôtre ne déborde pas sur la page hôte. Seule la
 * police est déclarée hors de cette racine — une `@font-face` n'est prise en compte qu'au niveau du
 * document.
 *
 * Mêmes données et même carte que l'accueil (`ListingRepository::featured()`, `ListingPresenter`,
 * `partials/property-card` en mode `embed`) : Weblogy reste l'unique interlocuteur affiché.
 * Rien n'est renvoyé en mode démonstration : Abidjan.net ne doit jamais montrer d'annonce fictive.
 */
final class WidgetController extends Controller
{
    private const DEFAULT_LIMIT = 3;
    private const MAX_LIMIT = 6;

    /** Cache serveur du fragment (secondes) : une page très fréquentée d'Abidjan.net ne coûte qu'une requête. */
    private const SERVER_TTL = 300;

    /** Cache des navigateurs et du serveur d'Abidjan.net (secondes). */
    private const HTTP_TTL = 600;

    /** Marquage de provenance des liens, lu par la mesure d'audience. */
    private const UTM = ['utm_source' => 'abidjan.net', 'utm_medium' => 'widget', 'utm_campaign' => 'biens-a-la-une'];

    public function featured(Request $request): Response
    {
        $site = $this->site();
        $limit = max(1, min(self::MAX_LIMIT, (int) $request->query('limit', self::DEFAULT_LIMIT) ?: self::DEFAULT_LIMIT));
        $origin = $this->origin();

        $html = demo_mode() ? '' : $this->app->cache()->remember(
            sprintf('widget_featured_%d_%d_%s', $site->id, $limit, md5($origin . '|' . $site->defaultLocale)),
            self::SERVER_TTL,
            fn (): string => $this->render($site->country->id, $limit, $origin)
        );

        $response = Response::html($html);
        $response->setHeader('Cache-Control', sprintf('public, max-age=%d, stale-while-revalidate=%d', self::HTTP_TTL, self::HTTP_TTL));
        // Fragment, pas une page : il n'a pas à figurer seul dans les résultats de recherche
        $response->setHeader('X-Robots-Tag', 'noindex');
        $response->setHeader('Vary', 'Origin');

        $requestOrigin = rtrim((string) $request->header('Origin', ''), '/');
        if ($requestOrigin !== '' && in_array($requestOrigin, (array) config('app.widget.origins', []), true)) {
            $response->setHeader('Access-Control-Allow-Origin', $requestOrigin);
        }

        return $response;
    }

    private function render(int $countryId, int $limit, string $origin): string
    {
        $rows = $this->app->listings()->featured($countryId, $limit);
        if ($rows === []) {
            return '';
        }

        $cards = array_map(
            fn (array $card): array => $this->absolutize($card, $origin),
            $this->app->listingPresenter()->cards($rows)
        );

        return $this->app->view()->render('front/widget/featured', [
            'cards' => $cards,
            'allListingsUrl' => $this->withUtm($origin . url('acheter')),
            'homeUrl' => $this->withUtm($origin . url()),
            'css' => (string) @file_get_contents(APP_ROOT . '/public/assets/css/widget.css'),
            'fonts' => [
                'latin' => $origin . asset('fonts/plus-jakarta-sans/plus-jakarta-sans-latin-wght-normal.woff2'),
                'latinExt' => $origin . asset('fonts/plus-jakarta-sans/plus-jakarta-sans-latin-ext-wght-normal.woff2'),
            ],
        ]);
    }

    /**
     * Carte affichée sur un autre domaine : liens et images en adresses complètes.
     *
     * @param array<string, mixed> $card
     * @return array<string, mixed>
     */
    private function absolutize(array $card, string $origin): array
    {
        $card['url'] = $this->withUtm($origin . url($card['url']));

        $image = $card['image'];
        $image['src'] = $origin . $image['src'];
        if ($image['srcset'] !== '') {
            $image['srcset'] = implode(', ', array_map(
                static fn (string $candidate): string => $origin . trim($candidate),
                explode(',', $image['srcset'])
            ));
        }
        $card['image'] = $image;

        return $card;
    }

    private function withUtm(string $url): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query(self::UTM);
    }

    /** Schéma, hôte et port du site (https://immobilier.abidjan.net), sans chemin. */
    private function origin(): string
    {
        $home = absolute_url();

        return substr($home, 0, strlen($home) - strlen(url()));
    }
}
