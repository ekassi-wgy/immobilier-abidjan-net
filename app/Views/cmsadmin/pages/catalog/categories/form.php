<?php
/**
 * Création / modification d'une catégorie de biens.
 *
 * @var array<string,mixed>|null                   $category
 * @var array<string,mixed>                        $values
 * @var array<string,string>                       $errors
 * @var array<int,string>                          $parents        Catégories racines possibles (vide si la catégorie a des enfants)
 * @var bool                                       $hasChildren
 * @var array<int,string>                          $countries
 * @var array<int,string>                          $transactions
 * @var array<string, list<array<string,mixed>>>   $attributeGroups
 * @var array<int, array{is_required: bool, sort_order: int}> $inherited Critères de la catégorie parente
 * @var string                                     $parentName
 * @var list<int>                                  $parentTransactions Transactions de la famille (sous-catégorie)
 * @var array<string,int>                          $usage
 * @var list<string>                               $icons
 */
$isEdit = $category !== null;
$value = static fn (string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
$selectedTransactions = array_map('intval', (array) $value('transactions', []));
$isChild = (string) $value('parent_id') !== '';
$offeredTransactions = $isChild && $parentTransactions !== [] ? array_intersect_key($transactions, array_flip($parentTransactions)) : $transactions;
$links = (array) $value('attributes', []);
$iconOptions = array_combine($icons, $icons);
if ($value('icon') !== '' && !isset($iconOptions[$value('icon')])) {
    $iconOptions[$value('icon')] = $value('icon');
}
?>
<?= cmsadmin_partial('page-header', [
    'title' => $isEdit ? __('catalog.categories.edit', ['name' => $category['name']]) : __('catalog.categories.create'),
    'subtitle' => __('catalog.categories.form_subtitle'),
    'breadcrumb' => [
        ['label' => __('auth.dashboard'), 'url' => '/'],
        ['label' => __('catalog.categories.title'), 'url' => 'categories'],
        ['label' => $isEdit ? $category['name'] : __('catalog.categories.create')],
    ],
]) ?>

<?php if ($errors !== []): ?>
<div class="im-flash im-flash--error" role="alert">
  <span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span>
  <p><?= e(__('cmsadmin.form_errors', ['count' => count($errors)])) ?></p>
</div>
<?php endif; ?>

<form class="im-form" method="post" action="<?= e(cmsadmin_url($isEdit ? 'categories/' . $category['id'] : 'categories')) ?>" novalidate>
  <?= csrf_field() ?>

  <div class="im-form__layout">
    <div class="im-form__main">
      <section class="card im-panel im-form-section" id="general">
        <header class="im-form-section__head">
          <span class="im-form-section__index">01</span>
          <h2 class="im-panel__title"><?= e(__('cmsadmin.general')) ?></h2>
        </header>
        <div class="row g-3">
          <div class="col-12">
            <?php if ($hasChildren): ?>
            <p class="im-note mb-0"><span class="mdi mdi-file-tree-outline" aria-hidden="true"></span> <?= e(__('catalog.categories.is_root_with_children')) ?></p>
            <?php else: ?>
            <?= cmsadmin_partial('field', [
                'name' => 'parent_id', 'type' => 'select', 'label' => __('catalog.categories.parent'), 'options' => $parents,
                'placeholder' => __('catalog.categories.no_parent'), 'value' => $value('parent_id'), 'hint' => __('catalog.categories.parent_hint'),
                'error' => $errors['parent_id'] ?? null, 'class' => '',
            ]) ?>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <?= cmsadmin_partial('field', ['name' => 'name', 'label' => __('cmsadmin.name'), 'value' => $value('name'), 'required' => true, 'error' => $errors['name'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 100, 'data-slug-source' => 'slug']]) ?>
          </div>
          <div class="col-md-6">
            <?= cmsadmin_partial('field', ['name' => 'name_plural', 'label' => __('catalog.categories.name_plural'), 'value' => $value('name_plural'), 'optional' => true, 'hint' => __('catalog.categories.name_plural_hint'), 'error' => $errors['name_plural'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 100]]) ?>
          </div>
          <div class="col-md-6">
            <?= cmsadmin_partial('field', ['name' => 'slug', 'label' => __('cmsadmin.slug'), 'value' => $value('slug'), 'optional' => !$isEdit, 'hint' => $isEdit ? __('cmsadmin.slug_warning') : __('cmsadmin.slug_hint'), 'error' => $errors['slug'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 80, 'spellcheck' => 'false']]) ?>
          </div>
          <div class="col-md-6">
            <?php if ($isEdit): ?>
            <?= cmsadmin_partial('field', ['name' => 'code_display', 'label' => __('cmsadmin.code'), 'value' => $category['code'], 'disabled' => true, 'hint' => __('cmsadmin.code_locked'), 'class' => '']) ?>
            <?php else: ?>
            <?= cmsadmin_partial('field', ['name' => 'code', 'label' => __('cmsadmin.code'), 'value' => $value('code'), 'optional' => true, 'hint' => __('cmsadmin.code_hint'), 'error' => $errors['code'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 60, 'spellcheck' => 'false']]) ?>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <?= cmsadmin_partial('field', ['name' => 'name_en', 'label' => __('cmsadmin.name_en'), 'value' => $value('name_en'), 'optional' => true, 'error' => $errors['name_en'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 100, 'lang' => 'en']]) ?>
          </div>
          <div class="col-md-6">
            <div class="im-icon-field">
              <?= cmsadmin_partial('field', ['name' => 'icon', 'type' => 'select', 'label' => __('catalog.icon'), 'options' => $iconOptions, 'placeholder' => __('cmsadmin.none'), 'value' => $value('icon'), 'optional' => true, 'error' => $errors['icon'] ?? null, 'class' => '', 'attributes' => ['data-icon-preview' => 'category-icon']]) ?>
              <span class="im-icon-preview" id="category-icon" data-sprite="<?= e(asset('img/icons.svg')) ?>" aria-hidden="true"><?php if ($value('icon') !== '' && in_array($value('icon'), $icons, true)): ?><svg><use href="<?= e(asset('img/icons.svg')) ?>#i-<?= e($value('icon')) ?>"></use></svg><?php endif; ?></span>
            </div>
          </div>
          <div class="col-12">
            <?= cmsadmin_partial('field', ['name' => 'description', 'type' => 'textarea', 'label' => __('catalog.description'), 'value' => $value('description'), 'optional' => true, 'hint' => __('catalog.categories.description_hint'), 'error' => $errors['description'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 2000]]) ?>
          </div>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="transactions">
        <header class="im-form-section__head">
          <span class="im-form-section__index">02</span>
          <h2 class="im-panel__title"><?= e(__('catalog.categories.transactions')) ?></h2>
        </header>
        <p class="im-panel__subtitle"><?= e($isChild && $parentName !== '' ? __('catalog.categories.transactions_child_hint', ['parent' => $parentName, 'list' => implode(', ', $offeredTransactions)]) : __('catalog.categories.transactions_hint')) ?></p>
        <fieldset<?= isset($errors['transactions']) ? ' aria-describedby="transactions-error"' : '' ?>>
          <legend class="visually-hidden"><?= e(__('catalog.categories.transactions')) ?></legend>
          <div class="im-checks">
            <?php foreach ($offeredTransactions as $id => $label): ?>
            <label class="im-check">
              <input type="checkbox" name="transactions[]" value="<?= e($id) ?>"<?= in_array($id, $selectedTransactions, true) ? ' checked' : '' ?>>
              <span class="im-check__box" aria-hidden="true"><span class="mdi mdi-check"></span></span>
              <span><?= e($label) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <?php if (isset($errors['transactions'])): ?><p class="invalid-feedback d-block" id="transactions-error"><?= e($errors['transactions']) ?></p><?php endif; ?>
      </section>

      <section class="card im-panel im-form-section" id="criteres">
        <header class="im-form-section__head">
          <span class="im-form-section__index">03</span>
          <h2 class="im-panel__title"><?= e(__('catalog.attributes.title')) ?></h2>
        </header>
        <p class="im-panel__subtitle">
          <?= e($parentName !== '' ? __('catalog.categories.attributes_child_hint', ['parent' => $parentName]) : __('catalog.categories.attributes_root_hint')) ?>
        </p>

        <div class="table-responsive">
          <table class="table im-table im-attribute-picker">
            <thead>
              <tr>
                <th scope="col"><?= e(__('catalog.attributes.singular')) ?></th>
                <th scope="col" class="text-center"><?= e(__('catalog.categories.required')) ?></th>
                <th scope="col" class="text-end"><?= e(__('cmsadmin.sort_order')) ?></th>
              </tr>
            </thead>
            <?php foreach ($attributeGroups as $groupName => $attributes): ?>
            <tbody>
              <tr class="im-attribute-picker__group"><th scope="rowgroup" colspan="3"><?= e($groupName) ?></th></tr>
              <?php foreach ($attributes as $attribute):
                  $aid = (int) $attribute['id'];
                  $isInherited = isset($inherited[$aid]);
                  $link = $links[$aid] ?? null;
              ?>
              <tr class="<?= $isInherited ? 'is-inherited' : '' ?><?= (int) $attribute['is_active'] === 0 ? ' is-muted' : '' ?>">
                <td>
                  <?php if ($isInherited): ?>
                  <span class="im-check is-static">
                    <span class="im-check__box is-checked" aria-hidden="true"><span class="mdi mdi-check"></span></span>
                    <span><?= e($attribute['name']) ?> <span class="im-tag"><?= e(__('catalog.categories.inherited')) ?></span></span>
                  </span>
                  <?php else: ?>
                  <label class="im-check">
                    <input type="checkbox" name="attributes[<?= e($aid) ?>][selected]" value="1"<?= $link !== null ? ' checked' : '' ?>>
                    <span class="im-check__box" aria-hidden="true"><span class="mdi mdi-check"></span></span>
                    <span><?= e($attribute['name']) ?><?php if ((int) $attribute['is_active'] === 0): ?> <span class="im-tag"><?= e(__('cmsadmin.inactive')) ?></span><?php endif; ?></span>
                  </label>
                  <?php endif; ?>
                  <span class="im-cell-sub ms-4"><?= e(__('catalog.attributes.types.' . $attribute['input_type'])) ?><?= $attribute['unit'] ? ' · ' . e($attribute['unit']) : '' ?></span>
                </td>
                <td class="text-center">
                  <?php if ($isInherited): ?>
                  <?= $inherited[$aid]['is_required'] ? e(__('cmsadmin.yes')) : '—' ?>
                  <?php else: ?>
                  <input class="form-check-input" type="checkbox" name="attributes[<?= e($aid) ?>][required]" value="1" aria-label="<?= e(__('catalog.categories.required_for', ['name' => $attribute['name']])) ?>"<?= !empty($link['is_required']) ? ' checked' : '' ?>>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <?php if (!$isInherited): ?>
                  <input class="form-control form-control-sm im-num im-input-order" type="number" step="1" name="attributes[<?= e($aid) ?>][sort_order]" value="<?= e($link['sort_order'] ?? 0) ?>" aria-label="<?= e(__('catalog.categories.order_for', ['name' => $attribute['name']])) ?>">
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <?php endforeach; ?>
          </table>
        </div>
      </section>
    </div>

    <aside class="im-form__aside">
      <div class="im-sticky">
        <section class="card im-panel">
          <h2 class="im-panel__title"><?= e(__('cmsadmin.publication')) ?></h2>
          <div class="mt-3">
            <?= cmsadmin_partial('switch', ['name' => 'is_active', 'label' => __('cmsadmin.active'), 'checked' => (bool) $value('is_active', 1), 'hint' => __('catalog.categories.active_hint')]) ?>
            <?= cmsadmin_partial('field', ['name' => 'country_id', 'type' => 'select', 'label' => __('catalog.categories.country'), 'options' => $countries, 'placeholder' => __('catalog.categories.all_countries'), 'value' => $value('country_id'), 'error' => $errors['country_id'] ?? null]) ?>
            <?= cmsadmin_partial('field', ['name' => 'sort_order', 'type' => 'number', 'label' => __('cmsadmin.sort_order'), 'value' => $value('sort_order', 0), 'hint' => __('cmsadmin.sort_order_hint'), 'error' => $errors['sort_order'] ?? null, 'attributes' => ['step' => 1]]) ?>
          </div>
          <?php if ($usage !== []): ?>
          <dl class="im-meta-list">
            <?php foreach ($usage as $key => $count): ?>
            <div><dt><?= e(__($key . '_label')) ?></dt><dd class="im-num"><?= e(format_number($count)) ?></dd></div>
            <?php endforeach; ?>
          </dl>
          <?php endif; ?>
          <div class="d-grid gap-2">
            <button class="btn btn-primary" type="submit"><?= e(__($isEdit ? 'cmsadmin.save' : 'cmsadmin.create')) ?></button>
            <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url('categories')) ?>"><?= e(__('cmsadmin.cancel')) ?></a>
          </div>
        </section>

        <nav class="im-toc" aria-label="<?= e(__('catalog.on_this_page')) ?>">
          <p class="im-toc__title"><?= e(__('catalog.on_this_page')) ?></p>
          <ol>
            <li><a href="#general"><?= e(__('cmsadmin.general')) ?></a></li>
            <li><a href="#transactions"><?= e(__('catalog.categories.transactions')) ?></a></li>
            <li><a href="#criteres"><?= e(__('catalog.attributes.title')) ?></a></li>
          </ol>
        </nav>
      </div>
    </aside>
  </div>
</form>
