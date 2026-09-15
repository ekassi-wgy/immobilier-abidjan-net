<?php
/**
 * Liste des équipements, regroupés par famille.
 *
 * @var list<array<string,mixed>> $rows
 * @var array                     $filters ['q', 'groupe', 'etat']
 * @var array<string,string>      $groups
 */
$query = (string) app()->request()?->server('QUERY_STRING', '');
$current = '/cmsadmin/equipements' . ($query !== '' ? '?' . $query : '');
$activeFilters = array_filter($filters);
$sprite = asset('img/icons.svg');
$byGroup = [];
foreach ($rows as $row) {
    $byGroup[$row['feature_group']][] = $row;
}
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('catalog.features.title'),
    'subtitle' => __('catalog.features.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('catalog.title')], ['label' => __('catalog.features.title')]],
    'actions' => [['label' => __('catalog.features.create'), 'url' => 'equipements/ajouter', 'icon' => 'mdi-plus']],
]) ?>

<section class="card im-panel im-panel--flush">
  <form class="im-filters" method="get" action="<?= e(cmsadmin_url('equipements')) ?>">
    <div class="im-filters__search">
      <span class="mdi mdi-magnify" aria-hidden="true"></span>
      <label class="visually-hidden" for="f-q"><?= e(__('cmsadmin.search')) ?></label>
      <input class="form-control" id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('catalog.attributes.search_placeholder')) ?>">
    </div>
    <div class="im-filters__field">
      <label class="visually-hidden" for="f-groupe"><?= e(__('catalog.features.group')) ?></label>
      <select class="form-select" id="f-groupe" name="groupe">
        <option value=""><?= e(__('catalog.attributes.all_groups')) ?></option>
        <?php foreach ($groups as $code => $name): ?>
        <option value="<?= e($code) ?>"<?= $filters['groupe'] === $code ? ' selected' : '' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="im-filters__field im-filters__field--narrow">
      <label class="visually-hidden" for="f-etat"><?= e(__('cmsadmin.state')) ?></label>
      <select class="form-select" id="f-etat" name="etat">
        <option value=""><?= e(__('cmsadmin.all_states')) ?></option>
        <option value="actifs"<?= $filters['etat'] === 'actifs' ? ' selected' : '' ?>><?= e(__('cmsadmin.active_plural')) ?></option>
        <option value="inactifs"<?= $filters['etat'] === 'inactifs' ? ' selected' : '' ?>><?= e(__('cmsadmin.inactive_plural')) ?></option>
      </select>
    </div>
    <div class="im-filters__actions">
      <button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.filter')) ?></button>
      <?php if ($activeFilters !== []): ?><a class="im-link-muted" href="<?= e(cmsadmin_url('equipements')) ?>"><?= e(__('cmsadmin.reset')) ?></a><?php endif; ?>
    </div>
  </form>

  <?php if ($rows === []): ?>
    <?= cmsadmin_partial('empty-state', ['icon' => 'mdi-sofa-outline', 'title' => __('catalog.features.empty'), 'text' => $activeFilters !== [] ? __('cmsadmin.empty_filtered') : __('catalog.features.subtitle'), 'action' => $activeFilters !== [] ? null : ['label' => __('catalog.features.create'), 'url' => 'equipements/ajouter']]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table im-table--list">
      <thead>
        <tr>
          <th scope="col"><?= e(__('catalog.features.singular')) ?></th>
          <th scope="col"><?= e(__('catalog.attributes.display')) ?></th>
          <th scope="col" class="text-end"><?= e(__('geo.usage.properties_label')) ?></th>
          <th scope="col" class="text-end"><?= e(__('cmsadmin.sort_order')) ?></th>
          <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
          <th scope="col"><span class="visually-hidden"><?= e(__('cmsadmin.actions')) ?></span></th>
        </tr>
      </thead>
      <?php foreach ($byGroup as $group => $features): ?>
      <tbody>
        <tr class="im-table__group"><th scope="rowgroup" colspan="6"><?= e($groups[$group] ?? $group) ?></th></tr>
        <?php foreach ($features as $row): $editUrl = cmsadmin_url('equipements/' . $row['id'] . '/modifier'); ?>
        <tr<?= (int) $row['is_active'] === 0 ? ' class="is-muted"' : '' ?>>
          <td>
            <div class="im-feature-cell">
              <span class="im-icon-preview im-icon-preview--sm" aria-hidden="true"><?php if ($row['icon']): ?><svg><use href="<?= e($sprite) ?>#i-<?= e($row['icon']) ?>"></use></svg><?php endif; ?></span>
              <div>
                <a class="im-cell-main im-cell-link" href="<?= e($editUrl) ?>"><?= e($row['name']) ?></a>
                <span class="im-cell-sub"><code><?= e($row['code']) ?></code></span>
              </div>
            </div>
          </td>
          <td class="im-cell-sub-text"><?= e((int) $row['is_filterable'] === 1 ? __('catalog.attributes.filterable') : '—') ?></td>
          <td class="text-end im-num"><?= e(format_number((int) $row['properties_count'])) ?></td>
          <td class="text-end im-num"><?= e($row['sort_order']) ?></td>
          <td><?= cmsadmin_partial('state-badge', ['active' => (bool) $row['is_active']]) ?></td>
          <td class="text-end">
            <?= cmsadmin_partial('row-actions', ['label' => $row['name'], 'items' => [
                ['url' => $editUrl, 'label' => __('cmsadmin.edit'), 'icon' => 'mdi-pencil-outline'],
                ['post' => cmsadmin_url('equipements/' . $row['id'] . '/activation') . '?retour=' . rawurlencode($current), 'label' => __((int) $row['is_active'] === 1 ? 'cmsadmin.deactivate' : 'cmsadmin.activate'), 'icon' => (int) $row['is_active'] === 1 ? 'mdi-eye-off-outline' : 'mdi-eye-outline'],
                ['post' => cmsadmin_url('equipements/' . $row['id'] . '/supprimer') . '?retour=' . rawurlencode($current), 'label' => __('cmsadmin.delete'), 'icon' => 'mdi-trash-can-outline', 'danger' => true, 'confirm' => __('cmsadmin.confirm_delete', ['name' => $row['name']])],
            ]]) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</section>
