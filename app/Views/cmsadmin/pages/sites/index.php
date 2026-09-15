<?php
/**
 * Pays & sites (multisite).
 *
 * @var list<array<string,mixed>> $countries
 * @var list<array<string,mixed>> $sites
 * @var int|null                  $currentSiteId
 */
$statusVariant = ['active' => 'published', 'maintenance' => 'pending', 'disabled' => 'unpublished'];
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('sites.title'),
    'subtitle' => __('sites.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('sites.title')]],
]) ?>

<section class="card im-panel im-panel--flush mb-4">
  <div class="im-panel-bar">
    <h2 class="im-panel__title im-panel-bar__title"><?= e(__('sites.site.plural')) ?></h2>
    <div class="im-panel-bar__actions">
      <a class="btn btn-primary btn-sm" href="<?= e(cmsadmin_url('pays-sites/sites/ajouter')) ?>"><span class="mdi mdi-plus" aria-hidden="true"></span> <?= e(__('sites.site.create')) ?></a>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table im-table im-table--list">
      <thead>
        <tr>
          <th scope="col"><?= e(__('sites.site.singular')) ?></th>
          <th scope="col"><?= e(__('sites.country.singular')) ?></th>
          <th scope="col"><?= e(__('sites.primary_domain')) ?></th>
          <th scope="col"><?= e(__('sites.locale')) ?></th>
          <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
          <th scope="col"><span class="visually-hidden"><?= e(__('cmsadmin.actions')) ?></span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sites as $site): $editUrl = cmsadmin_url('pays-sites/sites/' . $site['id'] . '/modifier'); ?>
        <tr>
          <td>
            <a class="im-cell-main im-cell-link" href="<?= e($editUrl) ?>"><?= e($site['name']) ?></a>
            <span class="im-cell-sub"><code><?= e($site['code']) ?></code><?php if ((int) $site['id'] === $currentSiteId): ?> <span class="im-tag"><?= e(__('sites.current')) ?></span><?php endif; ?></span>
          </td>
          <td><span class="im-cell-main"><?= e($site['country_name']) ?></span><span class="im-cell-sub"><?= e($site['iso2']) ?> · <?= e($site['currency_symbol']) ?></span></td>
          <td>
            <?php if ($site['primary_host'] !== null): ?>
            <span class="im-cell-main"><?= e($site['primary_host']) ?></span>
            <?php else: ?>
            <span class="im-cell-sub"><?= e(__('sites.no_production_domain')) ?></span>
            <?php endif; ?>
            <span class="im-cell-sub"><?= e(__('sites.domains_count', ['count' => (int) $site['domains_count']])) ?></span>
          </td>
          <td><?= e(strtoupper((string) $site['default_locale'])) ?></td>
          <td><?= cmsadmin_partial('state-badge', ['label' => __('sites.status.' . $site['status']), 'variant' => $statusVariant[$site['status']] ?? 'unpublished']) ?></td>
          <td class="text-end">
            <?= cmsadmin_partial('row-actions', ['label' => $site['name'], 'items' => [
                ['url' => $editUrl, 'label' => __('cmsadmin.edit'), 'icon' => 'mdi-pencil-outline'],
                ['url' => $editUrl . '#domaines', 'label' => __('sites.domains'), 'icon' => 'mdi-web'],
            ]]) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card im-panel im-panel--flush">
  <div class="im-panel-bar">
    <h2 class="im-panel__title im-panel-bar__title"><?= e(__('sites.country.plural')) ?></h2>
    <div class="im-panel-bar__actions">
      <a class="btn im-btn-ghost btn-sm" href="<?= e(cmsadmin_url('pays-sites/pays/ajouter')) ?>"><span class="mdi mdi-plus" aria-hidden="true"></span> <?= e(__('sites.country.create')) ?></a>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table im-table im-table--list">
      <thead>
        <tr>
          <th scope="col"><?= e(__('sites.country.singular')) ?></th>
          <th scope="col"><?= e(__('sites.country.currency')) ?></th>
          <th scope="col"><?= e(__('sites.country.phone_prefix')) ?></th>
          <th scope="col"><?= e(__('sites.country.timezone')) ?></th>
          <th scope="col" class="text-end"><?= e(__('geo.city.plural')) ?></th>
          <th scope="col" class="text-end"><?= e(__('sites.site.plural')) ?></th>
          <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
          <th scope="col"><span class="visually-hidden"><?= e(__('cmsadmin.actions')) ?></span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($countries as $country): $editUrl = cmsadmin_url('pays-sites/pays/' . $country['id'] . '/modifier'); ?>
        <tr<?= (int) $country['is_active'] === 0 ? ' class="is-muted"' : '' ?>>
          <td><a class="im-cell-main im-cell-link" href="<?= e($editUrl) ?>"><?= e($country['name']) ?></a><span class="im-cell-sub"><?= e($country['iso2']) ?></span></td>
          <td><span class="im-cell-main"><?= e($country['currency_symbol']) ?></span><span class="im-cell-sub"><?= e($country['currency_code']) ?></span></td>
          <td class="im-num"><?= e($country['phone_prefix']) ?></td>
          <td><?= e($country['timezone']) ?></td>
          <td class="text-end im-num"><?= e(format_number((int) $country['cities_count'])) ?></td>
          <td class="text-end im-num"><?= e($country['sites_count']) ?></td>
          <td><?= cmsadmin_partial('state-badge', ['active' => (bool) $country['is_active']]) ?></td>
          <td class="text-end">
            <?= cmsadmin_partial('row-actions', ['label' => $country['name'], 'items' => array_values(array_filter([
                ['url' => $editUrl, 'label' => __('cmsadmin.edit'), 'icon' => 'mdi-pencil-outline'],
                (int) $country['sites_count'] === 0 ? ['url' => cmsadmin_url('pays-sites/sites/ajouter?pays=' . $country['id']), 'label' => __('sites.site.create'), 'icon' => 'mdi-plus'] : null,
            ]))]) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
