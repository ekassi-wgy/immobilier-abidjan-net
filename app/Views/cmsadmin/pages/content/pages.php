<?php
/**
 * Pages éditoriales et légales du site (lot 2.2).
 * Une page système (avec un code) ne se supprime pas : elle se dépublie.
 *
 * @var list<array<string,mixed>> $rows
 * @var array                     $pagination
 */
$query = (string) app()->request()?->server('QUERY_STRING', '');
$current = '/cmsadmin/pages' . ($query !== '' ? '?' . $query : '');
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('content.pages.title'),
    'subtitle' => __('content.pages.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('content.pages.title')]],
    'actions' => [['label' => __('content.pages.create'), 'url' => 'pages/ajouter', 'icon' => 'mdi-plus', 'variant' => 'primary']],
]) ?>

<div class="card im-panel">
  <?php if ($rows === []): ?>
    <?= cmsadmin_partial('empty-state', ['icon' => 'mdi-text-box-outline', 'title' => __('content.pages.empty_title'), 'text' => __('content.pages.empty_text')]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table">
      <thead>
        <tr>
          <th scope="col"><?= e(__('content.fields.title')) ?></th>
          <th scope="col"><?= e(__('content.fields.slug')) ?></th>
          <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
          <th scope="col"><?= e(__('content.fields.updated')) ?></th>
          <th scope="col" class="text-end"><?= e(__('cmsadmin.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): $system = $row['code'] !== null; ?>
        <tr>
          <td>
            <a class="im-cell-link" href="<?= e(cmsadmin_url('pages/' . $row['id'] . '/modifier')) ?>"><?= e($row['title']) ?></a>
            <?php if ($system): ?><span class="im-tag"><?= e(__('content.pages.system')) ?></span><?php endif; ?>
          </td>
          <td><span class="im-cell-sub">/<?= e($row['slug']) ?></span></td>
          <td><?= cmsadmin_partial('state-badge', ['label' => __((int) $row['is_published'] === 1 ? 'content.published' : 'content.draft'), 'variant' => (int) $row['is_published'] === 1 ? 'published' : 'unpublished']) ?></td>
          <td><span class="im-cell-sub"><?= e($row['updated_at'] !== null ? substr((string) $row['updated_at'], 0, 10) : '—') ?></span></td>
          <td class="text-end">
            <?= cmsadmin_partial('row-actions', ['label' => (string) $row['title'], 'items' => array_values(array_filter([
                ['url' => cmsadmin_url('pages/' . $row['id'] . '/modifier'), 'label' => __('cmsadmin.edit'), 'icon' => 'mdi-pencil-outline'],
                (int) $row['is_published'] === 1 ? ['url' => url((string) $row['slug']), 'label' => __('content.open'), 'icon' => 'mdi-open-in-new'] : null,
                $system ? null : ['post' => cmsadmin_url('pages/' . $row['id'] . '/supprimer') . '?retour=' . rawurlencode($current), 'label' => __('cmsadmin.delete'),
                    'icon' => 'mdi-trash-can-outline', 'danger' => true, 'confirm' => __('cmsadmin.confirm_delete', ['name' => $row['title']])],
            ]))]) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= cmsadmin_partial('pagination', $pagination + ['path' => 'pages', 'query' => []]) ?>
  <?php endif; ?>
</div>
