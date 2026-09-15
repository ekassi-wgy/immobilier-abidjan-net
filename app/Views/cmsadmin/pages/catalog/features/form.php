<?php
/**
 * Création / modification d'un équipement.
 *
 * @var array<string,mixed>|null $feature
 * @var array<string,mixed>      $values
 * @var array<string,string>     $errors
 * @var array<string,string>     $groups
 * @var list<string>             $icons
 * @var array<string,int>        $usage
 */
$isEdit = $feature !== null;
$value = static fn (string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
$iconOptions = array_combine($icons, $icons);
if ($value('icon') !== '' && !isset($iconOptions[$value('icon')])) {
    $iconOptions[$value('icon')] = $value('icon') . ' · ' . __('catalog.icon_missing');
}
?>
<?= cmsadmin_partial('page-header', [
    'title' => $isEdit ? __('catalog.features.edit', ['name' => $feature['name']]) : __('catalog.features.create'),
    'subtitle' => __('catalog.features.subtitle'),
    'breadcrumb' => [
        ['label' => __('auth.dashboard'), 'url' => '/'],
        ['label' => __('catalog.features.title'), 'url' => 'equipements'],
        ['label' => $isEdit ? $feature['name'] : __('catalog.features.create')],
    ],
]) ?>

<?php if ($errors !== []): ?>
<div class="im-flash im-flash--error" role="alert">
  <span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span>
  <p><?= e(__('cmsadmin.form_errors', ['count' => count($errors)])) ?></p>
</div>
<?php endif; ?>

<form class="im-form" method="post" action="<?= e(cmsadmin_url($isEdit ? 'equipements/' . $feature['id'] : 'equipements')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="im-form__layout">
    <div class="im-form__main">
      <section class="card im-panel im-form-section">
        <header class="im-form-section__head">
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
            <?= cmsadmin_partial('field', ['name' => 'code_display', 'label' => __('cmsadmin.code'), 'value' => $feature['code'], 'disabled' => true, 'hint' => __('cmsadmin.code_locked'), 'class' => '']) ?>
            <?php else: ?>
            <?= cmsadmin_partial('field', ['name' => 'code', 'label' => __('cmsadmin.code'), 'value' => $value('code'), 'optional' => true, 'hint' => __('cmsadmin.code_hint'), 'error' => $errors['code'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 60, 'spellcheck' => 'false']]) ?>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <?= cmsadmin_partial('field', ['name' => 'feature_group', 'type' => 'select', 'label' => __('catalog.features.group'), 'options' => $groups, 'value' => $value('feature_group', 'comfort'), 'required' => true, 'error' => $errors['feature_group'] ?? null, 'class' => '']) ?>
          </div>
          <div class="col-md-6">
            <div class="im-icon-field">
              <?= cmsadmin_partial('field', ['name' => 'icon', 'type' => 'select', 'label' => __('catalog.icon'), 'options' => $iconOptions, 'placeholder' => __('cmsadmin.none'), 'value' => $value('icon'), 'optional' => true, 'hint' => __('catalog.icon_hint'), 'error' => $errors['icon'] ?? null, 'class' => '', 'attributes' => ['data-icon-preview' => 'feature-icon']]) ?>
              <span class="im-icon-preview" id="feature-icon" data-sprite="<?= e(asset('img/icons.svg')) ?>" aria-hidden="true"><?php if (in_array($value('icon'), $icons, true)): ?><svg><use href="<?= e(asset('img/icons.svg')) ?>#i-<?= e($value('icon')) ?>"></use></svg><?php endif; ?></span>
            </div>
          </div>
        </div>
      </section>
    </div>

    <aside class="im-form__aside">
      <div class="im-sticky">
        <section class="card im-panel">
          <h2 class="im-panel__title"><?= e(__('cmsadmin.publication')) ?></h2>
          <div class="mt-3">
            <?= cmsadmin_partial('switch', ['name' => 'is_active', 'label' => __('cmsadmin.active'), 'checked' => (bool) $value('is_active', 1)]) ?>
            <?= cmsadmin_partial('switch', ['name' => 'is_filterable', 'label' => __('catalog.attributes.filterable'), 'checked' => (bool) $value('is_filterable', 1), 'hint' => __('catalog.features.filterable_hint')]) ?>
            <?= cmsadmin_partial('field', ['name' => 'sort_order', 'type' => 'number', 'label' => __('cmsadmin.sort_order'), 'value' => $value('sort_order', 0), 'error' => $errors['sort_order'] ?? null, 'attributes' => ['step' => 1]]) ?>
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
            <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url('equipements')) ?>"><?= e(__('cmsadmin.cancel')) ?></a>
          </div>
        </section>
      </div>
    </aside>
  </div>
</form>
