<?php
/**
 * Redirections du site courant (lot 2.1). Triées par nombre d'utilisations : celles qui ne
 * servent jamais peuvent être retirées après quelques mois.
 *
 * @var list<array<string,mixed>> $rows
 * @var string                    $search
 * @var array                     $pagination
 * @var list<int>                 $codes
 */
$query = (string) app()->request()?->server('QUERY_STRING', '');
$current = '/cmsadmin/seo/redirections' . ($query !== '' ? '?' . $query : '');
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('seo.redirects.title'),
    'subtitle' => __('seo.redirects.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('seo.title')], ['label' => __('seo.redirects.title')]],
    'actions' => [['label' => __('seo.redirects.create'), 'url' => 'seo/redirections/ajouter', 'icon' => 'mdi-plus', 'variant' => 'primary']],
]) ?>

<div class="card im-panel">
  <form class="im-filters-bar" method="get" action="<?= e(cmsadmin_url('seo/redirections')) ?>">
    <div class="im-search-field">
      <span class="mdi mdi-magnify" aria-hidden="true"></span>
      <label class="visually-hidden" for="redirect-q"><?= e(__('seo.redirects.search')) ?></label>
      <input class="form-control" id="redirect-q" name="q" type="search" value="<?= e($search) ?>" placeholder="<?= e(__('seo.redirects.search')) ?>">
    </div>
    <button class="btn im-btn-ghost" type="submit"><?= e(__('cmsadmin.filter')) ?></button>
  </form>

  <?php if ($rows === []): ?>
    <?= cmsadmin_partial('empty-state', [
        'icon' => 'mdi-arrow-decision-outline',
        'title' => __('seo.redirects.empty_title'),
        'text' => __('seo.redirects.empty_text'),
        'action' => ['label' => __('seo.redirects.create'), 'url' => 'seo/redirections/ajouter'],
    ]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table">
      <thead>
        <tr>
          <th scope="col"><?= e(__('seo.redirects.fields.source')) ?></th>
          <th scope="col"><?= e(__('seo.redirects.fields.target')) ?></th>
          <th scope="col"><?= e(__('seo.redirects.fields.code')) ?></th>
          <th scope="col" class="text-end"><?= e(__('seo.redirects.hits')) ?></th>
          <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
          <th scope="col" class="text-end"><?= e(__('cmsadmin.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
          <td><a class="im-cell-link" href="<?= e(cmsadmin_url('seo/redirections/' . $row['id'] . '/modifier')) ?>"><?= e($row['source_path']) ?></a></td>
          <td><span class="im-cell-sub"><?= (int) $row['http_code'] === 410 ? '—' : e($row['target_path']) ?></span></td>
          <td><span class="im-tag"><?= e($row['http_code']) ?></span></td>
          <td class="text-end im-num">
            <?= e(format_number((int) $row['hits'])) ?>
            <?php if ($row['last_hit_at'] !== null): ?><span class="im-cell-sub"><?= e(substr((string) $row['last_hit_at'], 0, 10)) ?></span><?php endif; ?>
          </td>
          <td><?= cmsadmin_partial('state-badge', ['label' => __((int) $row['is_active'] === 1 ? 'cmsadmin.active' : 'cmsadmin.inactive'), 'variant' => (int) $row['is_active'] === 1 ? 'published' : 'unpublished']) ?></td>
          <td class="text-end">
            <?= cmsadmin_partial('row-actions', [
                'label' => (string) $row['source_path'],
                'items' => [
                    ['url' => cmsadmin_url('seo/redirections/' . $row['id'] . '/modifier'), 'label' => __('cmsadmin.edit'), 'icon' => 'mdi-pencil-outline'],
                    ['post' => cmsadmin_url('seo/redirections/' . $row['id'] . '/supprimer') . '?retour=' . rawurlencode($current), 'label' => __('cmsadmin.delete'),
                     'icon' => 'mdi-trash-can-outline', 'danger' => true, 'confirm' => __('cmsadmin.confirm_delete', ['name' => $row['source_path']])],
                ],
            ]) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= cmsadmin_partial('pagination', $pagination + ['path' => 'seo/redirections', 'query' => array_filter(['q' => $search])]) ?>
  <?php endif; ?>
</div>
