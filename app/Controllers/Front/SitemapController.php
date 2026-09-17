<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;

/**
 * `sitemap.xml` et `robots.txt` du site courant (lot 2.1).
 *
 * Le sitemap est calculé à chaque appel : il ne contient que des URL réellement indexables —
 * accueil, pages de résultats qui portent au moins une annonce, annonces en ligne, vitrine des
 * partenaires, pages éditoriales publiées. Une URL surchargée en `noindex` dans `seo_meta` en est
 * retirée : le sitemap ne doit jamais contredire la balise `robots` de la page.
 *
 * `robots.txt` interdit tout hors production : un domaine de pré-production ne doit pas être
 * indexé, ni faire concurrence au site réel.
 */
final class SitemapController extends Controller
{
    /** Limite volontairement basse au regard des 50 000 URL admises : elle suffit largement en v1. */
    private const MAX_PROPERTIES = 20000;

    private const MAX_POSTS = 5000;

    public function sitemap(Request $request): Response
    {
        $site = $this->site();
        $listings = $this->app->listings();
        $seo = $this->app->seo();

        $urls = [['loc' => '', 'lastmod' => null, 'priority' => '1.0', 'changefreq' => 'daily']];

        foreach ($listings->sitemapSearchPaths($site->country->id) as $path) {
            $urls[] = ['loc' => $path, 'lastmod' => null, 'priority' => '0.8', 'changefreq' => 'daily'];
        }
        $presenter = $this->app->listingPresenter();
        foreach ($listings->sitemapProperties($site->country->id, self::MAX_PROPERTIES) as $row) {
            $urls[] = ['loc' => $presenter->url($row), 'lastmod' => $row['lastmod'], 'priority' => '0.7', 'changefreq' => 'weekly'];
        }
        // Vitrine des partenaires : une seule page (les profils d'agence ne sont plus publics).
        if ($listings->countAgencies($site->country->id, []) > 0) {
            $urls[] = ['loc' => 'partenaires', 'lastmod' => null, 'priority' => '0.4', 'changefreq' => 'monthly'];
        }
        // Pages de service indexables : les parcours d'acquisition (contact, partenaires, biens confiés).
        foreach (['contact', 'confiez-nous-votre-bien', 'devenir-partenaire'] as $path) {
            $urls[] = ['loc' => $path, 'lastmod' => null, 'priority' => '0.5', 'changefreq' => 'monthly'];
        }
        // Actualités : la liste n'est indexable que si elle contient au moins un article publié.
        $posts = $this->app->content()->publishedPosts($site->id, locale(), self::MAX_POSTS, 0);
        if ($posts['total'] > 0) {
            $urls[] = ['loc' => 'actualites', 'lastmod' => null, 'priority' => '0.5', 'changefreq' => 'weekly'];
            foreach ($posts['rows'] as $post) {
                $urls[] = ['loc' => 'actualites/' . $post['slug'], 'lastmod' => $post['published_at'], 'priority' => '0.5', 'changefreq' => 'monthly'];
            }
        }
        foreach ($this->app->pages()->publishedSlugs() as $slug) {
            $urls[] = ['loc' => $slug, 'lastmod' => null, 'priority' => '0.3', 'changefreq' => 'yearly'];
        }

        // Une page mise en noindex depuis le back-office sort du sitemap. La liste est chargée
        // en une requête : l'interroger URL par URL en ferait des milliers.
        $noindex = $seo->noindexPaths($site->id);
        $urls = array_values(array_filter(
            $urls,
            static fn (array $url): bool => !isset($noindex[$seo->normalize((string) $url['loc'])])
        ));

        return new Response($this->xml($urls), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    public function robots(Request $request): Response
    {
        $site = $this->site();
        $lines = ['User-agent: *'];

        if ($site->isProductionHost()) {
            // Aucune ligne vide dans le groupe : certains robots y voient la fin du bloc User-agent.
            array_push(
                $lines,
                '# Espaces privés : back-office, espace propriétaire, favoris du visiteur',
                'Disallow: /cmsadmin',
                'Disallow: /mon-espace',
                'Disallow: /favoris',
                '# Combinaisons de filtres, tris et vues de recherche : contenu en double, déjà en noindex',
                'Disallow: /*?',
                '# … sauf la pagination des listes, pour que les robots atteignent toutes les annonces',
                'Allow: /*?page=',
                // La règle la plus longue l'emporte : sans ces lignes, « /*? » bloquerait les CSS et JS
                // versionnés (?v=…) et Google afficherait les pages sans mise en forme.
                '# Styles, scripts, polices et photos : nécessaires à Google pour afficher les pages',
                'Allow: /assets/',
                'Allow: /uploads/',
                '',
                'Sitemap: ' . absolute_url('sitemap.xml'),
            );
        } else {
            // Pré-production et développement : rien ne doit être indexé.
            $lines[] = 'Disallow: /';
        }

        return new Response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * @param list<array{loc: string, lastmod: ?string, priority: string, changefreq: string}> $urls
     */
    private function xml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n"
                . '    <loc>' . htmlspecialchars(absolute_url(ltrim($url['loc'], '/')), ENT_XML1) . '</loc>' . "\n";
            if (!empty($url['lastmod'])) {
                $xml .= '    <lastmod>' . gmdate('Y-m-d', (int) strtotime((string) $url['lastmod'])) . '</lastmod>' . "\n";
            }
            $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n"
                . '    <priority>' . $url['priority'] . '</priority>' . "\n"
                . '  </url>' . "\n";
        }

        return $xml . '</urlset>' . "\n";
    }
}
