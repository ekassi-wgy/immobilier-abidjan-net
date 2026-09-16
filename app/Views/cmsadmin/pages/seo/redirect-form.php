<?php
/**
 * Redirection permanente (lot 2.1).
 *
 * @var int|null             $id
 * @var array<string,mixed>  $values
 * @var array<string,string> $errors
 * @var list<int>            $codes
 */
$value = static fn (string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
$field = static fn (array $options): string => cmsadmin_partial('field', $options + [
    'error' => $errors[$options['name']] ?? null,
    'value' => $value($options['name']),
]);
$codeOptions = [];
foreach ($codes as $code) {
    $codeOptions[$code] = $code . ' — ' . __('seo.redirects.codes.' . $code);
}
?>
<?= cmsadmin_partial('page-header', [
    'title' => __($id === null ? 'seo.redirects.create' : 'seo.redirects.edit'),
    'subtitle' => __('seo.redirects.form_subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('seo.redirects.title'), 'url' => 'seo/redirections'], ['label' => __($id === null ? 'seo.redirects.create' : 'seo.redirects.edit')]],
]) ?>

<form method="post" action="<?= e(cmsadmin_url('seo/redirections' . ($id !== null ? '/' . $id : ''))) ?>">
  <?= csrf_field() ?>

  <div class="row g-4">
    <div class="col-xxl-8">
      <section class="card im-panel">
        <?= $field(['name' => 'source_path', 'label' => __('seo.redirects.fields.source'), 'required' => true,
                    'hint' => __('seo.redirects.help.source'), 'attributes' => ['maxlength' => 255, 'placeholder' => '/ancienne-page']]) ?>
        <?= $field(['name' => 'target_path', 'label' => __('seo.redirects.fields.target'), 'required' => true,
                    'hint' => __('seo.redirects.help.target'), 'attributes' => ['maxlength' => 255, 'placeholder' => '/acheter/appartement/abidjan']]) ?>
      </section>
    </div>

    <div class="col-xxl-4">
      <section class="card im-panel">
        <?= $field(['name' => 'http_code', 'label' => __('seo.redirects.fields.code'), 'type' => 'select',
                    'options' => $codeOptions, 'hint' => __('seo.redirects.help.code')]) ?>
        <?= cmsadmin_partial('switch', [
            'name' => 'is_active',
            'label' => __('cmsadmin.active'),
            'checked' => (int) $value('is_active', 1) === 1,
            'hint' => __('seo.redirects.help.active'),
        ]) ?>
      </section>
    </div>
  </div>

  <div class="im-form-actions">
    <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url('seo/redirections')) ?>"><?= e(__('cmsadmin.cancel')) ?></a>
    <button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.save')) ?></button>
  </div>
</form>
