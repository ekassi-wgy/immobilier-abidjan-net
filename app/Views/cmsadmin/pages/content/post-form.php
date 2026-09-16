<?php
/**
 * Rédaction d'une actualité (lot 2.2).
 *
 * @var int|null             $id
 * @var array<string,mixed>  $values
 * @var array<string,string> $errors
 * @var string|null          $cover  Aperçu de la couverture actuelle
 */
$value = static fn (string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
$field = static fn (array $o): string => cmsadmin_partial('field', $o + ['error' => $errors[$o['name']] ?? null, 'value' => $value($o['name'])]);
?>
<?= cmsadmin_partial('page-header', [
    'title' => __($id === null ? 'content.posts.create' : 'content.posts.edit'),
    'subtitle' => __('content.posts.form_subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('content.posts.title'), 'url' => 'actualites'], ['label' => __($id === null ? 'content.posts.create' : 'content.posts.edit')]],
]) ?>

<form method="post" action="<?= e(cmsadmin_url('actualites' . ($id !== null ? '/' . $id : ''))) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="row g-4">
    <div class="col-xxl-8">
      <section class="card im-panel">
        <?= $field(['name' => 'title', 'label' => __('content.fields.title'), 'required' => true, 'attributes' => ['maxlength' => 190, 'data-slug-source' => 'f-slug']]) ?>
        <?= $field(['name' => 'slug', 'label' => __('content.fields.slug'), 'required' => true, 'hint' => __('content.help.slug'), 'attributes' => ['maxlength' => 190]]) ?>
        <?= $field(['name' => 'excerpt', 'label' => __('content.fields.excerpt'), 'type' => 'textarea', 'optional' => true,
                    'hint' => __('content.help.excerpt'), 'attributes' => ['maxlength' => 500, 'rows' => 3]]) ?>
        <?= $field(['name' => 'content', 'label' => __('content.fields.content'), 'type' => 'textarea', 'required' => true,
                    'hint' => __('content.help.content'), 'attributes' => ['rows' => 20]]) ?>
      </section>
    </div>

    <div class="col-xxl-4">
      <section class="card im-panel">
        <?= $field(['name' => 'status', 'label' => __('cmsadmin.state'), 'type' => 'select',
                    'options' => ['draft' => __('content.draft'), 'published' => __('content.published')],
                    'hint' => __('content.help.status')]) ?>

        <div class="mb-3">
          <label class="form-label" for="f-cover"><?= e(__('content.fields.cover')) ?></label>
          <?php if ($cover !== null): ?>
          <img class="im-preview" src="<?= e($cover) ?>" alt="" width="240" height="160">
          <?php endif; ?>
          <input class="form-control<?= isset($errors['cover']) ? ' is-invalid' : '' ?>" id="f-cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp">
          <?php if (isset($errors['cover'])): ?><p class="invalid-feedback d-block"><?= e($errors['cover']) ?></p><?php endif; ?>
          <p class="form-text"><?= e(__('content.help.cover')) ?></p>
        </div>

        <?= $field(['name' => 'meta_title', 'label' => __('seo.fields.meta_title'), 'optional' => true, 'attributes' => ['maxlength' => 190]]) ?>
        <?= $field(['name' => 'meta_description', 'label' => __('seo.fields.meta_description'), 'type' => 'textarea', 'optional' => true, 'attributes' => ['maxlength' => 320, 'rows' => 3]]) ?>
      </section>
    </div>
  </div>

  <div class="im-form-actions">
    <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url('actualites')) ?>"><?= e(__('cmsadmin.cancel')) ?></a>
    <button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.save')) ?></button>
  </div>
</form>
