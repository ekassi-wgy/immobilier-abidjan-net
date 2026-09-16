<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Support\Paginator;

/**
 * Annuaire et profil public des agences partenaires (lot 1.11) : /agences, /agences/{slug}
 *
 * Seules les agences actives et non supprimées sont publiques. Le profil affiche ce que
 * l'agence gère elle-même depuis son espace (présentation, logo, coordonnées, zones) et ses
 * annonces en ligne ; les informations légales internes (RCCM, NCC, email de gestion) restent
 * au back-office.
 */
final class AgencyController extends Controller
{
    private const PER_PAGE = 12;
    private const LISTINGS_PER_PAGE = 9;

    public function index(Request $request): Response
    {
        $site = $this->site();
        $countryId = $site->country->id;

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'ville' => trim((string) $request->query('ville', '')),
            'commune' => trim((string) $request->query('commune', '')),
            'verifiee' => $request->query('verifiee') === 'oui' ? 'oui' : '',
        ];

        $listings = $this->app->listings();
        $total = $listings->countAgencies($countryId, $filters);
        $paginator = Paginator::fromRequest($request, $total, self::PER_PAGE);
        if ($total > 0 && (int) $request->query('page', 1) > $paginator->pages) {
            throw new HttpException(404);
        }

        return $this->page('front/layouts/app', 'front/pages/agencies', [
            'agencies' => $listings->agencies($countryId, $filters, self::PER_PAGE, $paginator->offset),
            'total' => $total,
            'filters' => $filters,
            'cities' => $listings->agencyCities($countryId),
            'paginator' => $paginator,
            'baseUrl' => 'agences',
            'query' => array_filter($filters, static fn (string $value): bool => $value !== ''),
        ], [
            'title' => __('front.agencies.meta_title', ['country' => $site->country->localizedName(locale())]),
            'description' => __('front.agencies.meta_description'),
            'canonical' => absolute_url('agences'),
            'noindex' => $total === 0,
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        [$site, $agency] = $this->findOrFail($slug);

        return $this->render($request, $agency, (int) $site->country->id);
    }

    /** Demande de contact adressée à l'agence (lead `agency_contact`). */
    public function contact(Request $request, string $slug): Response
    {
        [$site, $agency] = $this->findOrFail($slug);

        $input = $request->all();
        $validator = $this->validateContact($input);
        $trapped = $this->isTrapped($request);
        $allowed = $this->withinQuota($request, 'agency');

        if ($validator->fails()) {
            return $this->render($request, $agency, (int) $site->country->id, $validator->errors(), $input, 422);
        }
        if (!$allowed) {
            return $this->render($request, $agency, (int) $site->country->id, ['message' => __('front.contact.too_many')], $input, 429);
        }

        if (!$trapped) {
            $leadId = $this->insertLead($request, [
                'type' => 'agency_contact',
                'agency_id' => (int) $agency['id'],
                'name' => $validator->string('name'),
                'email' => $validator->nullableString('email'),
                'phone' => $validator->nullableString('phone'),
                'message' => $validator->nullableString('message'),
            ]);

            $this->notifyBackOffice(
                'lead.new',
                __('front.agencies.notification_title', ['agency' => (string) $agency['name']]),
                __('front.contact.notification_body_simple', ['name' => $validator->string('name')]),
                cmsadmin_url('contacts/' . $leadId),
                $this->app->notifier()->propertyRecipients(['agency_id' => (int) $agency['id']])
            );
        }

        $this->flash('success', __('front.contact.success_agency'));

        return $this->redirect('agences/' . $agency['slug'] . '#contact', 303);
    }

    /**
     * @return array{0: \App\Models\Site, 1: array<string, mixed>}
     */
    private function findOrFail(string $slug): array
    {
        $site = $this->site();
        $agency = $this->app->listings()->agencyBySlug($slug, $site->country->id) ?? throw new HttpException(404);

        return [$site, $agency];
    }

    /**
     * @param array<string, mixed>  $agency
     * @param array<string, string> $errors
     * @param array<string, mixed>  $old
     */
    private function render(Request $request, array $agency, int $countryId, array $errors = [], array $old = [], int $status = 200): Response
    {
        $listings = $this->app->listings();
        $agencyId = (int) $agency['id'];

        $total = (int) $agency['listings'];
        $paginator = Paginator::fromRequest($request, $total, self::LISTINGS_PER_PAGE);
        $page = $listings->byAgency($agencyId, $countryId, self::LISTINGS_PER_PAGE, $paginator->offset);

        return $this->page('front/layouts/app', 'front/pages/agency', [
            'agency' => $agency,
            'zones' => $listings->agencyZones($agencyId),
            'listings' => $this->app->listingPresenter()->cards($page['rows']),
            'paginator' => $paginator,
            'baseUrl' => 'agences/' . $agency['slug'],
            'query' => [],
            'errors' => $errors,
            'old' => $old,
            'flash' => $this->app->session()->flashes(),
            'csrfToken' => $this->app->csrf()->token(),
        ], [
            'title' => __('front.agencies.profile_meta_title', ['agency' => (string) $agency['name']]),
            'description' => __('front.agencies.profile_meta_description', [
                'agency' => (string) $agency['name'],
                'place' => (string) ($agency['city_name'] ?? ''),
            ]),
            'canonical' => absolute_url('agences/' . $agency['slug']),
            'ogImage' => !empty($agency['logo_path']) ? absolute_url((string) $agency['logo_path']) : null,
            'schema' => $this->schema($agency),
        ], $status);
    }

    /**
     * @param array<string, mixed> $agency
     * @return array<string, mixed>
     */
    private function schema(array $agency): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'RealEstateAgent',
            'name' => (string) $agency['name'],
            'url' => absolute_url('agences/' . $agency['slug']),
            'image' => !empty($agency['logo_path']) ? absolute_url((string) $agency['logo_path']) : null,
            'telephone' => !empty($agency['phone']) ? (string) $agency['phone'] : null,
            'areaServed' => !empty($agency['city_name']) ? (string) $agency['city_name'] : null,
            'description' => !empty($agency['description'])
                ? mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $agency['description'])) ?? ''), 0, 400)
                : null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
