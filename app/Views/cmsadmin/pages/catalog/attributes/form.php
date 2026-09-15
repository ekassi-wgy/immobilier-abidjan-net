<?php
/**
 * Création / modification d'un critère dynamique et de ses options.
 *
 * @var array<string,mixed>|null   $attribute
 * @var array<string,mixed>        $values
 * @var array<string,string>       $errors    Erreurs d'options : « options.{clé}.label|code »
 * @var array<int,string>          $groups
 * @var array<string,string>       $inputTypes
 * @var array<string,string>       $columns
 * @var bool                       $typeLocked
 * @var int                        $valuesCount
 * @var list<array<string,mixed>>  $categories
 */
$isEdit = $attribute !== null;
$value = static fn (string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
$options = (array) $value('options', []);
$inputType = (string) $value('input_type', 'select');
$hasOptions = in_array($inputType, ['select', 'multiselect'], true);

$optionRow = static function (string $index, array $option, array $errors): string {
    $err = static fn (string $field): ?string => $errors["options.{$index}.{$field}"] ?? null;
    ob_start(); ?>
    <tr data-repeat-item>
      <td>
        <?php if (!empty($option['id'])): ?><input type="hidden" name="options[<?= e($index) ?>][id]" value="<?= e($option['id']) ?>"><?php endif; ?>
        <input class="form-control form-control-sm<?= $err('label') ? ' is-invalid' : '' ?>" name="options[<?= e($index) ?>][label]" value="<?= e($option['label'] ?? '') ?>" maxlength="120" aria-label="<?= e(__('catalog.attributes.option_label')) ?>" required>
        <?php if ($err('label')): ?><p class="invalid-feedback d-block"><?= e($err('label')) ?></p><?php endif; ?>
      </td>
      <td>
        <input class="form-control form-control-sm" name="options[<?= e($index) ?>][label_en]" value="<?= e($option['label_en'] ?? '') ?>" maxlength="120" lang="en" aria-label="<?= e(__('catalog.attributes.option_label_en')) ?>">
      </td>
      <td>
        <input class="form-control form-control-sm<?= $err('code') ? ' is-invalid' : '' ?>" name="options[<?= e($index) ?>][code]" value="<?= e($option['code'] ?? '') ?>" maxlength="60" spellcheck="false" placeholder="<?= e(__('catalog.attributes.auto')) ?>" aria-label="<?= e(__('cmsadmin.code')) ?>"<?= !empty($option['usage_count']) ? ' readonly' : '' ?>>
        <?php if ($err('code')): ?><p class="invalid-feedback d-block"><?= e($err('code')) ?></p><?php endif; ?>
      </td>
      <td><input class="form-control form-control-sm im-num im-input-order" type="number" step="1" name="options[<?= e($index) ?>][sort_order]" value="<?= e($option['sort_order'] ?? 0) ?>" aria-label="<?= e(__('cmsadmin.sort_order')) ?>"></td>
      <td class="text-center"><input class="form-check-input" type="checkbox" name="options[<?= e($index) ?>][is_active]" value="1"<?= !isset($option['is_active']) || (int) $option['is_active'] === 1 ? ' checked' : '' ?> aria-label="<?= e(__('cmsadmin.active')) ?>"></td>
      <td class="text-end">
        <?php if (!empty($option['usage_count'])): ?>
        <span class="im-cell-sub" title="<?= e(__('catalog.attributes.option_used_hint')) ?>"><?= e(__('geo.usage.properties', ['count' => (int) $option['usage_count']])) ?></span>
        <?php else: ?>
        <button type="button" class="im-icon-btn im-icon-btn--sm" data-repeat-remove aria-label="<?= e(__('catalog.attributes.remove_option')) ?>"><span class="mdi mdi-close" aria-hidden="true"></span></button>
        <?php endif; ?>
      </td>
    </tr>
    <?php return (string) ob_get_clean();
};
?>
<?= cmsadmin_partial('page-header', [
    'title' => $isEdit ? __('catalog.attributes.edit', ['name' => $attribute['name']]) : __('catalog.attributes.create'),
    'subtitle' => __('catalog.attributes.form_subtitle'),
    'breadcrumb' => [
        ['label' => __('auth.dashboard'), 'url' => '/'],
        ['label' => __('catalog.attributes.title'), 'url' => 'criteres'],
        ['label' => $isEdit ? $attribute['name'] : __('catalog.attributes.create')],
    ],
]) ?>

<?php if ($errors !== []): ?>
<div class="im-flash im-flash--error" role="alert">
  <span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span>
  <p><?= e(__('cmsadmin.form_errors', ['count' => count($errors)])) ?></p>
</div>
<?php endif; ?>

<form class="im-form" method="post" action="<?= e(cmsadmin_url($isEdit ? 'criteres/' . $attribute['id'] : 'criteres')) ?>" novalidate>
  <?= csrf_field() ?>

  <div class="im-form__layout">
    <div class="im-form__main">
      <section class="card im-panel im-form-section">
        <header class="im-form-section__head">
          <span class="im-form-section__index">01</span>
          <h2 class="im-panel__title"><?= e(__('cmsadmin.general')) ?></h2>
        </header>
        <div class="row g-3">
          <div class="col-md-6">
            <?= cmsadmin_partial('field', ['name' => 'name', 'label' => __('cmsadmin.name'), 'value' => $value('name'), 'required' => true, 'error' => $errors['name'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 100]]) ?>
          </div>
          <div class="col-md-6">
            <?= cmsadmin_partial('field', ['name' => 'name_en', 'label' => __('cmsadmin.name_en'), 'value' => $value('name_en'), 'optional' => true, 'error' => $errors['name_en'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 100, 'lang' => 'en']]) ?>
          </div>
          <div class="col-md-6">
            <?php if ($isEdit): ?>
            <?= cmsadmin_partial('field', ['name' => 'code_display', 'label' => __('cmsadmin.code'), 'value' => $attribute['code'], 'disabled' => true, 'hint' => __('cmsadmin.code_locked'), 'class' => '']) ?>
            <?php else: ?>
            <?= cmsadmin_partial('field', ['name' => 'code', 'label' => __('cmsadmin.code'), 'value' => $value('code'), 'optional' => true, 'hint' => __('cmsadmin.code_hint'), 'error' => $errors['code'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 60, 'spellcheck' => 'false']]) ?>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <?= cmsadmin_partial('field', ['name' => 'group_id', 'type' => 'select', 'label' => __('catalog.attributes.group'), 'options' => $groups, 'placeholder' => __('cmsadmin.choose'), 'value' => $value('group_id'), 'required' => true, 'error' => $errors['group_id'] ?? null, 'class' => '']) ?>
          </div>
          <div class="col-12">
            <?= cmsadmin_partial('field', ['name' => 'help_text', 'label' => __('catalog.attributes.help_text'), 'value' => $value('help_text'), 'optional' => true, 'hint' => __('catalog.attributes.help_text_hint'), 'error' => $errors['help_text'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 255]]) ?>
          </div>
        </div>
      </section>

      <section class="card im-panel im-form-section">
        <header class="im-form-section__head">
          <span class="im-form-section__index">02</span>
          <h2 class="im-panel__title"><?= e(__('catalog.attributes.input')) ?></h2>
        </header>
        <div class="row g-3">
          <div class="col-md-6">
            <?= cmsadmin_partial('field', [
                'name' => 'input_type', 'type' => 'select', 'label' => __('catalog.attributes.input_type'), 'options' => $inputTypes, 'value' => $inputType,
                'disabled' => $typeLocked, 'hint' => $typeLocked ? __('catalog.attributes.type_locked', ['count' => $valuesCount]) : null,
                'error' => $errors['input_type'] ?? null, 'class' => '', 'attributes' => ['data-toggle-options' => 'attribute-options'],
            ]) ?>
          </div>
          <div class="col-md-3">
            <?= cmsadmin_partial('field', ['name' => 'unit', 'label' => __('catalog.attributes.unit'), 'value' => $value('unit'), 'optional' => true, 'error' => $errors['unit'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 20, 'placeholder' => 'm²']]) ?>
          </div>
          <div class="col-md-3">
            <?= cmsadmin_partial('field', ['name' => 'sort_order', 'type' => 'number', 'label' => __('cmsadmin.sort_order'), 'value' => $value('sort_order', 0), 'error' => $errors['sort_order'] ?? null, 'class' => '', 'attributes' => ['step' => 1]]) ?>
          </div>
          <div class="col-md-6">
            <?= cmsadmin_partial('field', ['name' => 'min_value', 'label' => __('catalog.attributes.min_value'), 'value' => $value('min_value'), 'optional' => true, 'error' => $errors['min_value'] ?? null, 'class' => '', 'attributes' => ['inputmode' => 'decimal']]) ?>
          </div>
          <div class="col-md-6">
            <?= cmsadmin_partial('field', ['name' => 'max_value', 'label' => __('catalog.attributes.max_value'), 'value' => $value('max_value'), 'optional' => true, 'error' => $errors['max_value'] ?? null, 'class' => '', 'attributes' => ['inputmode' => 'decimal']]) ?>
          </div>
          <div class="col-12">
            <?php if ($isEdit): ?>
            <p class="im-note mb-0"><span class="mdi mdi-database-outline" aria-hidden="true"></span>
              <?= e($attribute['storage'] === 'column' ? __('catalog.attributes.storage_is_column', ['column' => __('catalog.attributes.columns.' . $attribute['column_name'])]) : __('catalog.attributes.storage_is_eav')) ?>
            </p>
            <?php else: ?>
            <fieldset>
              <legend class="form-label"><?= e(__('catalog.attributes.storage')) ?></legend>
              <div class="im-segmented" role="radiogroup">
                <label class="im-segmented__option"><input type="radio" name="storage" value="eav"<?= $value('storage', 'eav') !== 'column' ? ' checked' : '' ?>><span><?= e(__('catalog.attributes.storage_eav')) ?></span></label>
                <label class="im-segmented__option"><input type="radio" name="storage" value="column"<?= $value('storage') === 'column' ? ' checked' : '' ?>><span><?= e(__('catalog.attributes.storage_column')) ?></span></label>
              </div>
              <p class="form-text"><?= e(__('catalog.attributes.storage_hint')) ?></p>
            </fieldset>
            <?= cmsadmin_partial('field', ['name' => 'column_name', 'type' => 'select', 'label' => __('catalog.attributes.column_name'), 'options' => $columns, 'placeholder' => __('cmsadmin.choose'), 'value' => $value('column_name'), 'optional' => true, 'hint' => __('catalog.attributes.column_name_hint'), 'error' => $errors['column_name'] ?? null, 'class' => 'mt-3']) ?>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="attribute-options"<?= $hasOptions ? '' : ' hidden' ?>>
        <header class="im-form-section__head">
          <span class="im-form-section__index">03</span>
          <h2 class="im-panel__title"><?= e(__('catalog.attributes.options')) ?></h2>
        </header>
        <p class="im-panel__subtitle"><?= e(__('catalog.attributes.options_hint')) ?></p>
        <?php if (isset($errors['options'])): ?><p class="invalid-feedback d-block"><?= e($errors['options']) ?></p><?php endif; ?>
        <div class="table-responsive">
          <table class="table im-table im-options-table">
            <thead>
              <tr>
                <th scope="col"><?= e(__('catalog.attributes.option_label')) ?></th>
                <th scope="col"><?= e(__('catalog.attributes.option_label_en')) ?></th>
                <th scope="col"><?= e(__('cmsadmin.code')) ?></th>
                <th scope="col"><?= e(__('cmsadmin.sort_order')) ?></th>
                <th scope="col" class="text-center"><?= e(__('cmsadmin.active')) ?></th>
                <th scope="col"><span class="visually-hidden"><?= e(__('cmsadmin.actions')) ?></span></th>
              </tr>
            </thead>
            <tbody data-repeat-list="options">
              <?php foreach ($options as $index => $option): ?>
              <?= $optionRow((string) $index, $option, $errors) ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <template data-repeat-template="options"><?= $optionRow('__INDEX__', ['sort_order' => (count($options) + 1) * 10], []) ?></template>
        <button type="button" class="btn im-btn-ghost btn-sm" data-repeat-add="options"><span class="mdi mdi-plus" aria-hidden="true"></span> <?= e(__('catalog.attributes.add_option')) ?></button>
      </section>
    </div>

    <aside class="im-form__aside">
      <div class="im-sticky">
        <section class="card im-panel">
          <h2 class="im-panel__title"><?= e(__('cmsadmin.publication')) ?></h2>
          <div class="mt-3">
            <?= cmsadmin_partial('switch', ['name' => 'is_active', 'label' => __('cmsadmin.active'), 'checked' => (bool) $value('is_active', 1)]) ?>
            <?= cmsadmin_partial('switch', ['name' => 'is_filterable', 'label' => __('catalog.attributes.filterable'), 'checked' => (bool) $value('is_filterable', 0), 'hint' => __('catalog.attributes.filterable_hint')]) ?>
            <?= cmsadmin_partial('switch', ['name' => 'is_public', 'label' => __('catalog.attributes.public'), 'checked' => (bool) $value('is_public', 1), 'hint' => __('catalog.attributes.public_hint')]) ?>
          </div>
          <?php if ($isEdit): ?>
          <dl class="im-meta-list">
            <div><dt><?= e(__('catalog.categories.title')) ?></dt><dd class="im-num"><?= e(count($categories)) ?></dd></div>
            <div><dt><?= e(__('geo.usage.properties_label')) ?></dt><dd class="im-num"><?= e(format_number($valuesCount)) ?></dd></div>
          </dl>
          <?php endif; ?>
          <div class="d-grid gap-2">
            <button class="btn btn-primary" type="submit"><?= e(__($isEdit ? 'cmsadmin.save' : 'cmsadmin.create')) ?></button>
            <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url('criteres')) ?>"><?= e(__('cmsadmin.cancel')) ?></a>
          </div>
        </section>

        <?php if ($categories !== []): ?>
        <section class="card im-panel">
          <h2 class="im-panel__title"><?= e(__('catalog.attributes.used_by')) ?></h2>
          <ul class="im-plain-list">
            <?php foreach ($categories as $category): ?>
            <li><a class="im-cell-link" href="<?= e(cmsadmin_url('categories/' . $category['id'] . '/modifier#criteres')) ?>"><?= e($category['name']) ?></a><?php if ((int) $category['is_required'] === 1): ?> <span class="im-tag"><?= e(__('catalog.categories.required')) ?></span><?php endif; ?></li>
            <?php endforeach; ?>
          </ul>
        </section>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</form>
