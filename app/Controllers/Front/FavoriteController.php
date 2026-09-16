<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;

/**
 * Favoris du visiteur (lot 1.9).
 *
 * Il n'existe aucun compte visiteur : les références mises de côté sont mémorisées par le
 * navigateur (localStorage) et recopiées dans un cookie `ian_fav` par `assets/js/search.js`,
 * uniquement pour que cette page puisse être rendue côté serveur. Aucune donnée personnelle,
 * aucune session, et l'ordre du cookie (le plus récent en premier) est conservé à l'affichage.
 */
final class FavoriteController extends Controller
{
    public const COOKIE = 'ian_fav';

    /** Garde-fou : un cookie trop long est ignoré au-delà de cette limite. */
    private const MAX_REFERENCES = 60;

    public function index(Request $request): Response
    {
        $site = $this->site();

        $references = $this->references($request->cookie(self::COOKIE));
        $rows = $references === [] ? [] : $this->app->listings()->byReferences($site->country->id, $references);

        // Les annonces retirées de la vente disparaissent d'elles-mêmes ; l'ordre du visiteur est gardé.
        $byReference = [];
        foreach ($rows as $row) {
            $byReference[(string) $row['reference']] = $row;
        }
        $ordered = array_values(array_filter(array_map(
            static fn (string $reference): ?array => $byReference[$reference] ?? null,
            $references
        )));

        return $this->page('front/layouts/app', 'front/pages/favorites', [
            'favorites' => $this->app->listingPresenter()->cards($ordered),
            'missing' => count($references) - count($ordered),
        ], [
            'title' => __('front.favorites.title'),
            'description' => __('front.favorites.meta_description', ['site' => $site->name]),
            'noindex' => true,
        ]);
    }

    /**
     * Références lues dans le cookie : « IAN-24531,IAN-24532 ».
     *
     * @return list<string>
     */
    private function references(?string $cookie): array
    {
        if ($cookie === null || $cookie === '') {
            return [];
        }

        $references = [];
        foreach (explode(',', $cookie) as $reference) {
            $reference = strtoupper(trim($reference));
            if (preg_match('/^[A-Z]{2,6}-[0-9]{1,12}$/', $reference) === 1) {
                $references[] = $reference;
            }
        }

        return array_slice(array_values(array_unique($references)), 0, self::MAX_REFERENCES);
    }
}
