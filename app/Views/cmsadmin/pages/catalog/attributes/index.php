<?php
/**
 * Liste des critères dynamiques.
 *
 * @var list<array<string,mixed>> $rows
 * @var array                     $filters ['q', 'groupe', 'etat']
 * @var array<int,string>         $groups
 * @var array                     $pagination
 */
$query = (string) app()->request()?->server('QUERY_STRING', '');
$current = '/cmsadmin/criteres' . ($query !== '' ? '?' . $query : '');
$activeFilters = array_filter($filters);
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('catalog.attributes.title'),
    'subtitle' => __('catalog.attributes.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('catalog.title')], ['label' => __('catalog.attributes.title')]],
    'actions' => [['label' => __('catalog.attributes.create'), 'url' => 'criteres/ajouter', 'icon' => 'mdi-plus']],
]) ?>

<section class="card im-panel im-panel--flush">
  <form class="im-filters" method="get" action="<?= e(cmsadmin_url('criteres')) ?>">
    <div class="im-filters__search">
      <span class="mdi mdi-magnify" aria-hidden="true"></span>
      <label class="visually-hidden" for="f-q"><?= e(__('cmsadmin.search')) ?></label>
      <input class="form-control" id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('catalog.attributes.search_placeholder')) ?>">
    </div>
    <div class="im-filters__field">
      <label class="visually-hidden" for="f-groupe"><?= e(__('catalog.attributes.group')) ?></label>
      <select class="form-select" id="f-groupe" name="groupe">
        <option value=""><?= e(__('catalog.attributes.all_groups')) ?></option>
        <?php foreach ($groups as $id => $name): ?>
        <option value="<?= e($id) ?>"<?= $filters['groupe'] === $id ? ' selected' : '' ?>><?= e($name) ?></option>
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
      <?php if ($activeFilters !== []): ?><a class="im-link-muted" href="<?= e(cmsadmin_url('criteres')) ?>"><?= e(__('cmsadmin.reset')) ?></a><?php endif; ?>
    </div>
  </form>

  <?php if ($rows === []): ?>
    <?= cmsadmin_partial('empty-state', ['icon' => 'mdi-tune-variant', 'title' => __('catalog.attributes.empty'), 'text' => $activeFilters !== [] ? __('cmsadmin.empty_filtered') : __('catalog.attributes.subtitle'), 'action' => $activeFilters !== [] ? null : ['label' => __('catalog.attributes.create'), 'url' => 'criteres/ajouter']]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table im-table--list">
      <thead>
        <tr>
          <th scope="col"><?= e(__('catalog.attributes.singular')) ?></th>
          <th scope="col"><?= e(__('catalog.attributes.group')) ?></th>
          <th scope="col"><?= e(__('catalog.attributes.input_type')) ?></th>
          <th scope="col"><?= e(__('catalog.attributes.display')) ?></th>
          <th scope="col" class="text-end"><?= e(__('catalog.categories.title')) ?></th>
          <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
          <th scope="col"><span class="visually-hidden"><?= e(__('cmsadmin.actions')) ?></span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): $editUrl = cmsadmin_url('criteres/' . $row['id'] . '/modifier'); ?>
        <tr<?= (int) $row['is_active'] === 0 ? ' class="is-muted"' : '' ?>>
          <td>
            <a class="im-cell-main im-cell-link" href="<?= e($editUrl) ?>"><?= e($row['name']) ?></a>
            <span class="im-cell-sub"><code><?= e($row['code']) ?></code><?php if ($row['storage'] === 'column'): ?> <span class="im-tag" title="<?= e(__('catalog.attributes.storage_column_hint')) ?>"><?= e(__('catalog.attributes.indexed')) ?></span><?php endif; ?></span>
          </td>
          <td><?= e($row['group_name']) ?></td>
          <td>
            <span class="im-cell-main"><?= e(__('catalog.attributes.types.' . $row['input_type'])) ?></span>
            <span class="im-cell-sub"><?= in_array($row['input_type'], ['select', 'multiselect'], true) ? e(__('catalog.attributes.options_count', ['count' => (int) $row['options_count']])) : e($row['unit'] ?? '') ?></span>
          </td>
          <td class="im-cell-sub-text">
            <?= e(implode(' · ', array_filter([(int) $row['is_filterable'] === 1 ? __('catalog.attributes.filterable') : null, (int) $row['is_public'] === 1 ? __('catalog.attributes.public') : __('catalog.attributes.private')]))) ?>
          </td>
          <td class="text-end im-num"><?= e($row['categories_count']) ?></td>
          <td><?= cmsadmin_partial('state-badge', ['active' => (bool) $row['is_active']]) ?></td>
          <td class="text-end">
            <?= cmsadmin_partial('row-actions', ['label' => $row['name'], 'items' => [
                ['url' => $editUrl, 'label' => __('cmsadmin.edit'), 'icon' => 'mdi-pencil-outline'],
                ['post' => cmsadmin_url('criteres/' . $row['id'] . '/activation') . '?retour=' . rawurlencode($current), 'label' => __((int) $row['is_active'] === 1 ? 'cmsadmin.deactivate' : 'cmsadmin.activate'), 'icon' => (int) $row['is_active'] === 1 ? 'mdi-eye-off-outline' : 'mdi-eye-outline'],
                ['post' => cmsadmin_url('criteres/' . $row['id'] . '/supprimer') . '?retour=' . rawurlencode($current), 'label' => __('cmsadmin.delete'), 'icon' => 'mdi-trash-can-outline', 'danger' => true, 'confirm' => __('cmsadmin.confirm_delete', ['name' => $row['name']])],
            ]]) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= cmsadmin_partial('pagination', $pagination + ['path' => 'criteres', 'query' => $activeFilters]) ?>
  <?php endif; ?>
</section>
