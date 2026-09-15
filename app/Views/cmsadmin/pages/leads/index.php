<?php
/**
 * Demandes de contact reçues : onglets par statut, filtres et pagination côté serveur.
 * Une agence ne voit que les demandes qui lui sont adressées (filtrage côté contrôleur).
 *
 * @var list<array<string,mixed>> $rows
 * @var array                     $filters  ['q','statut','type','periode']
 * @var array<string,int>         $counts
 * @var array<string,string>      $types
 * @var bool                      $isAgency
 * @var array                     $pagination
 */
$query = (string) app()->request()?->server('QUERY_STRING', '');
$current = '/cmsadmin/contacts' . ($query !== '' ? '?' . $query : '');
$activeFilters = array_filter($filters);
$searchFilters = array_filter(array_diff_key($filters, ['statut' => true]));
$statusVariant = ['new' => 'pending', 'read' => 'archived', 'in_progress' => 'published', 'closed' => 'unpublished', 'spam' => 'rejected'];
$typeIcon = ['property_contact' => 'mdi-home-outline', 'agency_contact' => 'mdi-office-building-outline', 'general_contact' => 'mdi-email-outline', 'property_submission' => 'mdi-key-outline'];
$tabUrl = static fn (string $status): string => cmsadmin_url('contacts' . ($status !== '' ? '?statut=' . $status : ''));
$periods = ['7j' => __('leads.period.7d'), '30j' => __('leads.period.30d'), '90j' => __('leads.period.90d')];
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('leads.title'),
    'subtitle' => __($isAgency ? 'leads.subtitle_agency' : 'leads.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('leads.title')]],
]) ?>

<section class="card im-panel im-panel--flush">
  <nav class="im-tabs" aria-label="<?= e(__('cmsadmin.state')) ?>">
    <a class="im-tabs__item<?= $filters['statut'] === '' ? ' is-current' : '' ?>" href="<?= e($tabUrl('')) ?>"<?= $filters['statut'] === '' ? ' aria-current="page"' : '' ?>><?= e(__('leads.all')) ?> <span class="im-tabs__count"><?= e(format_number(array_sum($counts))) ?></span></a>
    <?php foreach ($counts as $status => $count): ?>
    <a class="im-tabs__item<?= $filters['statut'] === $status ? ' is-current' : '' ?>" href="<?= e($tabUrl($status)) ?>"<?= $filters['statut'] === $status ? ' aria-current="page"' : '' ?>><?= e(__('leads.status_plural.' . $status)) ?> <span class="im-tabs__count"><?= e(format_number($count)) ?></span></a>
    <?php endforeach; ?>
  </nav>

  <form class="im-filters" method="get" action="<?= e(cmsadmin_url('contacts')) ?>">
    <?php if ($filters['statut'] !== ''): ?><input type="hidden" name="statut" value="<?= e($filters['statut']) ?>"><?php endif; ?>
    <div class="im-filters__search">
      <span class="mdi mdi-magnify" aria-hidden="true"></span>
      <label class="visually-hidden" for="f-q"><?= e(__('cmsadmin.search')) ?></label>
      <input class="form-control" id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('leads.search_placeholder')) ?>">
    </div>
    <div class="im-filters__field">
      <label class="visually-hidden" for="f-type"><?= e(__('leads.type_label')) ?></label>
      <select class="form-select" id="f-type" name="type">
        <option value=""><?= e(__('leads.all_types')) ?></option>
        <?php foreach ($types as $type => $label): ?>
        <option value="<?= e($type) ?>"<?= $filters['type'] === $type ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="im-filters__field im-filters__field--narrow">
      <label class="visually-hidden" for="f-periode"><?= e(__('leads.period_label')) ?></label>
      <select class="form-select" id="f-periode" name="periode">
        <option value=""><?= e(__('leads.period.any')) ?></option>
        <?php foreach ($periods as $key => $label): ?>
        <option value="<?= e($key) ?>"<?= $filters['periode'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="im-filters__actions">
      <button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.filter')) ?></button>
      <?php if ($searchFilters !== []): ?><a class="im-link-muted" href="<?= e($tabUrl($filters['statut'])) ?>"><?= e(__('cmsadmin.reset')) ?></a><?php endif; ?>
    </div>
  </form>

  <?php if ($rows === []): ?>
    <?= cmsadmin_partial('empty-state', [
        'icon' => 'mdi-email-outline',
        'title' => __('leads.empty_title'),
        'text' => $activeFilters !== [] ? __('cmsadmin.empty_filtered') : __($isAgency ? 'leads.empty_agency_text' : 'leads.empty_text'),
    ]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table im-table--list">
      <thead>
        <tr>
          <th scope="col"><?= e(__('leads.sender')) ?></th>
          <th scope="col"><?= e(__('leads.subject')) ?></th>
          <?php if (!$isAgency): ?><th scope="col"><?= e(__('agencies.singular')) ?></th><?php endif; ?>
          <th scope="col"><?= e(__('leads.received')) ?></th>
          <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
          <th scope="col"><span class="visually-hidden"><?= e(__('cmsadmin.actions')) ?></span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): $url = cmsadmin_url('contacts/' . $row['id'] . '?retour=' . rawurlencode($current)); ?>
        <tr<?= in_array($row['status'], ['closed', 'spam'], true) ? ' class="is-muted"' : '' ?>>
          <td>
            <a class="im-cell-main im-cell-link" href="<?= e($url) ?>"><?= e($row['name']) ?></a>
            <span class="im-cell-sub"><?= e($row['email'] ?? $row['phone'] ?? '') ?></span>
          </td>
          <td>
            <span class="im-cell-main">
              <span class="mdi <?= e($typeIcon[$row['type']] ?? 'mdi-email-outline') ?>" aria-hidden="true"></span>
              <?= e($row['property_title'] ?? __('leads.type.' . $row['type'])) ?>
            </span>
            <span class="im-cell-sub"><?= e($row['property_reference'] !== null ? $row['property_reference'] . ' · ' . __('leads.type.' . $row['type']) : mb_substr(trim((string) $row['message']), 0, 70)) ?></span>
          </td>
          <?php if (!$isAgency): ?><td class="im-cell-sub-text"><?= e($row['agency_name'] ?? '—') ?></td><?php endif; ?>
          <td class="im-cell-sub-text"><?= e(substr((string) $row['created_at'], 0, 16)) ?></td>
          <td>
            <?= cmsadmin_partial('state-badge', ['label' => __('leads.status.' . $row['status']), 'variant' => $statusVariant[$row['status']] ?? 'unpublished']) ?>
            <?php if ($row['assigned_name'] !== null): ?><span class="im-cell-sub mt-1"><?= e($row['assigned_name']) ?></span><?php endif; ?>
          </td>
          <td class="text-end"><a class="btn btn-sm im-btn-ghost" href="<?= e($url) ?>"><?= e(__('leads.open')) ?></a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= cmsadmin_partial('pagination', $pagination + ['path' => 'contacts', 'query' => $activeFilters]) ?>
  <?php endif; ?>
</section>
