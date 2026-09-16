<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Site;

/**
 * Accueil du site public (lot 1.8) : hero et recherche rapide, biens à la une, types de biens,
 * dernières annonces, chiffres clés, agences partenaires en vedette.
 *
 * Toutes les données viennent du pays du site courant ; une section sans contenu n'est pas affichée.
 */
final class HomeController extends Controller
{
    private const FEATURED = 6;
    private const LATEST = 8;
    private const AGENCIES = 6;

    /**
     * Diapositives de repli tant que le client n'a pas fourni ses photos et que la table `banners`
     * est vide (le CRUD des bannières arrive au lot 2.2). Photos libres provisoires : voir CREDITS.md.
     */
    private const FALLBACK_SLIDES = [
        ['image' => 'img/placeholder/hero/abidjan-lagune-ebrie', 'caption' => 'Abidjan, au bord de la lagune Ébrié', 'origin' => '50% 60%'],
        ['image' => 'img/placeholder/hero/riviera-golf-coproprietes', 'caption' => 'Riviera Golf, Cocody', 'origin' => '40% 50%'],
        ['image' => 'img/placeholder/hero/coucher-soleil-lagune-ebrie', 'caption' => 'Coucher de soleil sur la lagune Ébrié', 'origin' => '55% 45%'],
        ['image' => 'img/placeholder/hero/plateau-pont-ado', 'caption' => 'Le Plateau vu du pont Alassane-Ouattara', 'origin' => '60% 50%'],
    ];

    public function index(Request $request): Response
    {
        $site = site() ?? throw new HttpException(404);
        $countryId = $site->country->id;

        $listings = $this->app->listings();
        $presenter = $this->app->listingPresenter();

        $featured = $listings->featured($countryId, self::FEATURED);
        $featuredIds = array_map(static fn (array $row): int => (int) $row['id'], $featured);
        $latest = $listings->latest($countryId, self::LATEST, $featuredIds);
        $figures = $listings->keyFigures($countryId);
        $slides = $this->slides($site->id);

        return $this->page('front/layouts/app', 'front/pages/home', [
            'hero' => [
                'slides' => $slides,
                'listingsCount' => $figures['listings'],
                'search' => $this->app->searchOptions()->all($countryId),
                'interval' => 7000,
                'city' => $this->mainCity($countryId, $site),
            ],
            'featured' => $presenter->cards($featured),
            'latest' => $presenter->cards($latest),
            'families' => $listings->categoryFamilies($countryId),
            'communes' => $listings->popularCommunes($countryId),
            'figures' => $figures,
            'agencies' => $listings->featuredAgencies($countryId, self::AGENCIES),
        ], [
            'title' => __('front.home.meta_title', ['country' => $site->country->localizedName(locale())]),
            'description' => __('front.home.meta_description'),
            'headerOverlay' => true,
            'preloadImage' => ['desktop' => $slides[0]['desktop'], 'mobile' => $slides[0]['mobile']],
            'pageScripts' => ['js/hero.js'],
            'canonical' => absolute_url(),
            'schema' => $this->schema($site, $figures['listings']),
        ]);
    }

    /**
     * Diapositives du hero : bannières « home_hero » du site, sinon les photos provisoires.
     * Une bannière envoyée depuis le back-office (lot 2.2) est servie telle quelle ;
     * les photos livrées avec la maquette existent en 1920 (bureau) et 960 (mobile).
     *
     * @return list<array{desktop: string, mobile: string, fallback: string, caption: ?string, origin: string}>
     */
    private function slides(int $siteId): array
    {
        $slides = [];
        foreach ($this->app->listings()->heroBanners($siteId) as $banner) {
            $image = url(ltrim((string) $banner['image_path'], '/'));
            $slides[] = [
                'desktop' => $image,
                'mobile' => $image,
                'fallback' => $image,
                'caption' => $banner['caption'] !== null ? (string) $banner['caption'] : null,
                'origin' => '50% 50%',
            ];
        }
        if ($slides !== []) {
            return $slides;
        }

        return array_map(static fn (array $slide): array => [
            'desktop' => asset($slide['image'] . '-1920.webp'),
            'mobile' => asset($slide['image'] . '-960.webp'),
            'fallback' => asset($slide['image'] . '-1920.jpg'),
            'caption' => $slide['caption'],
            'origin' => $slide['origin'],
        ], self::FALLBACK_SLIDES);
    }

    /** Ville mise en avant dans le titre du hero : première ville active du pays, sinon le pays. */
    private function mainCity(int $countryId, Site $site): string
    {
        $cities = $this->app->geo()->cityOptions($countryId, true);

        return $cities !== [] ? (string) reset($cities) : $site->country->localizedName(locale());
    }

    /**
     * Données structurées de l'accueil (Schema.org) : le site et l'organisation qui l'édite.
     *
     * @return array<string, mixed>
     */
    private function schema(Site $site, int $listings): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'RealEstateAgent',
            'name' => $site->name,
            'url' => absolute_url(),
            'logo' => absolute_url('assets/img/brand/logo-immobilier-abidjan-net.png'),
            'description' => __('front.home.meta_description'),
            'areaServed' => $site->country->localizedName(locale()),
            'numberOfItems' => $listings,
        ];
    }
}
