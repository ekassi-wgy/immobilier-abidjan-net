<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Catalog;

use App\Controllers\Cmsadmin\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Support\Str;
use App\Support\Validator;

/**
 * Catégories de biens (arborescence à deux niveaux), types de transaction autorisés et critères rattachés.
 * Réservé au Super Admin.
 */
final class CategoryController extends Controller
{
    use CatalogSupport;

    public function index(Request $request): Response
    {
        return $this->render($request, 'catalog/categories/index', [
            'tree' => $this->app->catalog()->categoryTree(),
        ], [
            'title' => __('catalog.categories.title'),
            'activeMenu' => 'catalog.categories',
        ]);
    }

    public function create(Request $request): Response
    {
        $parentId = (int) $request->query('parent', 0);

        return $this->form($request, null, [
            'parent_id' => $parentId > 0 ? $parentId : '',
            'is_active' => 1,
            'sort_order' => 0,
            'transactions' => $parentId > 0 ? $this->app->catalog()->categoryTransactionIds($parentId) : [],
        ]);
    }

    public function store(Request $request): Response
    {
        [$data, $transactions, $attributes, $errors] = $this->validate($request, null);
        if ($errors !== []) {
            return $this->form($request, null, $this->resubmitted($request), $errors, 422);
        }

        $id = $this->app->catalog()->saveCategory(null, $data, $transactions, $attributes);
        $this->log($request, 'category.created', 'category', $id, $data['name'], ['after' => $data + ['transactions' => $transactions, 'attributes' => array_keys($attributes)]]);
        $this->flash('success', __('catalog.flash.created', ['name' => $data['name']]));

        return $this->redirectToRoute('cmsadmin.catalog.categories.index', status: 303);
    }

    public function edit(Request $request, string $id): Response
    {
        $category = $this->find((int) $id);

        return $this->form($request, $category, $category + [
            'transactions' => $this->app->catalog()->categoryTransactionIds((int) $category['id']),
            'attributes' => $this->app->catalog()->categoryAttributes((int) $category['id']),
            'name_en' => $this->translation($category['name_translations']),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $category = $this->find((int) $id);
        [$data, $transactions, $attributes, $errors] = $this->validate($request, $category);
        if ($errors !== []) {
            return $this->form($request, $category, $this->resubmitted($request) + ['code' => $category['code']], $errors, 422);
        }

        $catalog = $this->app->catalog();
        $before = $category + ['transactions' => implode(',', $catalog->categoryTransactionIds((int) $category['id'])), 'attributes' => implode(',', array_keys($catalog->categoryAttributes((int) $category['id'])))];
        $catalog->saveCategory((int) $category['id'], $data, $transactions, $attributes);
        $this->log($request, 'category.updated', 'category', (int) $category['id'], $data['name'], $this->diff($before, $data + ['transactions' => implode(',', $transactions), 'attributes' => implode(',', array_keys($attributes))]));
        $this->flash('success', __('catalog.flash.updated', ['name' => $data['name']]));

        return $this->redirectToRoute('cmsadmin.catalog.categories.index', status: 303);
    }

    public function toggle(Request $request, string $id): Response
    {
        $category = $this->find((int) $id);
        $active = !(bool) $category['is_active'];
        $this->app->catalog()->setActive('property_categories', (int) $category['id'], $active);
        $this->log($request, $active ? 'category.activated' : 'category.deactivated', 'category', (int) $category['id'], (string) $category['name']);
        $this->flash('success', __($active ? 'catalog.flash.activated' : 'catalog.flash.deactivated', ['name' => $category['name']]));

        return $this->redirectToRoute('cmsadmin.catalog.categories.index', status: 303);
    }

    public function destroy(Request $request, string $id): Response
    {
        $category = $this->find((int) $id);
        $usage = $this->app->catalog()->categoryUsage((int) $category['id']);
        if ($usage !== []) {
            $this->flash('error', __('catalog.flash.in_use', ['name' => $category['name'], 'details' => $this->usageText($usage)]));

            return $this->redirectToRoute('cmsadmin.catalog.categories.index', status: 303);
        }

        $this->app->catalog()->delete('property_categories', (int) $category['id']);
        $this->log($request, 'category.deleted', 'category', (int) $category['id'], (string) $category['name'], ['before' => $category]);
        $this->flash('success', __('catalog.flash.deleted', ['name' => $category['name']]));

        return $this->redirectToRoute('cmsadmin.catalog.categories.index', status: 303);
    }

    /**
     * @param array<string, mixed>|null $category
     * @param array<string, mixed>      $values
     * @param array<string, string>     $errors
     */
    private function form(Request $request, ?array $category, array $values, array $errors = [], int $status = 200): Response
    {
        $catalog = $this->app->catalog();
        $parentId = (int) ($values['parent_id'] ?? 0);

        return $this->render($request, 'catalog/categories/form', [
            'category' => $category,
            'values' => $values,
            'errors' => $errors,
            'parents' => $category !== null && $catalog->hasChildren((int) $category['id']) ? [] : $catalog->rootCategoryOptions($category !== null ? (int) $category['id'] : null),
            'hasChildren' => $category !== null && $catalog->hasChildren((int) $category['id']),
            'countries' => $this->app->countries()->options(),
            'transactions' => $catalog->transactionOptions(),
            'attributeGroups' => $catalog->attributesByGroup(),
            'inherited' => $parentId > 0 ? $catalog->categoryAttributes($parentId) : [],
            'parentTransactions' => $parentId > 0 ? $catalog->categoryTransactionIds($parentId) : [],
            'parentName' => $parentId > 0 ? ($catalog->category($parentId)['name'] ?? '') : '',
            'usage' => $category !== null ? $catalog->categoryUsage((int) $category['id']) : [],
            'icons' => $this->icons(),
        ], [
            'title' => $category !== null ? __('catalog.categories.edit', ['name' => $category['name']]) : __('catalog.categories.create'),
            'activeMenu' => 'catalog.categories',
            'plugins' => ['select2'],
        ], $status);
    }

    /**
     * @param array<string, mixed>|null $category
     * @return array{0: array<string, mixed>, 1: list<int>, 2: array<int, array{is_required: bool, sort_order: int}>, 3: array<string, string>}
     */
    private function validate(Request $request, ?array $category): array
    {
        $catalog = $this->app->catalog();
        $input = $request->all();
        if (trim((string) ($input['slug'] ?? '')) === '') {
            $input['slug'] = Str::slug((string) ($input['name'] ?? ''), 80);
        }
        if ($category === null && trim((string) ($input['code'] ?? '')) === '') {
            $input['code'] = Str::code((string) ($input['name'] ?? ''), 60);
        }

        $v = new Validator($input);
        $v->required('name')->maxLength('name', 100)->maxLength('name_plural', 100)->maxLength('name_en', 100)
            ->required('slug')->maxLength('slug', 80)->slug('slug')
            ->maxLength('description', 2000)->maxLength('icon', 60)
            ->integer('sort_order', -32768, 32767);
        if ($category === null) {
            $v->required('code')->maxLength('code', 60)->code('code');
            if (!$v->has('code') && $catalog->valueExists('property_categories', 'code', $v->string('code'))) {
                $v->add('code', __('validation.unique'));
            }
        }
        if (!$v->has('slug') && $catalog->valueExists('property_categories', 'slug', $v->string('slug'), $category !== null ? (int) $category['id'] : null)) {
            $v->add('slug', __('validation.unique'));
        }

        $parentId = $v->nullableInt('parent_id');
        $roots = $catalog->rootCategoryOptions($category !== null ? (int) $category['id'] : null);
        $v->rule('parent_id', $parentId === null || isset($roots[$parentId]), __('validation.in'));
        if ($parentId !== null && $category !== null && $catalog->hasChildren((int) $category['id'])) {
            $v->add('parent_id', __('catalog.categories.parent_has_children'));
        }

        $countryId = $v->nullableInt('country_id');
        $v->rule('country_id', $countryId === null || isset($this->app->countries()->options()[$countryId]), __('validation.in'));
        $icon = $v->nullableString('icon');
        $v->rule('icon', $this->validIcon($icon, $category['icon'] ?? null), __('validation.in'));

        // Une famille déclare ses transactions ; une sous-catégorie peut les restreindre (aucune case = celles de la famille)
        $allowedTransactions = $parentId !== null && isset($roots[$parentId])
            ? array_flip($catalog->categoryTransactionIds($parentId))
            : $catalog->transactionOptions();
        $transactions = array_values(array_filter(array_map('intval', $v->list('transactions')), static fn (int $id): bool => isset($allowedTransactions[$id])));
        $v->rule('transactions', $parentId !== null || $transactions !== [], __('catalog.categories.transactions_required'));

        $knownAttributes = [];
        foreach ($catalog->attributesByGroup() as $group) {
            foreach ($group as $attribute) {
                $knownAttributes[(int) $attribute['id']] = true;
            }
        }
        $attributes = [];
        $rawAttributes = is_array($input['attributes'] ?? null) ? $input['attributes'] : [];
        foreach ($rawAttributes as $attributeId => $link) {
            if (!is_array($link) || empty($link['selected']) || !isset($knownAttributes[(int) $attributeId])) {
                continue;
            }
            $attributes[(int) $attributeId] = [
                'is_required' => !empty($link['required']),
                'sort_order' => max(-32768, min(32767, (int) ($link['sort_order'] ?? 0))),
            ];
        }

        $nameEn = $v->nullableString('name_en');
        $data = [
            'parent_id' => $parentId,
            'country_id' => $countryId,
            'slug' => $v->string('slug'),
            'name' => $v->string('name'),
            'name_plural' => $v->nullableString('name_plural'),
            'name_translations' => $nameEn !== null ? json_encode(['en' => $nameEn], JSON_UNESCAPED_UNICODE) : null,
            'icon' => $icon,
            'description' => $v->nullableString('description'),
            'is_active' => $v->bool('is_active') ? 1 : 0,
            'sort_order' => $v->int('sort_order'),
        ];
        if ($category === null) {
            $data = ['code' => $v->string('code')] + $data;
        }

        return [$data, $transactions, $attributes, $v->errors()];
    }

    /** @return array<string, mixed> Valeurs ressaisies après une erreur */
    private function resubmitted(Request $request): array
    {
        $values = $request->all();
        $values['transactions'] = array_map('intval', (array) ($values['transactions'] ?? []));
        $links = [];
        foreach ((array) ($values['attributes'] ?? []) as $attributeId => $link) {
            if (is_array($link) && !empty($link['selected'])) {
                $links[(int) $attributeId] = ['is_required' => !empty($link['required']), 'sort_order' => (int) ($link['sort_order'] ?? 0)];
            }
        }
        $values['attributes'] = $links;

        return $values;
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        return $this->app->catalog()->category($id) ?? throw new HttpException(404);
    }
}
