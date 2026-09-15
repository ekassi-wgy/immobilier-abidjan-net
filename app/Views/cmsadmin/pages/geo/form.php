<?php
/**
 * Création / modification d'une ville, commune ou quartier.
 *
 * @var string                  $level
 * @var string                  $path
 * @var array<string,mixed>|null $item
 * @var array<string,mixed>     $values
 * @var array<string,string>    $errors
 * @var array                   $country
 * @var array<int,string>       $parents  Villes (commune) ou communes (quartier)
 * @var array<string,int>       $usage
 * @var string                  $back
 */
$isEdit = $item !== null;
$base = 'geo/' . $path;
$value = static fn (string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
$parentField = ['city' => null, 'commune' => 'city_id', 'district' => 'commune_id'][$level];
$action = $isEdit ? cmsadmin_url($base . '/' . $item['id']) : cmsadmin_url($base);
?>
<?= cmsadmin_partial('page-header', [
    'title' => $isEdit ? __("geo.{$level}.edit", ['name' => $item['name']]) : __("geo.{$level}.create"),
    'subtitle' => __('geo.subtitle', ['country' => $country['name']]),
    'breadcrumb' => [
        ['label' => __('auth.dashboard'), 'url' => '/'],
        ['label' => __("geo.{$level}.plural"), 'url' => $base],
        ['label' => $isEdit ? $item['name'] : __("geo.{$level}.create")],
    ],
]) ?>

<?php if ($errors !== []): ?>
<div class="im-flash im-flash--error" role="alert">
  <span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span>
  <p><?= e(__('cmsadmin.form_errors', ['count' => count($errors)])) ?></p>
</div>
<?php endif; ?>

<form class="im-form" method="post" action="<?= e($action) ?>" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="_back" value="<?= e($back) ?>">

  <div class="im-form__layout">
    <div class="im-form__main">
      <section class="card im-panel im-form-section">
        <header class="im-form-section__head">
          <h2 class="im-panel__title"><?= e(__('cmsadmin.general')) ?></h2>
        </header>

        <div class="row g-3">
          <?php if ($parentField !== null): ?>
          <div class="col-12">
            <?= cmsadmin_partial('field', [
                'name' => $parentField, 'type' => 'select', 'label' => __($level === 'commune' ? 'geo.city.singular' : 'geo.commune.singular'),
                'options' => $parents, 'placeholder' => '', 'value' => $value($parentField), 'required' => true,
                'error' => $errors[$parentField] ?? null, 'class' => '', 'attributes' => ['data-im-select' => '', 'data-placeholder' => __('cmsadmin.choose')],
            ]) ?>
          </div>
          <?php endif; ?>
          <div class="col-md-7">
            <?= cmsadmin_partial('field', [
                'name' => 'name', 'label' => __('cmsadmin.name'), 'value' => $value('name'), 'required' => true,
                'error' => $errors['name'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 100, 'data-slug-source' => 'slug', 'autofocus' => 'autofocus'],
            ]) ?>
          </div>
          <div class="col-md-5">
            <?= cmsadmin_partial('field', [
                'name' => 'slug', 'label' => __('cmsadmin.slug'), 'value' => $value('slug'), 'optional' => !$isEdit,
                'hint' => $isEdit ? __('cmsadmin.slug_warning') : __('cmsadmin.slug_hint'),
                'error' => $errors['slug'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 120, 'pattern' => '[a-z0-9-]+', 'spellcheck' => 'false'],
            ]) ?>
          </div>
        </div>
      </section>

      <section class="card im-panel im-form-section">
        <header class="im-form-section__head">
          <h2 class="im-panel__title"><?= e(__('geo.map_position')) ?></h2>
        </header>
        <p class="im-panel__subtitle"><?= e(__('geo.map_hint')) ?></p>
        <div class="row g-3">
          <div class="col-6">
            <?= cmsadmin_partial('field', [
                'name' => 'latitude', 'label' => __('geo.latitude'), 'value' => $value('latitude'), 'optional' => true,
                'error' => $errors['latitude'] ?? null, 'class' => '', 'attributes' => ['inputmode' => 'decimal', 'placeholder' => '5.3599'],
            ]) ?>
          </div>
          <div class="col-6">
            <?= cmsadmin_partial('field', [
                'name' => 'longitude', 'label' => __('geo.longitude'), 'value' => $value('longitude'), 'optional' => true,
                'error' => $errors['longitude'] ?? null, 'class' => '', 'attributes' => ['inputmode' => 'decimal', 'placeholder' => '-4.0083'],
            ]) ?>
          </div>
        </div>
      </section>
    </div>

    <aside class="im-form__aside">
      <div class="im-sticky">
        <section class="card im-panel">
          <h2 class="im-panel__title"><?= e(__('cmsadmin.publication')) ?></h2>
          <div class="mt-3">
            <?= cmsadmin_partial('switch', ['name' => 'is_active', 'label' => __('cmsadmin.active'), 'checked' => (bool) $value('is_active', 1), 'hint' => __('geo.active_hint')]) ?>
            <?= cmsadmin_partial('field', [
                'name' => 'sort_order', 'type' => 'number', 'label' => __('cmsadmin.sort_order'), 'value' => $value('sort_order', 0),
                'hint' => __('cmsadmin.sort_order_hint'), 'error' => $errors['sort_order'] ?? null, 'attributes' => ['step' => 1],
            ]) ?>
          </div>
          <?php if ($isEdit && $usage !== []): ?>
          <dl class="im-meta-list">
            <?php foreach ($usage as $key => $count): ?>
            <div><dt><?= e(__($key . '_label')) ?></dt><dd class="im-num"><?= e(format_number($count)) ?></dd></div>
            <?php endforeach; ?>
          </dl>
          <?php endif; ?>
          <div class="d-grid gap-2">
            <button class="btn btn-primary" type="submit"><?= e(__($isEdit ? 'cmsadmin.save' : 'cmsadmin.create')) ?></button>
            <?php if (!$isEdit): ?>
            <button class="btn im-btn-ghost" type="submit" name="_intent" value="another"><?= e(__('cmsadmin.create_and_add')) ?></button>
            <?php endif; ?>
            <a class="btn im-btn-ghost" href="<?= e($back !== '' ? url($back) : cmsadmin_url($base)) ?>"><?= e(__('cmsadmin.cancel')) ?></a>
          </div>
        </section>
      </div>
    </aside>
  </div>
</form>
