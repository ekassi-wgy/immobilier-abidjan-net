<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Geo;

use App\Controllers\Cmsadmin\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\GeoRepository;
use App\Support\Paginator;
use App\Support\Str;
use App\Support\Validator;

/**
 * CRUD du référentiel géographique, commun aux trois niveaux (villes, communes, quartiers).
 *
 * Accès : Super Admin et Admin Pays (RequireRole:staff). Tout est limité au pays de travail :
 * celui de l'Admin Pays, ou le pays choisi par le Super Admin (par défaut celui du site).
 * Un élément utilisé (annonce, agence, sous-niveau) ne peut pas être supprimé : il se désactive.
 */
abstract class GeoController extends Controller
{
    private const SESSION_COUNTRY = 'cmsadmin.geo_country';
    private const PER_PAGE = 30;

    /** city | commune | district */
    abstract protected function level(): string;

    /** Segment d'URL : villes | communes | quartiers */
    abstract protected function path(): string;

    public function index(Request $request): Response
    {
        $country = $this->country($request);
        $filters = $this->filters($request);
        $repo = $this->app->geo();

        $method = ['city' => 'cities', 'commune' => 'communes', 'district' => 'districts'][$this->level()];
        $requested = Paginator::fromRequest($request, PHP_INT_MAX, self::PER_PAGE);
        $result = $repo->{$method}($country['id'], $filters, self::PER_PAGE, $requested->offset);
        $paginator = new Paginator($result['total'], self::PER_PAGE, $requested->page);
        if ($paginator->page !== $requested->page) {
            // Page au-delà de la dernière : on affiche la dernière
            $result = $repo->{$method}($country['id'], $filters, self::PER_PAGE, $paginator->offset);
        }

        return $this->render($request, 'geo/index', [
            'level' => $this->level(),
            'path' => $this->path(),
            'rows' => $result['rows'],
            'filters' => $filters,
            'pagination' => $paginator->toArray(),
            'country' => $country,
            'countries' => $this->user($request)->isSuperAdmin() ? $this->app->countries()->options() : [],
            'cities' => $this->level() !== 'city' ? $repo->cityOptions($country['id']) : [],
            'communes' => $this->level() === 'district' ? $repo->communeOptions($country['id'], $filters['ville'] ?: null) : [],
        ], [
            'title' => __("geo.{$this->level()}.plural"),
            'activeMenu' => 'geo.' . ['city' => 'cities', 'commune' => 'communes', 'district' => 'districts'][$this->level()],
            'plugins' => ['select2'],
        ]);
    }

    public function create(Request $request): Response
    {
        $country = $this->country($request);
        $defaults = ['is_active' => 1, 'sort_order' => 0, 'parent_id' => $request->query($this->level() === 'commune' ? 'ville' : 'commune')];

        return $this->form($request, $country, null, $defaults);
    }

    public function store(Request $request): Response
    {
        $country = $this->country($request);
        [$data, $errors] = $this->validate($request, $country['id'], null);
        if ($errors !== []) {
            return $this->form($request, $country, null, $request->all(), $errors, 422);
        }

        $id = $this->app->geo()->insert($this->level(), $data);
        $this->log($request, "{$this->level()}.created", $this->level(), $id, $data['name'], ['after' => $data], $country['id']);
        $this->flash('success', __('geo.flash.created', ['name' => $data['name']]));

        return $request->input('_intent') === 'another'
            ? Response::redirect(cmsadmin_url("geo/{$this->path()}/ajouter") . $this->parentQuery($data), 303)
            : $this->backTo($request, "/cmsadmin/geo/{$this->path()}");
    }

    public function edit(Request $request, string $id): Response
    {
        $country = $this->country($request);

        return $this->form($request, $country, $this->find((int) $id, $country['id']));
    }

    public function update(Request $request, string $id): Response
    {
        $country = $this->country($request);
        $item = $this->find((int) $id, $country['id']);
        [$data, $errors] = $this->validate($request, $country['id'], $item);
        if ($errors !== []) {
            return $this->form($request, $country, $item, $request->all(), $errors, 422);
        }

        $this->app->geo()->update($this->level(), (int) $item['id'], $data);
        $this->log($request, "{$this->level()}.updated", $this->level(), (int) $item['id'], $data['name'], $this->diff($item, $data), $country['id']);
        $this->flash('success', __('geo.flash.updated', ['name' => $data['name']]));

        return $this->backTo($request, "/cmsadmin/geo/{$this->path()}");
    }

    public function toggle(Request $request, string $id): Response
    {
        $country = $this->country($request);
        $item = $this->find((int) $id, $country['id']);
        $active = !(bool) $item['is_active'];

        $this->app->geo()->setActive($this->level(), (int) $item['id'], $active);
        $this->log($request, $this->level() . ($active ? '.activated' : '.deactivated'), $this->level(), (int) $item['id'], (string) $item['name'], null, $country['id']);
        $this->flash('success', __($active ? 'geo.flash.activated' : 'geo.flash.deactivated', ['name' => $item['name']]));

        return $this->backTo($request, "/cmsadmin/geo/{$this->path()}");
    }

    public function destroy(Request $request, string $id): Response
    {
        $country = $this->country($request);
        $item = $this->find((int) $id, $country['id']);
        $usage = $this->app->geo()->usage($this->level(), (int) $item['id']);

        if ($usage !== []) {
            $details = implode(', ', array_map(static fn (string $key, int $count): string => __($key, ['count' => $count]), array_keys($usage), $usage));
            $this->flash('error', __('geo.flash.in_use', ['name' => $item['name'], 'details' => $details]));

            return $this->backTo($request, "/cmsadmin/geo/{$this->path()}");
        }

        $this->app->geo()->delete($this->level(), (int) $item['id']);
        $this->log($request, "{$this->level()}.deleted", $this->level(), (int) $item['id'], (string) $item['name'], ['before' => $item], $country['id']);
        $this->flash('success', __('geo.flash.deleted', ['name' => $item['name']]));

        return $this->backTo($request, "/cmsadmin/geo/{$this->path()}");
    }

    /** Super Admin : choix du pays de travail du référentiel (mémorisé en session). */
    public function switchCountry(Request $request): Response
    {
        if (!$this->user($request)->isSuperAdmin()) {
            throw new HttpException(403);
        }
        $countryId = (int) $request->input('country_id', 0);
        if (isset($this->app->countries()->options()[$countryId])) {
            $this->app->session()->set(self::SESSION_COUNTRY, $countryId);
        }

        return $this->backTo($request, "/cmsadmin/geo/{$this->path()}");
    }

    /**
     * @param array<string, mixed>      $country
     * @param array<string, mixed>|null $item
     * @param array<string, mixed>      $values
     * @param array<string, string>     $errors
     */
    private function form(Request $request, array $country, ?array $item, array $values = [], array $errors = [], int $status = 200): Response
    {
        $repo = $this->app->geo();
        $parentKey = ['city' => null, 'commune' => 'city_id', 'district' => 'commune_id'][$this->level()];

        return $this->render($request, 'geo/form', [
            'level' => $this->level(),
            'path' => $this->path(),
            'item' => $item,
            'values' => $values + ($item ?? []) + ($parentKey !== null && isset($values['parent_id']) ? [$parentKey => $values['parent_id']] : []),
            'errors' => $errors,
            'country' => $country,
            'parents' => match ($this->level()) {
                'commune' => $repo->cityOptions($country['id']),
                'district' => $repo->communeOptions($country['id']),
                default => [],
            },
            'usage' => $item !== null ? $repo->usage($this->level(), (int) $item['id']) : [],
            'back' => $this->backPath($request),
        ], [
            'title' => $item !== null ? __("geo.{$this->level()}.edit", ['name' => $item['name']]) : __("geo.{$this->level()}.create"),
            'activeMenu' => 'geo.' . ['city' => 'cities', 'commune' => 'communes', 'district' => 'districts'][$this->level()],
            'plugins' => ['select2'],
        ], $status);
    }

    /**
     * @param array<string, mixed>|null $item
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validate(Request $request, int $countryId, ?array $item): array
    {
        $input = $request->all();
        if (trim((string) ($input['slug'] ?? '')) === '') {
            $input['slug'] = Str::slug((string) ($input['name'] ?? ''));
        }

        $v = new Validator($input);
        $v->required('name')->maxLength('name', 100)
            ->required('slug')->maxLength('slug', 120)->slug('slug')
            ->decimal('latitude', -90, 90)->decimal('longitude', -180, 180)
            ->integer('sort_order', -32768, 32767);

        $repo = $this->app->geo();
        $data = [
            'name' => $v->string('name'),
            'slug' => $v->string('slug'),
            'latitude' => $v->nullableDecimal('latitude'),
            'longitude' => $v->nullableDecimal('longitude'),
            'sort_order' => $v->int('sort_order'),
            'is_active' => $v->bool('is_active') ? 1 : 0,
        ];

        $parentId = $countryId;
        if ($this->level() === 'city') {
            $data = ['country_id' => $countryId] + $data;
        } else {
            $parentField = $this->level() === 'commune' ? 'city_id' : 'commune_id';
            $parentId = $v->int($parentField);
            $validParent = $this->level() === 'commune'
                ? $repo->city($parentId, $countryId) !== null
                : $repo->commune($parentId, $countryId) !== null;
            $v->required($parentField)->rule($parentField, $v->string($parentField) === '' || $validParent, __('validation.in'));
            $data = [$parentField => $parentId] + $data;
        }

        if (!$v->has('slug') && !$v->has('city_id') && !$v->has('commune_id')
            && $repo->slugExists($this->level(), $parentId, $data['slug'], $item !== null ? (int) $item['id'] : null)) {
            $v->add('slug', __('geo.slug_taken'));
        }

        return [$data, $v->errors()];
    }

    /** @return array<string, mixed> */
    private function find(int $id, int $countryId): array
    {
        $item = match ($this->level()) {
            'city' => $this->app->geo()->city($id, $countryId),
            'commune' => $this->app->geo()->commune($id, $countryId),
            default => $this->app->geo()->district($id, $countryId),
        };

        return $item ?? throw new HttpException(404);
    }

    /**
     * Pays de travail : celui de l'Admin Pays, ou celui choisi par le Super Admin (défaut : pays du site).
     *
     * @return array{id: int, name: string, iso2: string}
     */
    protected function country(Request $request): array
    {
        $user = $this->user($request);
        $countries = $this->app->countries()->all();
        $id = $user->isSuperAdmin()
            ? (int) $this->app->session()->get(self::SESSION_COUNTRY, site()?->country->id)
            : (int) $user->countryId;

        $country = $countries[$id] ?? $countries[(int) site()?->country->id] ?? null;
        if ($country === null) {
            throw new HttpException(403);
        }

        return ['id' => (int) $country['id'], 'name' => (string) $country['name'], 'iso2' => (string) $country['iso2']];
    }

    /** @return array{q: string, etat: string, ville: int, commune: int} */
    private function filters(Request $request): array
    {
        $etat = (string) $request->query('etat', '');

        return [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'etat' => in_array($etat, ['actifs', 'inactifs'], true) ? $etat : '',
            'ville' => max(0, (int) $request->query('ville', 0)),
            'commune' => max(0, (int) $request->query('commune', 0)),
        ];
    }

    /** @param array<string, mixed> $data */
    private function parentQuery(array $data): string
    {
        return match ($this->level()) {
            'commune' => '?ville=' . $data['city_id'],
            'district' => '?commune=' . $data['commune_id'],
            default => '',
        };
    }
}
