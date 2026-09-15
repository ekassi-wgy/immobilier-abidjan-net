<?php
/**
 * Arborescence des catégories de biens.
 *
 * @var list<array<string, mixed>> $tree Racines avec 'children'
 */
$actions = static function (array $category, bool $isRoot): string {
    $id = (int) $category['id'];

    return cmsadmin_partial('row-actions', ['label' => $category['name'], 'items' => array_values(array_filter([
        ['url' => cmsadmin_url('categories/' . $id . '/modifier'), 'label' => __('cmsadmin.edit'), 'icon' => 'mdi-pencil-outline'],
        $isRoot ? ['url' => cmsadmin_url('categories/ajouter?parent=' . $id), 'label' => __('catalog.categories.add_child'), 'icon' => 'mdi-file-tree-outline'] : null,
        ['post' => cmsadmin_url('categories/' . $id . '/activation'), 'label' => __((int) $category['is_active'] === 1 ? 'cmsadmin.deactivate' : 'cmsadmin.activate'), 'icon' => (int) $category['is_active'] === 1 ? 'mdi-eye-off-outline' : 'mdi-eye-outline'],
        ['post' => cmsadmin_url('categories/' . $id . '/supprimer'), 'label' => __('cmsadmin.delete'), 'icon' => 'mdi-trash-can-outline', 'danger' => true, 'confirm' => __('cmsadmin.confirm_delete', ['name' => $category['name']])],
    ]))]);
};
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('catalog.categories.title'),
    'subtitle' => __('catalog.categories.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('catalog.title')], ['label' => __('catalog.categories.title')]],
    'actions' => [['label' => __('catalog.categories.create'), 'url' => 'categories/ajouter', 'icon' => 'mdi-plus']],
]) ?>

<section class="card im-panel im-panel--flush">
  <?php if ($tree === []): ?>
    <?= cmsadmin_partial('empty-state', ['icon' => 'mdi-shape-outline', 'title' => __('catalog.categories.empty'), 'text' => __('catalog.categories.subtitle'), 'action' => ['label' => __('catalog.categories.create'), 'url' => 'categories/ajouter']]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table im-table--list im-tree">
      <thead>
        <tr>
          <th scope="col"><?= e(__('catalog.categories.singular')) ?></th>
          <th scope="col"><?= e(__('catalog.categories.transactions')) ?></th>
          <th scope="col" class="text-end"><?= e(__('catalog.attributes.title')) ?></th>
          <th scope="col" class="text-end"><?= e(__('geo.usage.properties_label')) ?></th>
          <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
          <th scope="col"><span class="visually-hidden"><?= e(__('cmsadmin.actions')) ?></span></th>
        </tr>
      </thead>
      <?php foreach ($tree as $root): ?>
      <tbody class="im-tree__group">
        <tr class="im-tree__root<?= (int) $root['is_active'] === 0 ? ' is-muted' : '' ?>">
          <td>
            <a class="im-cell-main im-cell-link" href="<?= e(cmsadmin_url('categories/' . $root['id'] . '/modifier')) ?>"><?= e($root['name']) ?></a>
            <span class="im-cell-sub">/<?= e($root['slug']) ?> · <?= e(__('catalog.categories.children_count', ['count' => count($root['children'])])) ?><?= $root['country_name'] !== null ? ' · ' . e($root['country_name']) : '' ?></span>
          </td>
          <td class="im-cell-sub-text"><?= e($root['transactions'] ?? '—') ?></td>
          <td class="text-end im-num"><?= e($root['attributes_count']) ?></td>
          <td class="text-end im-num"><?= e(format_number((int) $root['properties_count'])) ?></td>
          <td><?= cmsadmin_partial('state-badge', ['active' => (bool) $root['is_active']]) ?></td>
          <td class="text-end"><?= $actions($root, true) ?></td>
        </tr>
        <?php foreach ($root['children'] as $child): ?>
        <tr class="im-tree__child<?= (int) $child['is_active'] === 0 || (int) $root['is_active'] === 0 ? ' is-muted' : '' ?>">
          <td>
            <span class="im-tree__branch" aria-hidden="true"></span>
            <a class="im-cell-link" href="<?= e(cmsadmin_url('categories/' . $child['id'] . '/modifier')) ?>"><?= e($child['name']) ?></a>
            <span class="im-cell-sub">/<?= e($child['slug']) ?><?= $child['country_name'] !== null ? ' · ' . e($child['country_name']) : '' ?></span>
          </td>
          <td class="im-cell-sub-text"><?= e($child['transactions'] ?? __('catalog.categories.same_as_family')) ?></td>
          <td class="text-end im-num"><?= (int) $child['attributes_count'] > 0 ? '+' . e($child['attributes_count']) : '—' ?></td>
          <td class="text-end im-num"><?= e(format_number((int) $child['properties_count'])) ?></td>
          <td><?= cmsadmin_partial('state-badge', ['active' => (bool) $child['is_active']]) ?></td>
          <td class="text-end"><?= $actions($child, false) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</section>
