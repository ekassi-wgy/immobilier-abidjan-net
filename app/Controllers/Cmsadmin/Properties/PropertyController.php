<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Properties;

use App\Controllers\Cmsadmin\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PropertyPresenter;
use App\Services\PropertyRepository;
use App\Services\PropertyWorkflow;
use App\Support\Paginator;
use RuntimeException;

/**
 * Annonces : liste, fiche, création et modification (toutes les rôles, dans leur périmètre).
 * Les décisions de validation et changements de statut sont dans PropertyActionController.
 */
final class PropertyController extends Controller
{
    use PropertySupport;

    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $site = $this->site();
        $user = $this->user($request);
        $agencyId = $this->scopeAgency($request);
        $repo = $this->app->properties();

        $statut = (string) $request->query('statut', '');
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'statut' => in_array($statut, [...PropertyRepository::STATUSES, 'revision'], true) ? $statut : '',
            'categorie' => max(0, (int) $request->query('categorie', 0)),
            'commune' => max(0, (int) $request->query('commune', 0)),
            'agence' => $user->isStaff() ? max(0, (int) $request->query('agence', 0)) : 0,
            'une' => $request->query('une') === '1',
        ];

        $requested = Paginator::fromRequest($request, PHP_INT_MAX, self::PER_PAGE);
        $result = $repo->paginate($site->country->id, $agencyId, $filters, self::PER_PAGE, $requested->offset);
        $paginator = new Paginator($result['total'], self::PER_PAGE, $requested->page);
        if ($paginator->page !== $requested->page) {
            $result = $repo->paginate($site->country->id, $agencyId, $filters, self::PER_PAGE, $paginator->offset);
        }

        $roots = [];
        foreach ($this->app->catalog()->categoryTree() as $root) {
            $roots[(int) $root['id']] = (string) $root['name'];
        }

        return $this->render($request, 'properties/index', [
            'rows' => $result['rows'],
            'filters' => $filters,
            'counts' => $repo->countsByStatus($site->country->id, $agencyId),
            'categories' => $roots,
            'communes' => $this->app->geo()->communeOptions($site->country->id),
            'agencies' => $user->isStaff() ? $this->agencyOptions() : [],
            'pagination' => $paginator->toArray(),
            'isStaff' => $user->isStaff(),
        ], [
            'title' => __($user->isAgency() ? 'properties.title_agency' : 'properties.title'),
            'activeMenu' => $filters['statut'] === 'pending' ? 'properties.pending' : 'properties.all',
            'plugins' => ['select2'],
        ]);
    }

    public function show(Request $request, string $reference): Response
    {
        $site = $this->site();
        $property = $this->findProperty($request, $reference);
        $repo = $this->app->properties();
        $catalog = $this->app->catalog();
        $presenter = new PropertyPresenter($this->app->db(), $catalog);

        $schema = $catalog->formSchema((int) $property['category_id'], $site->country->id);
        $attributes = $schema['attributes'] ?? [];
        $values = $repo->attributeValues((int) $property['id'], $attributes, $property);
        $images = $repo->images((int) $property['id']);
        $featureIds = $repo->featureIds((int) $property['id']);

        $revision = $repo->pendingRevision((int) $property['id']);
        $diff = [];
        $revisionImages = [];
        if ($revision !== null) {
            $newSchema = $catalog->formSchema((int) ($revision['data']['fields']['category_id'] ?? 0), $site->country->id);
            $diff = $presenter->revisionDiff($property, $revision['data'], $attributes, $values, $newSchema['attributes'] ?? [], $featureIds, $images);
            $revisionImages = array_values(array_filter($repo->images((int) $property['id'], (int) $revision['id']), static fn (array $i): bool => (int) $i['revision_id'] === (int) $revision['id']));
        }

        $criteria = [];
        foreach ($schema['groups'] ?? [] as $group => $groupAttributes) {
            foreach ($groupAttributes as $attribute) {
                $formatted = $presenter->attributeValue($attribute, $values[(int) $attribute['id']] ?? null);
                if ($formatted !== null) {
                    $criteria[$group][] = ['label' => $attribute['name'], 'value' => $formatted, 'public' => (int) $attribute['is_public'] === 1];
                }
            }
        }

        $featureNames = [];
        foreach ($catalog->featureChoices() as $group) {
            foreach ($group as $id => $name) {
                if (in_array($id, $featureIds, true)) {
                    $featureNames[] = $name;
                }
            }
        }

        return $this->render($request, 'properties/show', [
            'property' => $property,
            'price' => $presenter->price($property['price'], $property['price_period']),
            'location' => $presenter->location($property),
            'criteria' => $criteria,
            'features' => $featureNames,
            'images' => array_map(fn (array $i): array => $i + ['url' => $this->imageUrl((string) $i['path'], 800), 'thumb' => $this->imageUrl((string) $i['path'])], $images),
            'private' => $repo->privateDetails((int) $property['id']),
            'history' => $repo->statusHistory((int) $property['id']),
            'revision' => $revision,
            'diff' => $diff,
            'revisionImages' => array_map(fn (array $i): array => $i + ['thumb' => $this->imageUrl((string) $i['path'])], $revisionImages),
            'lastRejectedRevision' => $repo->lastRejectedRevision((int) $property['id']),
            'lifetimeDays' => (int) settings('listing.lifetime_days', 90),
        ], [
            'title' => $property['reference'] . ' · ' . $property['title'],
            'activeMenu' => 'properties.all',
            'plugins' => ['leaflet'],
            'pageScripts' => ['js/property-show.js'],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $this->user($request);

        return $this->form($request, null, [
            'category_id' => max(0, (int) $request->query('categorie', 0)) ?: '',
            'source' => 'agency',
            'agency_id' => $user->agencyId,
            'availability' => 'available',
            'city_id' => $this->defaultCityId(),
        ]);
    }

    public function store(Request $request): Response
    {
        $site = $this->site();
        $user = $this->user($request);
        [$payload, $errors, $schema] = $this->app->propertyForm()->validate($request, $user, $site, null, [], $this->isDraft($request));
        if ($errors !== []) {
            return $this->form($request, null, $this->resubmitted($request), $errors, 422);
        }

        try {
            [$id, $outcome] = $this->app->workflow()->create($request, $user, $site, $payload, $schema['attributes'] ?? [], $this->isDraft($request));
        } catch (RuntimeException $exception) {
            $this->app->logger()->exception($exception, ['action' => 'property.create']);
            $this->flash('error', __('properties.flash.save_failed'));

            return $this->form($request, null, $this->resubmitted($request), [], 500);
        }

        $reference = (string) $this->app->db()->scalar('SELECT reference FROM properties WHERE id = :id', ['id' => $id]);
        $this->flash('success', __(match ($outcome) {
            PropertyWorkflow::OUTCOME_PUBLISHED => 'properties.flash.published',
            PropertyWorkflow::OUTCOME_DRAFT => 'properties.flash.draft_saved',
            default => 'properties.flash.submitted',
        }, ['ref' => $reference]));

        return $this->redirectToRoute('cmsadmin.properties.show', ['reference' => $reference], 303);
    }

    public function edit(Request $request, string $reference): Response
    {
        $site = $this->site();
        $user = $this->user($request);
        $property = $this->findProperty($request, $reference);
        if ($user->isAgency() && $property['status'] === 'archived') {
            $this->flash('error', __('properties.flash.archived_locked'));

            return $this->redirectToRoute('cmsadmin.properties.show', ['reference' => $reference]);
        }

        $repo = $this->app->properties();
        $revision = $user->isAgency() ? $repo->pendingRevision((int) $property['id']) : null;

        // Une agence reprend sa révision en cours là où elle l'a laissée
        if ($revision !== null) {
            $data = $revision['data'];
            $values = (array) $data['fields'] + $property;
            $values['attributes'] = $this->attributeInputs((int) ($data['fields']['category_id'] ?? $property['category_id']), (array) $data['attributes'], (array) $data['fields']);
            $values['features'] = (array) $data['features'];
            $images = $repo->images((int) $property['id'], (int) $revision['id']);
            $byId = array_column($images, null, 'id');
            $values['photos'] = array_values(array_filter(array_map(static fn (array $row): ?array => isset($byId[(int) $row['id']]) ? ['id' => (int) $row['id'], 'alt' => $row['alt'] ?? null] : null, (array) $data['images'])));
        } else {
            $values = $property;
            $schema = $this->app->catalog()->formSchema((int) $property['category_id'], $site->country->id);
            $values['attributes'] = $repo->attributeValues((int) $property['id'], $schema['attributes'] ?? [], $property);
            $values['features'] = $repo->featureIds((int) $property['id']);
            $values['photos'] = array_map(static fn (array $i): array => ['id' => (int) $i['id'], 'alt' => $i['alt_text']], $repo->images((int) $property['id']));
        }
        $values += $repo->privateDetails((int) $property['id']);
        $values['price_on_request'] = $property['price'] === null && $revision === null ? 1 : (($values['price'] ?? null) === null ? 1 : 0);
        if ($values['featured_until'] ?? null) {
            $values['featured_until'] = substr((string) $values['featured_until'], 0, 10);
        }

        return $this->form($request, $property, $values, [], 200, $revision);
    }

    public function update(Request $request, string $reference): Response
    {
        $site = $this->site();
        $user = $this->user($request);
        $property = $this->findProperty($request, $reference);
        if ($user->isAgency() && $property['status'] === 'archived') {
            throw new HttpException(403);
        }

        $repo = $this->app->properties();
        $revision = $repo->pendingRevision((int) $property['id']);
        $currentImages = $repo->images((int) $property['id'], $user->isAgency() && $revision !== null ? (int) $revision['id'] : null);

        [$payload, $errors, $schema] = $this->app->propertyForm()->validate($request, $user, $site, $property, $currentImages, $this->isDraft($request) && $property['status'] === 'draft');
        if ($errors !== []) {
            return $this->form($request, $property, $this->resubmitted($request) + ['document_path' => $property['document_path']], $errors, 422, $user->isAgency() ? $revision : null);
        }

        try {
            $outcome = $this->app->workflow()->update($request, $user, $site, $property, $payload, $schema['attributes'] ?? [], $this->isDraft($request));
        } catch (RuntimeException $exception) {
            $this->app->logger()->exception($exception, ['action' => 'property.update', 'reference' => $reference]);
            $this->flash('error', __('properties.flash.save_failed'));

            return $this->redirectToRoute('cmsadmin.properties.edit', ['reference' => $reference], 303);
        }

        $this->flash('success', __(match ($outcome) {
            PropertyWorkflow::OUTCOME_REVISION => 'properties.flash.revision_submitted',
            PropertyWorkflow::OUTCOME_RESUBMITTED => 'properties.flash.resubmitted',
            PropertyWorkflow::OUTCOME_DRAFT => 'properties.flash.draft_saved',
            PropertyWorkflow::OUTCOME_CREATED => 'properties.flash.submitted',
            PropertyWorkflow::OUTCOME_PUBLISHED => 'properties.flash.published',
            default => 'properties.flash.updated',
        }, ['ref' => $reference]));

        return $this->redirectToRoute('cmsadmin.properties.show', ['reference' => $reference], 303);
    }

    /** Bouton « Enregistrer en brouillon » : l'annonce n'est ni publiée ni transmise à Weblogy. */
    private function isDraft(Request $request): bool
    {
        return $request->input('intent') === 'draft';
    }

    /**
     * @param array<string, mixed>|null $property
     * @param array<string, mixed>      $values
     * @param array<string, string>     $errors
     * @param array<string, mixed>|null $revision Révision en cours (agence)
     */
    private function form(Request $request, ?array $property, array $values, array $errors = [], int $status = 200, ?array $revision = null): Response
    {
        $site = $this->site();
        $user = $this->user($request);
        $catalog = $this->app->catalog();
        $geo = $this->app->geo();
        $repo = $this->app->properties();
        $countryId = $site->country->id;

        $categoryId = (int) ($values['category_id'] ?? 0);
        $schema = $categoryId > 0 ? $catalog->formSchema($categoryId, $countryId) : null;
        $cityId = (int) ($values['city_id'] ?? 0);
        $communeId = (int) ($values['commune_id'] ?? 0);
        $agencyId = $user->isAgency() ? (int) $user->agencyId : (int) ($values['agency_id'] ?? 0);

        // Photos : en ligne / révision (identifiants) et envois en attente (jetons)
        $known = $property !== null ? array_column($repo->images((int) $property['id'], $revision !== null ? (int) $revision['id'] : null), null, 'id') : [];
        $photos = [];
        foreach ((array) ($values['photos'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!empty($row['id']) && isset($known[(int) $row['id']])) {
                $photos[] = ['id' => (int) $row['id'], 'token' => null, 'alt' => $row['alt'] ?? $known[(int) $row['id']]['alt_text'], 'thumb' => $this->imageUrl((string) $known[(int) $row['id']]['path'])];
            } elseif (!empty($row['token']) && is_string($row['token']) && ($pending = $this->app->pendingUploads()->get($row['token'])) !== null) {
                $photos[] = ['id' => null, 'token' => $row['token'], 'alt' => $row['alt'] ?? null, 'thumb' => $this->imageUrl((string) $pending['path'])];
            }
        }

        $agents = [];
        if ($agencyId > 0) {
            foreach ($this->app->users()->forAgency($agencyId) as $account) {
                if ((int) $account['is_active'] === 1) {
                    $agents[(int) $account['id']] = $account['first_name'] . ' ' . $account['last_name'];
                }
            }
        }

        return $this->render($request, 'properties/form', [
            'property' => $property,
            'values' => $values,
            'errors' => $errors,
            'revision' => $revision,
            'isStaff' => $user->isStaff(),
            'schema' => $schema,
            'categories' => $catalog->categoryChoices($countryId),
            'features' => $catalog->featureChoices(),
            'cities' => $geo->cityOptions($countryId, true),
            'communes' => $cityId > 0 ? $geo->communeOptions($countryId, $cityId) : [],
            'districts' => $communeId > 0 ? $this->districtOptions($communeId, $countryId) : [],
            'agencies' => $user->isStaff() ? $this->agencyOptions() : [],
            'agents' => $agents,
            'photos' => $photos,
            'maxPhotos' => (int) settings('listing.max_photos', 30),
            'maxPhotoMb' => (int) settings('listing.max_photo_size_mb', 10),
            'currency' => $site->country->currencySymbol,
            'mapCenter' => $this->mapCenter($values, $countryId),
        ], [
            'title' => $property !== null ? __('properties.edit_title', ['ref' => $property['reference']]) : __('properties.create_title'),
            'activeMenu' => $property !== null ? 'properties.all' : 'properties.create',
            'plugins' => ['select2', 'leaflet'],
            'pageScripts' => ['js/property-form.js'],
        ], $status);
    }

    /**
     * Valeurs de critères au format du formulaire, à partir d'une révision (colonnes comprises).
     *
     * @param array<int|string, mixed> $attributes
     * @param array<string, mixed>     $fields
     * @return array<int, mixed>
     */
    private function attributeInputs(int $categoryId, array $attributes, array $fields): array
    {
        $values = [];
        foreach ($attributes as $id => $value) {
            $values[(int) $id] = $value;
        }
        $schema = $this->app->catalog()->formSchema($categoryId, $this->site()->country->id);
        foreach ($schema['attributes'] ?? [] as $id => $attribute) {
            if ($attribute['storage'] === 'column') {
                $values[$id] = $fields[$attribute['column_name']] ?? null;
            }
        }

        return $values;
    }

    /** @return array<string, mixed> Valeurs ressaisies après une erreur de validation */
    private function resubmitted(Request $request): array
    {
        $values = $request->all();
        unset($values['_csrf']);
        $values['features'] = array_map('intval', (array) ($values['features'] ?? []));
        $values['photos'] = array_values(array_filter((array) ($values['photos'] ?? []), 'is_array'));
        $values['attributes'] = (array) ($values['attributes'] ?? []);

        return $values;
    }

    /** @return array<int, string> */
    private function agencyOptions(): array
    {
        $options = [];
        foreach ($this->app->agencies()->paginate($this->site()->country->id, ['statut' => 'active'], 1000, 0)['rows'] as $agency) {
            $options[(int) $agency['id']] = (string) $agency['name'];
        }

        return $options;
    }

    /** @return array<int, string> */
    private function districtOptions(int $communeId, int $countryId): array
    {
        $options = [];
        foreach ($this->app->geo()->districts($countryId, ['commune' => $communeId, 'etat' => 'actifs'], 500, 0)['rows'] as $district) {
            $options[(int) $district['id']] = (string) $district['name'];
        }

        return $options;
    }

    private function defaultCityId(): ?int
    {
        $cities = $this->app->geo()->cityOptions($this->site()->country->id, true);

        return $cities !== [] ? (int) array_key_first($cities) : null;
    }

    /**
     * Centre de la carte : coordonnées saisies, sinon quartier, commune ou ville.
     *
     * @param array<string, mixed> $values
     * @return array{lat: float, lng: float, zoom: int}
     */
    private function mapCenter(array $values, int $countryId): array
    {
        if (is_numeric($values['latitude'] ?? null) && is_numeric($values['longitude'] ?? null)) {
            return ['lat' => (float) $values['latitude'], 'lng' => (float) $values['longitude'], 'zoom' => 16];
        }
        $geo = $this->app->geo();
        foreach ([['district', 15], ['commune', 13], ['city', 11]] as [$level, $zoom]) {
            $id = (int) ($values[$level . '_id'] ?? 0);
            $row = $id > 0 ? $geo->{$level}($id, $countryId) : null;
            if ($row !== null && $row['latitude'] !== null) {
                return ['lat' => (float) $row['latitude'], 'lng' => (float) $row['longitude'], 'zoom' => $zoom];
            }
        }

        return ['lat' => 5.35995, 'lng' => -4.00826, 'zoom' => 11];
    }
}
