<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Support\Paginator;

/**
 * Vitrine « Nos partenaires » : /partenaires.
 *
 * Weblogy est l'intermédiaire exclusif entre les partenaires et les prospects. La vitrine montre
 * le sérieux du réseau (logo, nom, type de professionnel, implantation, badge vérifié) mais
 * **aucune coordonnée, aucun formulaire de contact et aucune liste d'annonces** : un prospect
 * s'adresse toujours à Weblogy.
 *
 * Les anciennes adresses de l'annuaire (/agences, /agences/{slug}) redirigent en 301 ici.
 */
final class AgencyController extends Controller
{
    private const PER_PAGE = 24;

    public function index(Request $request): Response
    {
        $site = $this->site();
        $countryId = $site->country->id;

        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 80),
            'ville' => trim((string) $request->query('ville', '')),
            'verifiee' => $request->query('verifiee') === 'oui' ? 'oui' : '',
        ];

        $listings = $this->app->listings();
        $total = $listings->countAgencies($countryId, $filters);
        $paginator = Paginator::fromRequest($request, $total, self::PER_PAGE);
        if ($total > 0 && (int) $request->query('page', 1) > $paginator->pages) {
            throw new HttpException(404);
        }
        $query = array_filter($filters, static fn (string $value): bool => $value !== '');

        return $this->page('front/layouts/app', 'front/pages/agencies', [
            'agencies' => $listings->agencies($countryId, $filters, self::PER_PAGE, $paginator->offset),
            'total' => $total,
            'filters' => $filters,
            'cities' => $listings->agencyCities($countryId),
            'paginator' => $paginator,
            'baseUrl' => 'partenaires',
            'query' => $query,
        ], [
            'title' => __('front.agencies.meta_title', ['country' => $site->country->localizedName(locale())]),
            'description' => __('front.agencies.meta_description', ['site' => $site->name]),
            'canonical' => absolute_url('partenaires'),
            'noindex' => $total === 0 || $query !== [],
        ]);
    }

    /** Anciennes adresses de l'annuaire (profil et contact direct d'une agence) : redirection permanente. */
    public function legacy(Request $request, string $slug = ''): Response
    {
        return $this->redirect('partenaires', 301);
    }
}
