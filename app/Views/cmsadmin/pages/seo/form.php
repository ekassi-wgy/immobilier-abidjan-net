<?php
/**
 * Balises méta d'une URL (lot 2.1). Un champ laissé vide ne remplace rien : la page garde
 * ce que le contrôleur calcule.
 *
 * @var int|null             $id
 * @var array<string,mixed>  $values
 * @var array<string,string> $errors
 */
$value = static fn (string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
$field = static fn (array $options): string => cmsadmin_partial('field', $options + [
    'error' => $errors[$options['name']] ?? null,
    'value' => $value($options['name']),
]);
?>
<?= cmsadmin_partial('page-header', [
    'title' => __($id === null ? 'seo.create' : 'seo.edit'),
    'subtitle' => __('seo.form_subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('seo.title'), 'url' => 'seo'], ['label' => __($id === null ? 'seo.create' : 'seo.edit')]],
]) ?>

<form method="post" action="<?= e(cmsadmin_url('seo' . ($id !== null ? '/' . $id : ''))) ?>">
  <?= csrf_field() ?>

  <div class="row g-4">
    <div class="col-xxl-8">
      <section class="card im-panel">
        <header class="im-panel__head">
          <div>
            <h2 class="im-panel__title"><span class="im-step">01</span> <?= e(__('seo.groups.page')) ?></h2>
            <p class="im-panel__subtitle"><?= e(__('seo.groups.page_hint')) ?></p>
          </div>
        </header>
        <?= $field(['name' => 'path', 'label' => __('seo.fields.path'), 'required' => true, 'hint' => __('seo.help.path'),
                    'attributes' => ['maxlength' => 255, 'placeholder' => '/acheter/appartement/abidjan']]) ?>
        <?= $field(['name' => 'meta_title', 'label' => __('seo.fields.meta_title'), 'optional' => true, 'hint' => __('seo.help.meta_title'), 'attributes' => ['maxlength' => 190]]) ?>
        <?= $field(['name' => 'meta_description', 'label' => __('seo.fields.meta_description'), 'type' => 'textarea', 'optional' => true,
                    'hint' => __('seo.help.meta_description'), 'attributes' => ['maxlength' => 320, 'rows' => 3]]) ?>
        <?= $field(['name' => 'intro_text', 'label' => __('seo.fields.intro_text'), 'type' => 'textarea', 'optional' => true,
                    'hint' => __('seo.help.intro_text'), 'attributes' => ['rows' => 5]]) ?>
      </section>
    </div>

    <div class="col-xxl-4">
      <section class="card im-panel">
        <header class="im-panel__head">
          <div>
            <h2 class="im-panel__title"><span class="im-step">02</span> <?= e(__('seo.groups.sharing')) ?></h2>
            <p class="im-panel__subtitle"><?= e(__('seo.groups.sharing_hint')) ?></p>
          </div>
        </header>
        <?= $field(['name' => 'og_image_path', 'label' => __('seo.fields.og_image_path'), 'optional' => true,
                    'hint' => __('seo.help.og_image_path'), 'attributes' => ['maxlength' => 255, 'placeholder' => 'uploads/ci/seo/partage.jpg']]) ?>

        <?= cmsadmin_partial('switch', [
            'name' => 'noindex',
            'label' => __('seo.fields.noindex'),
            'checked' => (int) $value('noindex', 0) === 1,
            'hint' => __('seo.help.noindex'),
        ]) ?>
      </section>
    </div>
  </div>

  <div class="im-form-actions">
    <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url('seo')) ?>"><?= e(__('cmsadmin.cancel')) ?></a>
    <button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.save')) ?></button>
  </div>
</form>
