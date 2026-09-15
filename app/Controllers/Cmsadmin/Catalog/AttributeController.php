<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Catalog;

use App\Controllers\Cmsadmin\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogRepository;
use App\Support\Paginator;
use App\Support\Str;
use App\Support\Validator;

/**
 * Critères dynamiques des annonces (EAV) et leurs options. Réservé au Super Admin.
 *
 * Verrous : le code et le mode de stockage sont fixés à la création ; le type de saisie ne change plus
 * dès qu'une annonce a une valeur pour ce critère.
 */
final class AttributeController extends Controller
{
    use CatalogSupport;

    private const PER_PAGE = 30;

    public function index(Request $request): Response
    {
        $catalog = $this->app->catalog();
        $groups = $catalog->attributeGroups();
        $etat = (string) $request->query('etat', '');
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'groupe' => isset($groups[(int) $request->query('groupe', 0)]) ? (int) $request->query('groupe') : 0,
            'etat' => in_array($etat, ['actifs', 'inactifs'], true) ? $etat : '',
        ];

        $requested = Paginator::fromRequest($request, PHP_INT_MAX, self::PER_PAGE);
        $result = $catalog->attributes($filters, self::PER_PAGE, $requested->offset);
        $paginator = new Paginator($result['total'], self::PER_PAGE, $requested->page);
        if ($paginator->page !== $requested->page) {
            $result = $catalog->attributes($filters, self::PER_PAGE, $paginator->offset);
        }

        return $this->render($request, 'catalog/attributes/index', [
            'rows' => $result['rows'],
            'filters' => $filters,
            'groups' => array_map(static fn (array $group): string => (string) $group['name'], $groups),
            'pagination' => $paginator->toArray(),
        ], [
            'title' => __('catalog.attributes.title'),
            'activeMenu' => 'catalog.attributes',
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, null, ['is_active' => 1, 'is_public' => 1, 'is_filterable' => 0, 'storage' => 'eav', 'input_type' => 'select', 'sort_order' => 0, 'options' => []]);
    }

    public function store(Request $request): Response
    {
        [$data, $options, $errors] = $this->validate($request, null);
        if ($errors !== []) {
            return $this->form($request, null, $this->resubmitted($request), $errors, 422);
        }

        $id = $this->app->catalog()->saveAttribute(null, $data, $options);
        $this->log($request, 'attribute.created', 'attribute', $id, $data['name'], ['after' => $data + ['options' => array_column($options, 'code')]]);
        $this->flash('success', __('catalog.flash.created', ['name' => $data['name']]));

        return $this->redirectToRoute('cmsadmin.catalog.attributes.index', status: 303);
    }

    public function edit(Request $request, string $id): Response
    {
        $attribute = $this->find((int) $id);
        $options = array_map(fn (array $option): array => $option + ['label_en' => $this->translation($option['label_translations'])], $this->app->catalog()->attributeOptions((int) $attribute['id']));

        return $this->form($request, $attribute, $attribute + ['name_en' => $this->translation($attribute['name_translations']), 'options' => $options]);
    }

    public function update(Request $request, string $id): Response
    {
        $attribute = $this->find((int) $id);
        [$data, $options, $errors] = $this->validate($request, $attribute);
        if ($errors !== []) {
            return $this->form($request, $attribute, $this->resubmitted($request) + $attribute, $errors, 422);
        }

        $catalog = $this->app->catalog();
        $beforeOptions = implode(',', array_column($catalog->attributeOptions((int) $attribute['id']), 'code'));
        $catalog->saveAttribute((int) $attribute['id'], $data, $options);
        $this->log($request, 'attribute.updated', 'attribute', (int) $attribute['id'], $data['name'], $this->diff($attribute + ['options' => $beforeOptions], $data + ['options' => implode(',', array_column($options, 'code'))]));
        $this->flash('success', __('catalog.flash.updated', ['name' => $data['name']]));

        return $this->redirectToRoute('cmsadmin.catalog.attributes.index', status: 303);
    }

    public function toggle(Request $request, string $id): Response
    {
        $attribute = $this->find((int) $id);
        $active = !(bool) $attribute['is_active'];
        $this->app->catalog()->setActive('property_attributes', (int) $attribute['id'], $active);
        $this->log($request, $active ? 'attribute.activated' : 'attribute.deactivated', 'attribute', (int) $attribute['id'], (string) $attribute['name']);
        $this->flash('success', __($active ? 'catalog.flash.activated' : 'catalog.flash.deactivated', ['name' => $attribute['name']]));

        return $this->backTo($request, '/cmsadmin/criteres');
    }

    public function destroy(Request $request, string $id): Response
    {
        $attribute = $this->find((int) $id);
        $usage = $this->app->catalog()->attributeUsage((int) $attribute['id']);
        if ($usage !== []) {
            $this->flash('error', __('catalog.flash.in_use', ['name' => $attribute['name'], 'details' => $this->usageText($usage)]));

            return $this->backTo($request, '/cmsadmin/criteres');
        }

        $this->app->catalog()->delete('property_attributes', (int) $attribute['id']);
        $this->log($request, 'attribute.deleted', 'attribute', (int) $attribute['id'], (string) $attribute['name'], ['before' => $attribute]);
        $this->flash('success', __('catalog.flash.deleted', ['name' => $attribute['name']]));

        return $this->backTo($request, '/cmsadmin/criteres');
    }

    /**
     * @param array<string, mixed>|null $attribute
     * @param array<string, mixed>      $values
     * @param array<string, string>     $errors
     */
    private function form(Request $request, ?array $attribute, array $values, array $errors = [], int $status = 200): Response
    {
        $catalog = $this->app->catalog();
        $valuesCount = $attribute !== null ? $catalog->attributeValuesCount((int) $attribute['id']) : 0;

        return $this->render($request, 'catalog/attributes/form', [
            'attribute' => $attribute,
            'values' => $values,
            'errors' => $errors,
            'groups' => array_map(static fn (array $group): string => (string) $group['name'], $catalog->attributeGroups()),
            'inputTypes' => array_combine(CatalogRepository::INPUT_TYPES, array_map(static fn (string $type): string => __('catalog.attributes.types.' . $type), CatalogRepository::INPUT_TYPES)),
            'columns' => array_combine(CatalogRepository::COLUMN_STORAGE, array_map(static fn (string $column): string => __('catalog.attributes.columns.' . $column), CatalogRepository::COLUMN_STORAGE)),
            'typeLocked' => $valuesCount > 0,
            'valuesCount' => $valuesCount,
            'categories' => $attribute !== null ? $this->app->db()->select(
                'SELECT c.id, c.name, ca.is_required FROM category_attributes ca JOIN property_categories c ON c.id = ca.category_id WHERE ca.attribute_id = :id ORDER BY c.sort_order, c.name',
                ['id' => $attribute['id']]
            ) : [],
        ], [
            'title' => $attribute !== null ? __('catalog.attributes.edit', ['name' => $attribute['name']]) : __('catalog.attributes.create'),
            'activeMenu' => 'catalog.attributes',
        ], $status);
    }

    /**
     * @param array<string, mixed>|null $attribute
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>, 2: array<string, string>}
     */
    private function validate(Request $request, ?array $attribute): array
    {
        $catalog = $this->app->catalog();
        $input = $request->all();
        if ($attribute === null && trim((string) ($input['code'] ?? '')) === '') {
            $input['code'] = Str::code((string) ($input['name'] ?? ''), 60);
        }

        $v = new Validator($input);
        $v->required('name')->maxLength('name', 100)->maxLength('name_en', 100)
            ->required('group_id')->in('group_id', array_keys($catalog->attributeGroups()))
            ->maxLength('unit', 20)->maxLength('help_text', 255)
            ->decimal('min_value', -999999999999, 999999999999)->decimal('max_value', -999999999999, 999999999999)
            ->integer('sort_order', -32768, 32767);

        $typeLocked = $attribute !== null && $catalog->attributeValuesCount((int) $attribute['id']) > 0;
        $inputType = $typeLocked ? (string) $attribute['input_type'] : $v->string('input_type');
        if (!$typeLocked) {
            $v->required('input_type')->in('input_type', CatalogRepository::INPUT_TYPES);
        }

        if ($attribute === null) {
            $v->required('code')->maxLength('code', 60)->code('code');
            if (!$v->has('code') && $catalog->valueExists('property_attributes', 'code', $v->string('code'))) {
                $v->add('code', __('validation.unique'));
            }
            $v->in('storage', ['eav', 'column']);
            $storage = $v->string('storage') === 'column' ? 'column' : 'eav';
            $columnName = $storage === 'column' ? $v->string('column_name') : null;
            if ($storage === 'column') {
                $v->required('column_name')->in('column_name', CatalogRepository::COLUMN_STORAGE);
                $v->rule('input_type', in_array($inputType, ['integer', 'decimal'], true), __('catalog.attributes.column_numeric'));
                $taken = $this->app->db()->scalar('SELECT name FROM property_attributes WHERE column_name = :column', ['column' => $columnName]);
                $v->rule('column_name', $taken === null, __('catalog.attributes.column_taken', ['name' => (string) $taken]));
            }
        } else {
            $storage = (string) $attribute['storage'];
            $columnName = $attribute['column_name'];
        }

        $min = $v->nullableDecimal('min_value');
        $max = $v->nullableDecimal('max_value');
        $v->rule('max_value', $min === null || $max === null || (float) $max >= (float) $min, __('catalog.attributes.max_below_min'));

        // Options (listes à choix)
        $options = [];
        $existingOptions = $attribute !== null ? array_column($catalog->attributeOptions((int) $attribute['id']), null, 'id') : [];
        if (in_array($inputType, ['select', 'multiselect'], true)) {
            $codes = [];
            foreach ((array) ($input['options'] ?? []) as $key => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $label = trim((string) ($row['label'] ?? ''));
                $code = trim((string) ($row['code'] ?? ''));
                if ($label === '' && $code === '') {
                    continue;
                }
                $code = $code !== '' ? $code : Str::code($label, 60);
                $optionId = isset($row['id']) && isset($existingOptions[(int) $row['id']]) ? (int) $row['id'] : null;

                if ($label === '' || mb_strlen($label) > 120) {
                    $v->add("options.{$key}.label", $label === '' ? __('validation.required') : __('validation.max_length', ['max' => 120]));
                }
                if (preg_match('/^[a-z][a-z0-9_]{0,59}$/', $code) !== 1) {
                    $v->add("options.{$key}.code", __('validation.code'));
                } elseif (isset($codes[$code])) {
                    $v->add("options.{$key}.code", __('validation.unique'));
                }
                $codes[$code] = true;
                $labelEn = trim((string) ($row['label_en'] ?? ''));

                $options[] = [
                    'id' => $optionId,
                    'code' => $code,
                    'label' => $label,
                    'label_translations' => $this->encodeTranslation($labelEn !== '' ? mb_substr($labelEn, 0, 120) : null),
                    'sort_order' => max(-32768, min(32767, (int) ($row['sort_order'] ?? 0))),
                    'is_active' => !empty($row['is_active']) ? 1 : 0,
                ];
            }
            $v->rule('options', $options !== [], __('catalog.attributes.options_required'));
        }

        $data = [
            'group_id' => $v->int('group_id'),
            'name' => $v->string('name'),
            'name_translations' => $this->encodeTranslation($v->nullableString('name_en')),
            'input_type' => $inputType,
            'unit' => $v->nullableString('unit'),
            'min_value' => $min,
            'max_value' => $max,
            'help_text' => $v->nullableString('help_text'),
            'is_filterable' => $v->bool('is_filterable') ? 1 : 0,
            'is_public' => $v->bool('is_public') ? 1 : 0,
            'is_active' => $v->bool('is_active') ? 1 : 0,
            'sort_order' => $v->int('sort_order'),
        ];
        if ($attribute === null) {
            $data = ['code' => $v->string('code'), 'storage' => $storage, 'column_name' => $columnName] + $data;
        }

        return [$data, $options, $v->errors()];
    }

    /** @return array<string, mixed> */
    private function resubmitted(Request $request): array
    {
        $values = $request->all();
        $values['options'] = array_values(array_filter((array) ($values['options'] ?? []), 'is_array'));

        return $values;
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        return $this->app->catalog()->attribute($id) ?? throw new HttpException(404);
    }
}
