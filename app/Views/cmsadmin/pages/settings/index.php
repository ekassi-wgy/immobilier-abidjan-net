<?php
/**
 * Paramètres de la plateforme (lot 1.12). Super Admin uniquement.
 *
 * Un champ numérique laissé vide signifie « décision en attente » : le code retombe sur sa valeur
 * par défaut. C'est le cas de la commission tant que le client ne l'a pas tranchée.
 *
 * @var array<string, array<string, array<string,mixed>>> $groups   [section => [clé => champ]]
 * @var list<string>         $modes    Modes de commission
 * @var list<string>         $bases    Assiettes de commission
 * @var array<string,string> $errors
 * @var string               $currency
 */
$modeOptions = [];
foreach ($modes as $mode) {
    $modeOptions[$mode] = __('settings.commission.modes.' . $mode);
}
$baseOptions = [];
foreach ($bases as $base) {
    $baseOptions[$base] = __('settings.commission.bases.' . $base);
}
$render = static function (array $field) use ($errors, $currency, $modeOptions, $baseOptions): string {
    $options = [
        'name' => $field['name'],
        'label' => $field['label'],
        'value' => $field['value'],
        'hint' => $field['help'],
        'error' => $errors[$field['name']] ?? null,
        'class' => 'mb-4',
    ];

    if ($field['type'] === 'enum' || $field['type'] === 'base') {
        return cmsadmin_partial('field', $options + [
            'type' => 'select',
            'options' => $field['type'] === 'enum' ? $modeOptions : $baseOptions,
            'placeholder' => __('settings.commission.undecided'),
        ]);
    }

    return cmsadmin_partial('field', $options + [
        'type' => 'number',
        'optional' => true,
        'suffix' => $field['name'] === 'commission_rate_percent' ? '%' : (str_starts_with($field['name'], 'commission_') ? $currency : null),
        'attributes' => ['step' => $field['type'] === 'int' ? '1' : '0.01', 'min' => (string) ($field['min'] ?? 0), 'max' => (string) ($field['max'] ?? 0)],
    ]);
};
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('settings.title'),
    'subtitle' => __('settings.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('settings.title')]],
]) ?>

<form method="post" action="<?= e(cmsadmin_url('parametres')) ?>" class="im-form-grid">
  <?= csrf_field() ?>

  <div class="row g-4">
    <div class="col-xxl-8">
      <section class="card im-panel">
        <header class="im-panel__head">
          <div>
            <h2 class="im-panel__title"><span class="im-step">01</span> <?= e(__('settings.groups.commission')) ?></h2>
            <p class="im-panel__subtitle"><?= e(__('settings.groups.commission_hint')) ?></p>
          </div>
        </header>
        <?php foreach ($groups['commission'] as $field): ?>
        <?= $render($field) ?>
        <?php endforeach; ?>
      </section>

      <section class="card im-panel mt-4">
        <header class="im-panel__head">
          <div>
            <h2 class="im-panel__title"><span class="im-step">02</span> <?= e(__('settings.groups.listing')) ?></h2>
            <p class="im-panel__subtitle"><?= e(__('settings.groups.listing_hint')) ?></p>
          </div>
        </header>
        <div class="row">
          <?php foreach ($groups['listing'] as $field): ?>
          <div class="col-md-6"><?= $render($field) ?></div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>

    <div class="col-xxl-4">
      <section class="card im-panel">
        <header class="im-panel__head">
          <div>
            <h2 class="im-panel__title"><span class="im-step">03</span> <?= e(__('settings.groups.workflow')) ?></h2>
            <p class="im-panel__subtitle"><?= e(__('settings.groups.workflow_hint')) ?></p>
          </div>
        </header>
        <?php foreach ($groups['workflow'] as $field): ?>
        <?= cmsadmin_partial('switch', [
            'name' => $field['name'],
            'label' => $field['label'],
            'checked' => (bool) $field['value'],
            'hint' => $field['help'],
            'class' => 'mb-4',
        ]) ?>
        <?php endforeach; ?>
      </section>

      <section class="card im-panel mt-4">
        <header class="im-panel__head">
          <div>
            <h2 class="im-panel__title"><span class="im-step">04</span> <?= e(__('settings.groups.security')) ?></h2>
            <p class="im-panel__subtitle"><?= e(__('settings.groups.security_hint')) ?></p>
          </div>
        </header>
        <?php foreach ($groups['security'] as $field): ?>
        <?= $render($field) ?>
        <?php endforeach; ?>
      </section>

      <p class="form-text mt-3"><span class="mdi mdi-information-outline" aria-hidden="true"></span> <?= e(__('settings.contacts_elsewhere')) ?></p>
    </div>
  </div>

  <div class="im-form-actions">
    <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url()) ?>"><?= e(__('cmsadmin.cancel')) ?></a>
    <button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.save')) ?></button>
  </div>
</form>
