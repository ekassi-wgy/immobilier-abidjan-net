<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Site;
use App\Services\SiteRepository;
use App\Support\Validator;
use DateTimeZone;

/**
 * Pays, sites et domaines (multisite). Réservé au Super Admin.
 *
 * Garde-fous : le site et le domaine utilisés pour la session en cours ne peuvent pas être désactivés ou supprimés
 * (on ne se coupe pas l'accès au back-office) ; un pays avec un site actif ne peut pas être désactivé.
 * Toute écriture vide le cache de résolution des sites.
 */
final class SiteController extends Controller
{
    private const ENVIRONMENTS = ['production', 'staging', 'local'];

    public function index(Request $request): Response
    {
        return $this->render($request, 'sites/index', [
            'countries' => array_values($this->app->countries()->all()),
            'sites' => $this->app->countries()->sites(),
            'currentSiteId' => site()?->id,
        ], [
            'title' => __('sites.title'),
            'activeMenu' => 'sites',
        ]);
    }

    // Pays ----------------------------------------------------------------------------------------

    public function createCountry(Request $request): Response
    {
        return $this->countryForm($request, null, ['currency_decimals' => 0, 'default_locale' => 'fr', 'timezone' => 'Africa/Abidjan', 'is_active' => 0, 'sort_order' => 0]);
    }

    public function storeCountry(Request $request): Response
    {
        [$data, $errors] = $this->validateCountry($request, null);
        if ($errors !== []) {
            return $this->countryForm($request, null, $request->all(), $errors, 422);
        }

        $id = $this->app->countries()->insertCountry($data);
        $this->app->sites()->flush();
        $this->log($request, 'country.created', 'country', $id, $data['name'], ['after' => $data], $id);
        $this->flash('success', __('sites.flash.saved', ['name' => $data['name']]));

        return $this->redirectToRoute('cmsadmin.sites.index', status: 303);
    }

    public function editCountry(Request $request, string $id): Response
    {
        $country = $this->findCountry((int) $id);

        return $this->countryForm($request, $country, $country + ['name_en' => $this->translation($country['name_translations'])]);
    }

    public function updateCountry(Request $request, string $id): Response
    {
        $country = $this->findCountry((int) $id);
        [$data, $errors] = $this->validateCountry($request, $country);
        if ($errors !== []) {
            return $this->countryForm($request, $country, $request->all() + $country, $errors, 422);
        }

        $this->app->countries()->updateCountry((int) $country['id'], $data);
        $this->app->sites()->flush();
        $this->log($request, 'country.updated', 'country', (int) $country['id'], $data['name'], $this->diff($country, $data), (int) $country['id']);
        $this->flash('success', __('sites.flash.saved', ['name' => $data['name']]));

        return $this->redirectToRoute('cmsadmin.sites.index', status: 303);
    }

    // Sites ---------------------------------------------------------------------------------------

    public function createSite(Request $request): Response
    {
        return $this->siteForm($request, null, ['status' => Site::STATUS_DISABLED, 'theme' => 'default', 'default_locale' => 'fr', 'supported_locales' => ['fr'], 'country_id' => $request->query('pays')]);
    }

    public function storeSite(Request $request): Response
    {
        [$data, $errors] = $this->validateSite($request, null);
        if ($errors !== []) {
            return $this->siteForm($request, null, $request->all(), $errors, 422);
        }

        $id = $this->app->countries()->insertSite($data);
        $this->app->sites()->flush();
        $this->log($request, 'site.created', 'site', $id, $data['name'], ['after' => $data], (int) $data['country_id']);
        $this->flash('success', __('sites.flash.site_created', ['name' => $data['name']]));

        return Response::redirect(route('cmsadmin.sites.edit', ['id' => $id]) . '#domaines', 303);
    }

    public function editSite(Request $request, string $id): Response
    {
        $site = $this->findSite((int) $id);

        $social = [];
        foreach (SiteRepository::socialLinks($site['social_links'] ?? null) as $network => $url) {
            $social['social_' . $network] = $url;
        }

        return $this->siteForm($request, $site, ['supported_locales' => $this->locales($site['supported_locales'])] + $social + $site);
    }

    public function updateSite(Request $request, string $id): Response
    {
        $site = $this->findSite((int) $id);
        [$data, $errors] = $this->validateSite($request, $site);
        if ($errors !== []) {
            return $this->siteForm($request, $site, $request->all() + ['supported_locales' => []] + $site, $errors, 422);
        }

        $this->app->countries()->updateSite((int) $site['id'], $data);
        $this->app->sites()->flush();
        $this->log($request, 'site.updated', 'site', (int) $site['id'], $data['name'], $this->diff($site, $data), (int) $site['country_id']);
        $this->flash('success', __('sites.flash.saved', ['name' => $data['name']]));

        return $this->redirectToRoute('cmsadmin.sites.edit', ['id' => (int) $site['id']], 303);
    }

    // Domaines ------------------------------------------------------------------------------------

    public function addDomain(Request $request, string $id): Response
    {
        $site = $this->findSite((int) $id);
        $input = $request->all();
        $input['host'] = strtolower(rtrim(trim((string) ($input['host'] ?? '')), '.'));

        $v = new Validator($input);
        $v->required('host')->maxLength('host', 190)->hostname('host')->required('environment')->in('environment', self::ENVIRONMENTS);
        if (!$v->has('host') && $this->app->countries()->hostExists($v->string('host'))) {
            $v->add('host', __('sites.domain_taken'));
        }

        if ($v->fails()) {
            return $this->siteForm($request, $site, ['supported_locales' => $this->locales($site['supported_locales'])] + $site, [], 422, $v->errors() + ['_values' => $input]);
        }

        $this->app->countries()->addDomain((int) $site['id'], $v->string('host'), $v->string('environment'), $v->bool('is_primary'));
        $this->app->sites()->flush();
        $this->log($request, 'site.domain_added', 'site', (int) $site['id'], $v->string('host') . ' (' . $v->string('environment') . ')', null, (int) $site['country_id']);
        $this->flash('success', __('sites.flash.domain_added', ['host' => $v->string('host')]));

        return Response::redirect(route('cmsadmin.sites.edit', ['id' => (int) $site['id']]) . '#domaines', 303);
    }

    public function primaryDomain(Request $request, string $id, string $domain): Response
    {
        $site = $this->findSite((int) $id);
        $row = $this->app->countries()->domain((int) $site['id'], (int) $domain) ?? throw new HttpException(404);

        $this->app->countries()->makePrimary((int) $site['id'], (int) $row['id'], (string) $row['environment']);
        $this->app->sites()->flush();
        $this->log($request, 'site.domain_primary', 'site', (int) $site['id'], (string) $row['host'], null, (int) $site['country_id']);
        $this->flash('success', __('sites.flash.domain_primary', ['host' => $row['host']]));

        return Response::redirect(route('cmsadmin.sites.edit', ['id' => (int) $site['id']]) . '#domaines', 303);
    }

    public function deleteDomain(Request $request, string $id, string $domain): Response
    {
        $site = $this->findSite((int) $id);
        $row = $this->app->countries()->domain((int) $site['id'], (int) $domain) ?? throw new HttpException(404);

        if ($row['host'] === $request->host()) {
            $this->flash('error', __('sites.flash.domain_current', ['host' => $row['host']]));
        } else {
            $this->app->countries()->deleteDomain((int) $row['id']);
            $this->app->sites()->flush();
            $this->log($request, 'site.domain_deleted', 'site', (int) $site['id'], (string) $row['host'], ['before' => $row], (int) $site['country_id']);
            $this->flash('success', __('sites.flash.domain_deleted', ['host' => $row['host']]));
        }

        return Response::redirect(route('cmsadmin.sites.edit', ['id' => (int) $site['id']]) . '#domaines', 303);
    }

    // Formulaires ---------------------------------------------------------------------------------

    /**
     * @param array<string, mixed>|null $country
     * @param array<string, mixed>      $values
     * @param array<string, string>     $errors
     */
    private function countryForm(Request $request, ?array $country, array $values, array $errors = [], int $status = 200): Response
    {
        return $this->render($request, 'sites/country-form', [
            'country' => $country,
            'values' => $values,
            'errors' => $errors,
            'locales' => $this->localeOptions(),
            'timezones' => $this->timezones(),
            'activeSites' => $country !== null ? $this->app->countries()->activeSitesCount((int) $country['id']) : 0,
        ], [
            'title' => $country !== null ? __('sites.country.edit', ['name' => $country['name']]) : __('sites.country.create'),
            'activeMenu' => 'sites',
            'plugins' => ['select2'],
        ], $status);
    }

    /**
     * @param array<string, mixed>|null $site
     * @param array<string, mixed>      $values
     * @param array<string, string>     $errors
     * @param array<string, mixed>      $domainErrors Erreurs du formulaire d'ajout de domaine (+ '_values')
     */
    private function siteForm(Request $request, ?array $site, array $values, array $errors = [], int $status = 200, array $domainErrors = []): Response
    {
        $countries = $this->app->countries();

        return $this->render($request, 'sites/site-form', [
            'site' => $site,
            'values' => $values,
            'errors' => $errors,
            'countries' => $countries->options(),
            'locales' => $this->localeOptions(),
            'statuses' => [Site::STATUS_ACTIVE => __('sites.status.active'), Site::STATUS_MAINTENANCE => __('sites.status.maintenance'), Site::STATUS_DISABLED => __('sites.status.disabled')],
            'environments' => array_combine(self::ENVIRONMENTS, array_map(static fn (string $env): string => __('sites.env.' . $env), self::ENVIRONMENTS)),
            'domains' => $site !== null ? $countries->domains((int) $site['id']) : [],
            'domainErrors' => $domainErrors,
            'isCurrent' => $site !== null && (int) $site['id'] === site()?->id,
            'currentHost' => $request->host(),
        ], [
            'title' => $site !== null ? __('sites.site.edit', ['name' => $site['name']]) : __('sites.site.create'),
            'activeMenu' => 'sites',
        ], $status);
    }

    /**
     * @param array<string, mixed>|null $country
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validateCountry(Request $request, ?array $country): array
    {
        $input = $request->all();
        foreach (['iso2', 'currency_code'] as $upper) {
            $input[$upper] = strtoupper(trim((string) ($input[$upper] ?? '')));
        }

        $v = new Validator($input);
        $v->required('name')->maxLength('name', 100)->maxLength('name_en', 100)
            ->required('currency_code')->rule('currency_code', preg_match('/^[A-Z]{3}$/', $v->string('currency_code')) === 1 || $v->string('currency_code') === '', __('sites.country.currency_code_invalid'))
            ->required('currency_symbol')->maxLength('currency_symbol', 10)
            ->integer('currency_decimals', 0, 3)
            ->required('phone_prefix')->rule('phone_prefix', preg_match('/^\+[0-9]{1,4}$/', $v->string('phone_prefix')) === 1 || $v->string('phone_prefix') === '', __('sites.country.phone_prefix_invalid'))
            ->required('default_locale')->in('default_locale', array_keys($this->localeOptions()))
            ->required('timezone')->in('timezone', DateTimeZone::listIdentifiers())
            ->integer('sort_order', -32768, 32767);

        if ($country === null) {
            $v->required('iso2')->rule('iso2', preg_match('/^[A-Z]{2}$/', $v->string('iso2')) === 1 || $v->string('iso2') === '', __('sites.country.iso2_invalid'));
            if (!$v->has('iso2') && $this->app->countries()->iso2Exists($v->string('iso2'))) {
                $v->add('iso2', __('validation.unique'));
            }
        }

        $active = $v->bool('is_active');
        if ($country !== null && !$active && (int) $country['is_active'] === 1 && $this->app->countries()->activeSitesCount((int) $country['id']) > 0) {
            $v->add('is_active', __('sites.country.has_active_sites'));
        }

        $nameEn = $v->nullableString('name_en');
        $data = [
            'name' => $v->string('name'),
            'name_translations' => $nameEn !== null ? json_encode(['en' => $nameEn], JSON_UNESCAPED_UNICODE) : null,
            'currency_code' => $v->string('currency_code'),
            'currency_symbol' => $v->string('currency_symbol'),
            'currency_decimals' => $v->int('currency_decimals'),
            'phone_prefix' => $v->string('phone_prefix'),
            'default_locale' => $v->string('default_locale'),
            'timezone' => $v->string('timezone'),
            'is_active' => $active ? 1 : 0,
            'sort_order' => $v->int('sort_order'),
        ];
        if ($country === null) {
            $data = ['iso2' => $v->string('iso2')] + $data;
        }

        return [$data, $v->errors()];
    }

    /**
     * @param array<string, mixed>|null $site
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validateSite(Request $request, ?array $site): array
    {
        $v = new Validator($request->all());
        $locales = array_keys($this->localeOptions());
        $v->required('name')->maxLength('name', 100)
            ->required('theme')->maxLength('theme', 50)->slug('theme')
            ->required('default_locale')->in('default_locale', $locales)
            ->required('status')->in('status', [Site::STATUS_ACTIVE, Site::STATUS_MAINTENANCE, Site::STATUS_DISABLED])
            ->maxLength('contact_email', 190)->email('contact_email')
            ->maxLength('contact_phone', 30)->phone('contact_phone')
            ->maxLength('contact_whatsapp', 30)->phone('contact_whatsapp')
            ->maxLength('address', 255)
            ->decimal('latitude', -90, 90)
            ->decimal('longitude', -180, 180);

        // Une position sans son autre moitié ne place rien sur la carte.
        if (($v->string('latitude') === '') !== ($v->string('longitude') === '')) {
            $v->add($v->string('latitude') === '' ? 'latitude' : 'longitude', __('sites.coordinates_pair'));
        }

        if ($site === null) {
            $v->required('country_id')->in('country_id', array_keys($this->app->countries()->options()))
                ->required('code')->maxLength('code', 30)->code('code');
            if (!$v->has('code') && $this->app->countries()->siteCodeExists($v->string('code'))) {
                $v->add('code', __('validation.unique'));
            }
        }

        $supported = array_values(array_intersect($locales, $v->list('supported_locales')));
        if (!in_array($v->string('default_locale'), $supported, true) && !$v->has('default_locale')) {
            $supported[] = $v->string('default_locale');
        }

        if ($site !== null && (int) $site['id'] === site()?->id && $v->string('status') === Site::STATUS_DISABLED) {
            $v->add('status', __('sites.site.cannot_disable_current'));
        }

        $data = [
            'name' => $v->string('name'),
            'theme' => $v->string('theme'),
            'default_locale' => $v->string('default_locale'),
            'supported_locales' => json_encode(array_values(array_unique($supported))),
            'contact_email' => $v->nullableString('contact_email'),
            'contact_phone' => $v->nullableString('contact_phone'),
            'contact_whatsapp' => $v->nullableString('contact_whatsapp'),
            'address' => $v->nullableString('address'),
            'social_links' => $this->socialLinks($v),
            'latitude' => $v->nullableDecimal('latitude'),
            'longitude' => $v->nullableDecimal('longitude'),
            'status' => $v->string('status'),
        ];
        if ($site === null) {
            $data = ['country_id' => $v->int('country_id'), 'code' => $v->string('code')] + $data;
        }

        return [$data, $v->errors()];
    }

    /**
     * Liens de réseaux sociaux saisis : « https:// » ajouté s'il manque, domaine du réseau exigé
     * (un lien Facebook qui pointe ailleurs est refusé). Aucun lien = NULL en base.
     */
    private function socialLinks(Validator $v): ?string
    {
        $links = [];
        foreach (Site::SOCIAL_NETWORKS as $network => $domains) {
            $field = 'social_' . $network;
            $url = $v->string($field);
            if ($url === '') {
                continue;
            }
            if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) !== 1) {
                $url = 'https://' . ltrim($url, '/');
            }

            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            $known = false;
            foreach ($domains as $domain) {
                $known = $known || $host === $domain || str_ends_with($host, '.' . $domain);
            }

            if (mb_strlen($url) > 255 || filter_var($url, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true)) {
                $v->add($field, __('sites.social.invalid'));
            } elseif (!$known) {
                $v->add($field, __('sites.social.wrong_domain', ['domain' => $domains[0]]));
            } else {
                $links[$network] = $url;
            }
        }

        return $links === [] ? null : json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, string> */
    private function localeOptions(): array
    {
        $options = [];
        foreach ((array) config('app.locales', ['fr']) as $locale) {
            $options[(string) $locale] = __('sites.locales.' . $locale);
        }

        return $options;
    }

    /** @return array<string, string> Fuseaux horaires, Afrique en tête */
    private function timezones(): array
    {
        $africa = DateTimeZone::listIdentifiers(DateTimeZone::AFRICA);
        $others = array_diff(DateTimeZone::listIdentifiers(), $africa);

        return array_combine([...$africa, ...$others], [...$africa, ...$others]);
    }

    /** @return list<string> */
    private function locales(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    private function translation(mixed $json): string
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        return is_array($decoded) && is_string($decoded['en'] ?? null) ? $decoded['en'] : '';
    }

    /** @return array<string, mixed> */
    private function findCountry(int $id): array
    {
        return $this->app->countries()->find($id) ?? throw new HttpException(404);
    }

    /** @return array<string, mixed> */
    private function findSite(int $id): array
    {
        return $this->app->countries()->site($id) ?? throw new HttpException(404);
    }
}
