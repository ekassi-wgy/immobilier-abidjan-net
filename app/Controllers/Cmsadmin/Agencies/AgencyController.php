<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Agencies;

use App\Controllers\Cmsadmin\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AgencyRepository;
use App\Support\Paginator;
use App\Support\Str;
use App\Support\Validator;

/**
 * Agences partenaires du pays du site (Super Admin et Admin Pays).
 *
 * Une agence suspendue, fermée ou supprimée coupe immédiatement l'accès de ses comptes (vérifié à chaque requête).
 * Suppression (logique) uniquement pour une agence sans annonce ; sinon : fermeture.
 */
final class AgencyController extends Controller
{
    use AccountSupport;
    use LogoSupport;

    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $countryId = $this->countryId();
        $repo = $this->app->agencies();
        $statut = (string) $request->query('statut', '');
        $verifiee = (string) $request->query('verifiee', '');
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'statut' => in_array($statut, AgencyRepository::STATUSES, true) ? $statut : '',
            'verifiee' => in_array($verifiee, ['oui', 'non'], true) ? $verifiee : '',
            'ville' => max(0, (int) $request->query('ville', 0)),
        ];

        $requested = Paginator::fromRequest($request, PHP_INT_MAX, self::PER_PAGE);
        $result = $repo->paginate($countryId, $filters, self::PER_PAGE, $requested->offset);
        $paginator = new Paginator($result['total'], self::PER_PAGE, $requested->page);
        if ($paginator->page !== $requested->page) {
            $result = $repo->paginate($countryId, $filters, self::PER_PAGE, $paginator->offset);
        }

        return $this->render($request, 'agencies/index', [
            'rows' => $result['rows'],
            'filters' => $filters,
            'counts' => $repo->countsByStatus($countryId),
            'cities' => $this->app->geo()->cityOptions($countryId),
            'pagination' => $paginator->toArray(),
        ], [
            'title' => __('agencies.title'),
            'activeMenu' => 'agencies.all',
            'plugins' => ['select2'],
        ]);
    }

    public function create(Request $request): Response
    {
        $values = ['status' => 'active', 'zones' => []];
        $partnerRequest = null;

        // Création à partir d'une demande « Devenir partenaire »
        $requestId = (int) $request->query('demande', 0);
        if ($requestId > 0) {
            $partnerRequest = $this->app->partnerRequests()->find($requestId, $this->countryId()) ?? throw new HttpException(404);
            if ($partnerRequest['agency_id'] !== null) {
                return $this->redirectToRoute('cmsadmin.agencies.edit', ['id' => (int) $partnerRequest['agency_id']]);
            }
            [$firstName, $lastName] = $this->splitName((string) $partnerRequest['contact_name']);
            $values = [
                'name' => $partnerRequest['agency_name'],
                'email' => $partnerRequest['email'],
                'phone' => $partnerRequest['phone'],
                'rccm' => $partnerRequest['rccm'],
                'city_id' => $partnerRequest['city_id'],
                'commune_id' => $partnerRequest['commune_id'],
                'zones' => $partnerRequest['commune_id'] !== null ? [(int) $partnerRequest['commune_id']] : [],
                'partner_request_id' => $partnerRequest['id'],
                'create_owner' => 1,
                'owner_first_name' => $firstName,
                'owner_last_name' => $lastName,
                'owner_email' => $partnerRequest['email'],
            ] + $values;
        }

        return $this->form($request, null, $values, [], 200, $partnerRequest);
    }

    public function store(Request $request): Response
    {
        $countryId = $this->countryId();
        $partnerRequest = null;
        $requestId = (int) $request->input('partner_request_id', 0);
        if ($requestId > 0) {
            $partnerRequest = $this->app->partnerRequests()->find($requestId, $countryId);
            if ($partnerRequest === null || $partnerRequest['agency_id'] !== null) {
                throw new HttpException(404, 'Demande introuvable ou déjà traitée');
            }
        }

        [$data, $zones, $errors] = $this->validate($request, $countryId, null);
        $owner = null;
        if ($request->input('create_owner') === '1') {
            [$owner, $ownerErrors] = $this->validateOwner($request);
            $errors += $ownerErrors;
        }
        $logoError = $this->checkLogo($request);
        if ($logoError !== null && $logoError !== 'none') {
            $errors['logo'] = __($logoError, ['max' => '2 Mo']);
        }
        if ($errors !== []) {
            return $this->form($request, null, $request->all(), $errors, 422, $partnerRequest);
        }

        $user = $this->user($request);
        $data += ['country_id' => $countryId, 'created_by_user_id' => $user->id, 'partner_request_id' => $partnerRequest['id'] ?? null];
        $id = $this->app->agencies()->save(null, $data, $zones);
        if ($logoError === null) {
            $this->storeLogo($request, $id, null);
        }
        $this->log($request, 'agency.created', 'agency', $id, $data['name'], ['after' => $data + ['zones' => implode(',', $zones)]]);

        if ($partnerRequest !== null) {
            $this->app->partnerRequests()->update((int) $partnerRequest['id'], 'approved', $partnerRequest['internal_notes'], $user->id, $id);
            $this->log($request, 'partner_request.approved', 'partner_request', (int) $partnerRequest['id'], $data['name']);
        }

        $this->flash('success', __('agencies.flash.created', ['name' => $data['name']]));
        if ($owner !== null) {
            $this->createAccount($request, $owner + ['role' => 'agency_owner', 'country_id' => $countryId, 'agency_id' => $id], $data['name']);
        }

        return $this->redirectToRoute('cmsadmin.agencies.edit', ['id' => $id], 303);
    }

    public function edit(Request $request, string $id): Response
    {
        $agency = $this->find((int) $id);

        return $this->form($request, $agency, $agency + ['zones' => $this->app->agencies()->zoneIds((int) $agency['id'])]);
    }

    public function update(Request $request, string $id): Response
    {
        $agency = $this->find((int) $id);
        [$data, $zones, $errors] = $this->validate($request, (int) $agency['country_id'], $agency);
        $logoError = $this->checkLogo($request);
        if ($logoError !== null && $logoError !== 'none') {
            $errors['logo'] = __($logoError, ['max' => '2 Mo']);
        }
        if ($errors !== []) {
            return $this->form($request, $agency, $request->all() + ['zones' => []] + $agency, $errors, 422);
        }

        $repo = $this->app->agencies();
        $before = $agency + ['zones' => implode(',', $repo->zoneIds((int) $agency['id']))];
        $repo->save((int) $agency['id'], $data, $zones);

        if ($logoError === null) {
            $this->storeLogo($request, (int) $agency['id'], $agency['logo_path']);
        } elseif ($request->input('remove_logo') === '1') {
            $this->removeLogo((int) $agency['id'], $agency['logo_path']);
        }

        $this->log($request, 'agency.updated', 'agency', (int) $agency['id'], $data['name'], $this->diff($before, $data + ['zones' => implode(',', $zones)]));
        if ($agency['status'] !== $data['status']) {
            $this->log($request, 'agency.status_' . $data['status'], 'agency', (int) $agency['id'], $data['name']);
        }
        $this->flash('success', __('agencies.flash.updated', ['name' => $data['name']]));

        return $this->redirectToRoute('cmsadmin.agencies.edit', ['id' => (int) $agency['id']], 303);
    }

    public function destroy(Request $request, string $id): Response
    {
        $agency = $this->find((int) $id);
        $repo = $this->app->agencies();
        $properties = $repo->propertiesCount((int) $agency['id']);
        if ($properties > 0) {
            $this->flash('error', __('agencies.flash.has_properties', ['name' => $agency['name'], 'count' => $properties]));

            return $this->redirectToRoute('cmsadmin.agencies.edit', ['id' => (int) $agency['id']], 303);
        }

        $users = $this->app->users();
        foreach ($users->forAgency((int) $agency['id']) as $account) {
            $users->softDelete((int) $account['id']);
        }
        $repo->softDelete((int) $agency['id']);
        $this->log($request, 'agency.deleted', 'agency', (int) $agency['id'], (string) $agency['name'], ['before' => $agency]);
        $this->flash('success', __('agencies.flash.deleted', ['name' => $agency['name']]));

        return $this->redirectToRoute('cmsadmin.agencies.index', status: 303);
    }

    /**
     * @param array<string, mixed>|null $agency
     * @param array<string, mixed>      $values
     * @param array<string, string>     $errors
     * @param array<string, mixed>|null $partnerRequest
     */
    private function form(Request $request, ?array $agency, array $values, array $errors = [], int $status = 200, ?array $partnerRequest = null): Response
    {
        $countryId = $this->countryId();
        $geo = $this->app->geo();

        return $this->render($request, 'agencies/form', [
            'agency' => $agency,
            'values' => $values,
            'errors' => $errors,
            'cities' => $geo->cityOptions($countryId, true),
            'communes' => $geo->communeOptions($countryId),
            'accounts' => $agency !== null ? $this->app->users()->forAgency((int) $agency['id']) : [],
            'propertiesCount' => $agency !== null ? $this->app->agencies()->propertiesCount((int) $agency['id']) : 0,
            'partnerRequest' => $partnerRequest,
            'statuses' => array_combine(AgencyRepository::STATUSES, array_map(static fn (string $s): string => __('agencies.status.' . $s), AgencyRepository::STATUSES)),
        ], [
            'title' => $agency !== null ? $agency['name'] : __('agencies.create'),
            'activeMenu' => 'agencies.all',
            'plugins' => ['select2'],
        ], $status);
    }

    /**
     * @param array<string, mixed>|null $agency
     * @return array{0: array<string, mixed>, 1: list<int>, 2: array<string, string>}
     */
    private function validate(Request $request, int $countryId, ?array $agency): array
    {
        $input = $request->all();
        if (trim((string) ($input['slug'] ?? '')) === '') {
            $input['slug'] = Str::slug((string) ($input['name'] ?? ''), 170);
        }
        $website = trim((string) ($input['website'] ?? ''));
        if ($website !== '' && !preg_match('#^https?://#i', $website)) {
            $input['website'] = 'https://' . $website;
        }

        $v = new Validator($input);
        $v->required('name')->maxLength('name', 150)
            ->required('slug')->maxLength('slug', 170)->slug('slug')
            ->maxLength('legal_name', 190)->maxLength('rccm', 60)->maxLength('tax_id', 60)
            ->maxLength('description', 5000)
            ->maxLength('email', 190)->email('email')
            ->maxLength('phone', 30)->phone('phone')
            ->maxLength('whatsapp', 30)->phone('whatsapp')
            ->maxLength('website', 255)->rule('website', $v->string('website') === '' || filter_var($v->string('website'), FILTER_VALIDATE_URL) !== false, __('validation.url'))
            ->maxLength('address', 255)
            ->required('status')->in('status', AgencyRepository::STATUSES);

        if (!$v->has('slug') && $this->app->agencies()->slugExists($countryId, $v->string('slug'), $agency !== null ? (int) $agency['id'] : null)) {
            $v->add('slug', __('validation.unique'));
        }

        $geo = $this->app->geo();
        $cityId = $v->nullableInt('city_id');
        $v->rule('city_id', $cityId === null || $geo->city($cityId, $countryId) !== null, __('validation.in'));
        $communeId = $v->nullableInt('commune_id');
        $commune = $communeId !== null ? $geo->commune($communeId, $countryId) : null;
        $v->rule('commune_id', $communeId === null || ($commune !== null && ($cityId === null || (int) $commune['city_id'] === $cityId)), __('agencies.commune_not_in_city'));
        if ($commune !== null && $cityId === null) {
            $cityId = (int) $commune['city_id'];
        }

        $allowedZones = $geo->communeOptions($countryId);
        $zones = array_values(array_filter(array_map('intval', $v->list('zones')), static fn (int $id): bool => isset($allowedZones[$id])));

        $verified = $v->bool('is_verified');
        $data = [
            'name' => $v->string('name'),
            'slug' => $v->string('slug'),
            'legal_name' => $v->nullableString('legal_name'),
            'rccm' => $v->nullableString('rccm'),
            'tax_id' => $v->nullableString('tax_id'),
            'description' => $v->nullableString('description'),
            'email' => $v->nullableString('email') !== null ? mb_strtolower($v->string('email')) : null,
            'phone' => $v->nullableString('phone'),
            'whatsapp' => $v->nullableString('whatsapp'),
            'website' => $v->nullableString('website'),
            'address' => $v->nullableString('address'),
            'city_id' => $cityId,
            'commune_id' => $communeId,
            'status' => $v->string('status'),
            'is_verified' => $verified ? 1 : 0,
            'verified_at' => $verified ? ($agency['verified_at'] ?? gmdate('Y-m-d H:i:s')) : null,
            'is_featured' => $v->bool('is_featured') && $v->string('status') === 'active' ? 1 : 0,
        ];

        return [$data, $zones, $v->errors()];
    }

    /**
     * Compte du responsable créé en même temps que l'agence.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validateOwner(Request $request): array
    {
        $v = new Validator([
            'first_name' => $request->input('owner_first_name'),
            'last_name' => $request->input('owner_last_name'),
            'email' => $request->input('owner_email'),
        ]);
        $v->required('first_name', 'last_name', 'email')->maxLength('first_name', 80)->maxLength('last_name', 80)->maxLength('email', 190)->email('email');
        if (!$v->has('email') && $this->app->users()->emailExists($v->string('email'))) {
            $v->add('email', __('users.email_taken'));
        }

        $errors = [];
        foreach ($v->errors() as $field => $message) {
            $errors['owner_' . $field] = $message;
        }

        return [[
            'first_name' => $v->string('first_name'),
            'last_name' => $v->string('last_name'),
            'email' => mb_strtolower($v->string('email')),
        ], $errors];
    }

    /** @return array{0: string, 1: string} Prénom, nom (« Awa Koné Traoré » → « Awa », « Koné Traoré ») */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return [(string) array_shift($parts), implode(' ', $parts)];
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        return $this->app->agencies()->find($id, $this->countryId()) ?? throw new HttpException(404);
    }
}
