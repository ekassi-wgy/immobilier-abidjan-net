<?php
/**
 * Création / modification d'un pays.
 *
 * @var array<string,mixed>|null $country
 * @var array<string,mixed>      $values
 * @var array<string,string>     $errors
 * @var array<string,string>     $locales
 * @var array<string,string>     $timezones
 * @var int                      $activeSites
 */
$isEdit = $country !== null;
$value = static fn (string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
?>
<?= cmsadmin_partial('page-header', [
    'title' => $isEdit ? __('sites.country.edit', ['name' => $country['name']]) : __('sites.country.create'),
    'subtitle' => __('sites.country.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('sites.title'), 'url' => 'pays-sites'], ['label' => $isEdit ? $country['name'] : __('sites.country.create')]],
]) ?>

<?php if ($errors !== []): ?>
<div class="im-flash im-flash--error" role="alert">
  <span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span>
  <p><?= e(__('cmsadmin.form_errors', ['count' => count($errors)])) ?></p>
</div>
<?php endif; ?>

<form class="im-form" method="post" action="<?= e(cmsadmin_url($isEdit ? 'pays-sites/pays/' . $country['id'] : 'pays-sites/pays')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="im-form__layout">
    <div class="im-form__main">
      <section class="card im-panel im-form-section">
        <header class="im-form-section__head"><h2 class="im-panel__title"><?= e(__('cmsadmin.general')) ?></h2></header>
        <div class="row g-3">
          <div class="col-md-5">
            <?= cmsadmin_partial('field', ['name' => 'name', 'label' => __('cmsadmin.name'), 'value' => $value('name'), 'required' => true, 'error' => $errors['name'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 100]]) ?>
          </div>
          <div class="col-md-5">
            <?= cmsadmin_partial('field', ['name' => 'name_en', 'label' => __('cmsadmin.name_en'), 'value' => $value('name_en'), 'optional' => true, 'error' => $errors['name_en'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 100, 'lang' => 'en']]) ?>
          </div>
          <div class="col-md-2">
            <?= cmsadmin_partial('field', ['name' => $isEdit ? 'iso2_display' : 'iso2', 'label' => __('sites.country.iso2'), 'value' => $value('iso2'), 'required' => !$isEdit, 'disabled' => $isEdit, 'error' => $errors['iso2'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 2, 'placeholder' => 'SN', 'spellcheck' => 'false']]) ?>
          </div>
          <div class="col-md-4">
            <?= cmsadmin_partial('field', ['name' => 'currency_code', 'label' => __('sites.country.currency_code'), 'value' => $value('currency_code'), 'required' => true, 'hint' => __('sites.country.currency_code_hint'), 'error' => $errors['currency_code'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 3, 'placeholder' => 'XOF']]) ?>
          </div>
          <div class="col-md-4">
            <?= cmsadmin_partial('field', ['name' => 'currency_symbol', 'label' => __('sites.country.currency_symbol'), 'value' => $value('currency_symbol'), 'required' => true, 'error' => $errors['currency_symbol'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 10, 'placeholder' => 'FCFA']]) ?>
          </div>
          <div class="col-md-4">
            <?= cmsadmin_partial('field', ['name' => 'currency_decimals', 'type' => 'number', 'label' => __('sites.country.currency_decimals'), 'value' => $value('currency_decimals', 0), 'error' => $errors['currency_decimals'] ?? null, 'class' => '', 'attributes' => ['min' => 0, 'max' => 3, 'step' => 1]]) ?>
          </div>
          <div class="col-md-4">
            <?= cmsadmin_partial('field', ['name' => 'phone_prefix', 'label' => __('sites.country.phone_prefix'), 'value' => $value('phone_prefix'), 'required' => true, 'error' => $errors['phone_prefix'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 6, 'placeholder' => '+221']]) ?>
          </div>
          <div class="col-md-4">
            <?= cmsadmin_partial('field', ['name' => 'default_locale', 'type' => 'select', 'label' => __('sites.locale'), 'options' => $locales, 'value' => $value('default_locale', 'fr'), 'required' => true, 'error' => $errors['default_locale'] ?? null, 'class' => '']) ?>
          </div>
          <div class="col-md-4">
            <?= cmsadmin_partial('field', ['name' => 'timezone', 'type' => 'select', 'label' => __('sites.country.timezone'), 'options' => $timezones, 'value' => $value('timezone', 'Africa/Abidjan'), 'required' => true, 'error' => $errors['timezone'] ?? null, 'class' => '', 'attributes' => ['data-im-select' => '']]) ?>
          </div>
        </div>
      </section>
    </div>

    <aside class="im-form__aside">
      <div class="im-sticky">
        <section class="card im-panel">
          <h2 class="im-panel__title"><?= e(__('cmsadmin.publication')) ?></h2>
          <div class="mt-3">
            <?= cmsadmin_partial('switch', ['name' => 'is_active', 'label' => __('cmsadmin.active'), 'checked' => (bool) $value('is_active', 0), 'hint' => $activeSites > 0 ? __('sites.country.active_locked', ['count' => $activeSites]) : __('sites.country.active_hint')]) ?>
            <?php if (isset($errors['is_active'])): ?><p class="invalid-feedback d-block mt-n2 mb-3"><?= e($errors['is_active']) ?></p><?php endif; ?>
            <?= cmsadmin_partial('field', ['name' => 'sort_order', 'type' => 'number', 'label' => __('cmsadmin.sort_order'), 'value' => $value('sort_order', 0), 'error' => $errors['sort_order'] ?? null, 'attributes' => ['step' => 1]]) ?>
          </div>
          <div class="d-grid gap-2">
            <button class="btn btn-primary" type="submit"><?= e(__($isEdit ? 'cmsadmin.save' : 'cmsadmin.create')) ?></button>
            <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url('pays-sites')) ?>"><?= e(__('cmsadmin.cancel')) ?></a>
          </div>
        </section>
      </div>
    </aside>
  </div>
</form>
