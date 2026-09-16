<?php
/**
 * Rédaction d'une page éditoriale ou légale (lot 2.2).
 * Le contenu est du HTML : il n'est pas échappé à l'affichage public, seule l'équipe l'écrit.
 *
 * @var int|null             $id
 * @var array<string,mixed>  $values
 * @var array<string,string> $errors
 * @var bool                 $isSystem  Page attendue par le pied de page (code non nul)
 */
$value = static fn (string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
$field = static fn (array $o): string => cmsadmin_partial('field', $o + ['error' => $errors[$o['name']] ?? null, 'value' => $value($o['name'])]);
?>
<?= cmsadmin_partial('page-header', [
    'title' => __($id === null ? 'content.pages.create' : 'content.pages.edit'),
    'subtitle' => $isSystem ? __('content.pages.system_hint') : __('content.pages.form_subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('content.pages.title'), 'url' => 'pages'], ['label' => __($id === null ? 'content.pages.create' : 'content.pages.edit')]],
]) ?>

<form method="post" action="<?= e(cmsadmin_url('pages' . ($id !== null ? '/' . $id : ''))) ?>">
  <?= csrf_field() ?>

  <div class="row g-4">
    <div class="col-xxl-8">
      <section class="card im-panel">
        <?= $field(['name' => 'title', 'label' => __('content.fields.title'), 'required' => true, 'attributes' => ['maxlength' => 190, 'data-slug-source' => 'f-slug']]) ?>
        <?= $field(['name' => 'slug', 'label' => __('content.fields.slug'), 'required' => true, 'hint' => __('content.help.slug'), 'attributes' => ['maxlength' => 190]]) ?>
        <?= $field(['name' => 'content', 'label' => __('content.fields.content'), 'type' => 'textarea', 'required' => true,
                    'hint' => __('content.help.content'), 'attributes' => ['rows' => 20]]) ?>
      </section>
    </div>

    <div class="col-xxl-4">
      <section class="card im-panel">
        <?= cmsadmin_partial('switch', [
            'name' => 'is_published',
            'label' => __('content.fields.is_published'),
            'checked' => (int) $value('is_published', 0) === 1,
            'hint' => __('content.help.is_published'),
        ]) ?>
        <?= $field(['name' => 'meta_title', 'label' => __('seo.fields.meta_title'), 'optional' => true, 'attributes' => ['maxlength' => 190]]) ?>
        <?= $field(['name' => 'meta_description', 'label' => __('seo.fields.meta_description'), 'type' => 'textarea', 'optional' => true, 'attributes' => ['maxlength' => 320, 'rows' => 3]]) ?>
      </section>
    </div>
  </div>

  <div class="im-form-actions">
    <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url('pages')) ?>"><?= e(__('cmsadmin.cancel')) ?></a>
    <button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.save')) ?></button>
  </div>
</form>
