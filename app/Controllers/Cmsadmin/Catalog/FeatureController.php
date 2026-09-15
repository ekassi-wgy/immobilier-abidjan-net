<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Catalog;

use App\Controllers\Cmsadmin\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogRepository;
use App\Support\Str;
use App\Support\Validator;

/**
 * Équipements (cases à cocher filtrables : piscine, climatisation, gardiennage…). Réservé au Super Admin.
 */
final class FeatureController extends Controller
{
    use CatalogSupport;

    public function index(Request $request): Response
    {
        $etat = (string) $request->query('etat', '');
        $groupe = (string) $request->query('groupe', '');
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'groupe' => in_array($groupe, CatalogRepository::FEATURE_GROUPS, true) ? $groupe : '',
            'etat' => in_array($etat, ['actifs', 'inactifs'], true) ? $etat : '',
        ];

        return $this->render($request, 'catalog/features/index', [
            'rows' => $this->app->catalog()->features($filters),
            'filters' => $filters,
            'groups' => $this->groups(),
        ], [
            'title' => __('catalog.features.title'),
            'activeMenu' => 'catalog.features',
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, null, ['is_active' => 1, 'is_filterable' => 1, 'sort_order' => 0, 'feature_group' => $request->query('groupe', 'comfort')]);
    }

    public function store(Request $request): Response
    {
        [$data, $errors] = $this->validate($request, null);
        if ($errors !== []) {
            return $this->form($request, null, $request->all(), $errors, 422);
        }

        $id = $this->app->catalog()->saveFeature(null, $data);
        $this->log($request, 'feature.created', 'feature', $id, $data['name'], ['after' => $data]);
        $this->flash('success', __('catalog.flash.created', ['name' => $data['name']]));

        return $this->redirectToRoute('cmsadmin.catalog.features.index', status: 303);
    }

    public function edit(Request $request, string $id): Response
    {
        $feature = $this->find((int) $id);

        return $this->form($request, $feature, $feature + ['name_en' => $this->translation($feature['name_translations'])]);
    }

    public function update(Request $request, string $id): Response
    {
        $feature = $this->find((int) $id);
        [$data, $errors] = $this->validate($request, $feature);
        if ($errors !== []) {
            return $this->form($request, $feature, $request->all() + $feature, $errors, 422);
        }

        $this->app->catalog()->saveFeature((int) $feature['id'], $data);
        $this->log($request, 'feature.updated', 'feature', (int) $feature['id'], $data['name'], $this->diff($feature, $data));
        $this->flash('success', __('catalog.flash.updated', ['name' => $data['name']]));

        return $this->redirectToRoute('cmsadmin.catalog.features.index', status: 303);
    }

    public function toggle(Request $request, string $id): Response
    {
        $feature = $this->find((int) $id);
        $active = !(bool) $feature['is_active'];
        $this->app->catalog()->setActive('features', (int) $feature['id'], $active);
        $this->log($request, $active ? 'feature.activated' : 'feature.deactivated', 'feature', (int) $feature['id'], (string) $feature['name']);
        $this->flash('success', __($active ? 'catalog.flash.activated' : 'catalog.flash.deactivated', ['name' => $feature['name']]));

        return $this->backTo($request, '/cmsadmin/equipements');
    }

    public function destroy(Request $request, string $id): Response
    {
        $feature = $this->find((int) $id);
        $usage = $this->app->catalog()->featureUsage((int) $feature['id']);
        if ($usage !== []) {
            $this->flash('error', __('catalog.flash.in_use', ['name' => $feature['name'], 'details' => $this->usageText($usage)]));

            return $this->backTo($request, '/cmsadmin/equipements');
        }

        $this->app->catalog()->delete('features', (int) $feature['id']);
        $this->log($request, 'feature.deleted', 'feature', (int) $feature['id'], (string) $feature['name'], ['before' => $feature]);
        $this->flash('success', __('catalog.flash.deleted', ['name' => $feature['name']]));

        return $this->backTo($request, '/cmsadmin/equipements');
    }

    /**
     * @param array<string, mixed>|null $feature
     * @param array<string, mixed>      $values
     * @param array<string, string>     $errors
     */
    private function form(Request $request, ?array $feature, array $values, array $errors = [], int $status = 200): Response
    {
        return $this->render($request, 'catalog/features/form', [
            'feature' => $feature,
            'values' => $values,
            'errors' => $errors,
            'groups' => $this->groups(),
            'icons' => $this->icons(),
            'usage' => $feature !== null ? $this->app->catalog()->featureUsage((int) $feature['id']) : [],
        ], [
            'title' => $feature !== null ? __('catalog.features.edit', ['name' => $feature['name']]) : __('catalog.features.create'),
            'activeMenu' => 'catalog.features',
        ], $status);
    }

    /**
     * @param array<string, mixed>|null $feature
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validate(Request $request, ?array $feature): array
    {
        $catalog = $this->app->catalog();
        $input = $request->all();
        if ($feature === null && trim((string) ($input['code'] ?? '')) === '') {
            $input['code'] = Str::code((string) ($input['name'] ?? ''), 60);
        }

        $v = new Validator($input);
        $v->required('name')->maxLength('name', 100)->maxLength('name_en', 100)
            ->required('feature_group')->in('feature_group', CatalogRepository::FEATURE_GROUPS)
            ->integer('sort_order', -32768, 32767);
        if ($feature === null) {
            $v->required('code')->maxLength('code', 60)->code('code');
            if (!$v->has('code') && $catalog->valueExists('features', 'code', $v->string('code'))) {
                $v->add('code', __('validation.unique'));
            }
        }
        $icon = $v->nullableString('icon');
        $v->rule('icon', $this->validIcon($icon, $feature['icon'] ?? null), __('validation.in'));

        $data = [
            'name' => $v->string('name'),
            'name_translations' => $this->encodeTranslation($v->nullableString('name_en')),
            'feature_group' => $v->string('feature_group'),
            'icon' => $icon,
            'is_filterable' => $v->bool('is_filterable') ? 1 : 0,
            'is_active' => $v->bool('is_active') ? 1 : 0,
            'sort_order' => $v->int('sort_order'),
        ];
        if ($feature === null) {
            $data = ['code' => $v->string('code')] + $data;
        }

        return [$data, $v->errors()];
    }

    /** @return array<string, string> */
    private function groups(): array
    {
        return array_combine(CatalogRepository::FEATURE_GROUPS, array_map(static fn (string $group): string => __('catalog.features.groups.' . $group), CatalogRepository::FEATURE_GROUPS));
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        return $this->app->catalog()->feature($id) ?? throw new HttpException(404);
    }
}
