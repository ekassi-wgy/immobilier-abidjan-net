<?php
/**
 * Balises méta par URL (lot 2.1). Seules les pages réellement surchargées ont une ligne :
 * la liste est donc courte, et vide au départ.
 *
 * @var list<array<string,mixed>> $rows
 * @var string                    $search
 * @var array                     $pagination
 */
$query = (string) app()->request()?->server('QUERY_STRING', '');
$current = '/cmsadmin/seo' . ($query !== '' ? '?' . $query : '');
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('seo.title'),
    'subtitle' => __('seo.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('seo.title')]],
    'actions' => [['label' => __('seo.create'), 'url' => 'seo/ajouter', 'icon' => 'mdi-plus', 'variant' => 'primary']],
]) ?>

<div class="card im-panel">
  <form class="im-filters-bar" method="get" action="<?= e(cmsadmin_url('seo')) ?>">
    <div class="im-search-field">
      <span class="mdi mdi-magnify" aria-hidden="true"></span>
      <label class="visually-hidden" for="seo-q"><?= e(__('seo.search')) ?></label>
      <input class="form-control" id="seo-q" name="q" type="search" value="<?= e($search) ?>" placeholder="<?= e(__('seo.search')) ?>">
    </div>
    <button class="btn im-btn-ghost" type="submit"><?= e(__('cmsadmin.filter')) ?></button>
  </form>

  <?php if ($rows === []): ?>
    <?= cmsadmin_partial('empty-state', [
        'icon' => 'mdi-tag-text-outline',
        'title' => __('seo.empty_title'),
        'text' => __('seo.empty_text'),
        'action' => ['label' => __('seo.create'), 'url' => 'seo/ajouter'],
    ]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table">
      <thead>
        <tr>
          <th scope="col"><?= e(__('seo.fields.path')) ?></th>
          <th scope="col"><?= e(__('seo.fields.meta_title')) ?></th>
          <th scope="col"><?= e(__('seo.fields.noindex')) ?></th>
          <th scope="col" class="text-end"><?= e(__('cmsadmin.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
          <td>
            <a class="im-cell-link" href="<?= e(cmsadmin_url('seo/' . $row['id'] . '/modifier')) ?>"><?= e($row['path']) ?></a>
            <?php if (!empty($row['intro_text'])): ?><span class="im-tag"><?= e(__('seo.has_intro')) ?></span><?php endif; ?>
          </td>
          <td><span class="im-cell-sub"><?= e($row['meta_title'] ?? '—') ?></span></td>
          <td>
            <?php if ((int) $row['noindex'] === 1): ?>
            <?= cmsadmin_partial('state-badge', ['label' => __('seo.noindex_on'), 'variant' => 'unpublished']) ?>
            <?php else: ?>
            <span class="im-cell-sub">—</span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <?= cmsadmin_partial('row-actions', [
                'label' => (string) $row['path'],
                'items' => [
                    ['url' => cmsadmin_url('seo/' . $row['id'] . '/modifier'), 'label' => __('cmsadmin.edit'), 'icon' => 'mdi-pencil-outline'],
                    ['url' => url(ltrim((string) $row['path'], '/')), 'label' => __('seo.open_page'), 'icon' => 'mdi-open-in-new'],
                    ['post' => cmsadmin_url('seo/' . $row['id'] . '/supprimer') . '?retour=' . rawurlencode($current), 'label' => __('cmsadmin.delete'),
                     'icon' => 'mdi-trash-can-outline', 'danger' => true, 'confirm' => __('cmsadmin.confirm_delete', ['name' => $row['path']])],
                ],
            ]) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= cmsadmin_partial('pagination', $pagination + ['path' => 'seo', 'query' => array_filter(['q' => $search])]) ?>
  <?php endif; ?>
</div>
