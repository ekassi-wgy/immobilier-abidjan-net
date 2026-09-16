<?php
/**
 * Exports CSV (lot 1.12). Rôle `staff`, toujours limité au pays du site courant.
 *
 * @var list<int>    $periods      Jours proposés (0 = tout l'historique)
 * @var list<string> $statuses     Statuts d'annonce
 * @var list<string> $leadStatuses Statuts de demande de contact
 * @var int          $maxRows
 */
$periodLabel = static fn (int $days): string => $days === 0
    ? __('exports.period_all')
    : __n('exports.period_days', $days);
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('exports.title'),
    'subtitle' => __('exports.subtitle', ['max' => format_number($maxRows)]),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('exports.title')]],
]) ?>

<div class="row g-4">
  <div class="col-xxl-6">
    <section class="card im-panel h-100">
      <header class="im-panel__head">
        <div>
          <h2 class="im-panel__title"><span class="mdi mdi-home-city-outline" aria-hidden="true"></span> <?= e(__('exports.properties_title')) ?></h2>
          <p class="im-panel__subtitle"><?= e(__('exports.properties_subtitle')) ?></p>
        </div>
      </header>

      <form method="get" action="<?= e(cmsadmin_url('exports/annonces.csv')) ?>">
        <?= cmsadmin_partial('field', [
            'name' => 'periode',
            'label' => __('exports.period'),
            'type' => 'select',
            'value' => 90,
            'options' => array_combine($periods, array_map($periodLabel, $periods)),
            'class' => 'mb-3',
        ]) ?>
        <?= cmsadmin_partial('field', [
            'name' => 'statut',
            'label' => __('cmsadmin.state'),
            'type' => 'select',
            'options' => array_combine(
                array_slice($statuses, 1),
                array_map(static fn (string $s): string => __('properties.status.' . $s), array_slice($statuses, 1))
            ),
            'placeholder' => __('exports.all_statuses'),
            'class' => 'mb-4',
        ]) ?>
        <button class="btn btn-primary" type="submit"><span class="mdi mdi-tray-arrow-down" aria-hidden="true"></span> <?= e(__('exports.download')) ?></button>
      </form>

      <p class="form-text mt-3"><?= e(__('exports.properties_note')) ?></p>
    </section>
  </div>

  <div class="col-xxl-6">
    <section class="card im-panel h-100">
      <header class="im-panel__head">
        <div>
          <h2 class="im-panel__title"><span class="mdi mdi-email-outline" aria-hidden="true"></span> <?= e(__('exports.leads_title')) ?></h2>
          <p class="im-panel__subtitle"><?= e(__('exports.leads_subtitle')) ?></p>
        </div>
      </header>

      <form method="get" action="<?= e(cmsadmin_url('exports/contacts.csv')) ?>">
        <?= cmsadmin_partial('field', [
            'name' => 'periode',
            'label' => __('exports.period'),
            'type' => 'select',
            'value' => 90,
            'options' => array_combine($periods, array_map($periodLabel, $periods)),
            'class' => 'mb-3',
        ]) ?>
        <?= cmsadmin_partial('field', [
            'name' => 'statut',
            'label' => __('cmsadmin.state'),
            'type' => 'select',
            'options' => array_combine(
                array_slice($leadStatuses, 1),
                array_map(static fn (string $s): string => __('leads.status.' . $s), array_slice($leadStatuses, 1))
            ),
            'placeholder' => __('exports.all_statuses'),
            'class' => 'mb-4',
        ]) ?>
        <button class="btn btn-primary" type="submit"><span class="mdi mdi-tray-arrow-down" aria-hidden="true"></span> <?= e(__('exports.download')) ?></button>
      </form>

      <p class="form-text mt-3"><?= e(__('exports.leads_note')) ?></p>
    </section>
  </div>
</div>
