<?php
/**
 * Bannière (lot 2.2). Sur l'emplacement « home_hero », l'image sert de diapositive au
 * diaporama de l'accueil et la légende indique le lieu photographié.
 *
 * @var int|null             $id
 * @var array<string,mixed>  $values
 * @var array<string,string> $errors
 * @var list<string>         $placements
 * @var string|null          $image
 */
$value = static fn (string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
$field = static fn (array $o): string => cmsadmin_partial('field', $o + ['error' => $errors[$o['name']] ?? null, 'value' => $value($o['name'])]);
$placementOptions = [];
foreach ($placements as $key) {
    $placementOptions[$key] = __('content.placements.' . $key);
}
$date = static fn (string $key): string => substr((string) $value($key, ''), 0, 10);
?>
<?= cmsadmin_partial('page-header', [
    'title' => __($id === null ? 'content.banners.create' : 'content.banners.edit'),
    'subtitle' => __('content.banners.form_subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('content.banners.title'), 'url' => 'bannieres'], ['label' => __($id === null ? 'content.banners.create' : 'content.banners.edit')]],
]) ?>

<form method="post" action="<?= e(cmsadmin_url('bannieres' . ($id !== null ? '/' . $id : ''))) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="row g-4">
    <div class="col-xxl-8">
      <section class="card im-panel">
        <?= $field(['name' => 'placement', 'label' => __('content.fields.placement'), 'type' => 'select', 'options' => $placementOptions, 'required' => true]) ?>

        <div class="mb-3">
          <label class="form-label" for="f-image"><?= e(__('content.fields.image')) ?><?= $id === null ? '' : ' <span class="form-text">' . e(__('content.help.image_keep')) . '</span>' ?></label>
          <?php if ($image !== null): ?>
          <img class="im-preview" src="<?= e($image) ?>" alt="" width="320" height="180">
          <?php endif; ?>
          <input class="form-control<?= isset($errors['image']) ? ' is-invalid' : '' ?>" id="f-image" name="image" type="file" accept="image/jpeg,image/png,image/webp"<?= $id === null ? ' required' : '' ?>>
          <?php if (isset($errors['image'])): ?><p class="invalid-feedback d-block"><?= e($errors['image']) ?></p><?php endif; ?>
          <p class="form-text"><?= e(__('content.help.image')) ?></p>
        </div>

        <?= $field(['name' => 'title', 'label' => __('content.fields.title'), 'optional' => true, 'attributes' => ['maxlength' => 190]]) ?>
        <?= $field(['name' => 'subtitle', 'label' => __('content.fields.subtitle'), 'optional' => true, 'attributes' => ['maxlength' => 255]]) ?>
        <?= $field(['name' => 'caption', 'label' => __('content.fields.caption'), 'optional' => true, 'hint' => __('content.help.caption'), 'attributes' => ['maxlength' => 120]]) ?>
        <?= $field(['name' => 'link_url', 'label' => __('content.fields.link'), 'type' => 'url', 'optional' => true, 'attributes' => ['maxlength' => 255]]) ?>
      </section>
    </div>

    <div class="col-xxl-4">
      <section class="card im-panel">
        <?= cmsadmin_partial('switch', ['name' => 'is_active', 'label' => __('cmsadmin.active'), 'checked' => (int) $value('is_active', 1) === 1, 'hint' => __('content.help.is_active')]) ?>
        <?= cmsadmin_partial('field', ['name' => 'starts_at', 'label' => __('content.fields.starts_at'), 'type' => 'date', 'optional' => true, 'value' => $date('starts_at'), 'error' => $errors['starts_at'] ?? null]) ?>
        <?= cmsadmin_partial('field', ['name' => 'ends_at', 'label' => __('content.fields.ends_at'), 'type' => 'date', 'optional' => true, 'value' => $date('ends_at'), 'hint' => __('content.help.period'), 'error' => $errors['ends_at'] ?? null]) ?>
        <?= $field(['name' => 'sort_order', 'label' => __('content.fields.sort_order'), 'type' => 'number', 'optional' => true, 'hint' => __('content.help.sort_order'), 'attributes' => ['min' => 0, 'max' => 999]]) ?>
      </section>
    </div>
  </div>

  <div class="im-form-actions">
    <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url('bannieres')) ?>"><?= e(__('cmsadmin.cancel')) ?></a>
    <button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.save')) ?></button>
  </div>
</form>
