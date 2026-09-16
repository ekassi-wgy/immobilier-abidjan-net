<?php
/**
 * Actualités (lot 2.2).
 *
 * @var list<array<string,mixed>> $rows
 * @var array                     $filters
 * @var array                     $pagination
 */
$query = (string) app()->request()?->server('QUERY_STRING', '');
$current = '/cmsadmin/actualites' . ($query !== '' ? '?' . $query : '');
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('content.posts.title'),
    'subtitle' => __('content.posts.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('content.posts.title')]],
    'actions' => [['label' => __('content.posts.create'), 'url' => 'actualites/ajouter', 'icon' => 'mdi-plus', 'variant' => 'primary']],
]) ?>

<div class="card im-panel">
  <form class="im-filters-bar" method="get" action="<?= e(cmsadmin_url('actualites')) ?>">
    <div class="im-search-field">
      <span class="mdi mdi-magnify" aria-hidden="true"></span>
      <label class="visually-hidden" for="post-q"><?= e(__('content.posts.search')) ?></label>
      <input class="form-control" id="post-q" name="q" type="search" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('content.posts.search')) ?>">
    </div>
    <select class="form-select" name="statut" aria-label="<?= e(__('cmsadmin.state')) ?>">
      <option value=""><?= e(__('content.all_statuses')) ?></option>
      <?php foreach (['draft', 'published'] as $status): ?>
      <option value="<?= e($status) ?>"<?= $filters['statut'] === $status ? ' selected' : '' ?>><?= e(__('content.' . $status)) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn im-btn-ghost" type="submit"><?= e(__('cmsadmin.filter')) ?></button>
  </form>

  <?php if ($rows === []): ?>
    <?= cmsadmin_partial('empty-state', ['icon' => 'mdi-newspaper-variant-outline', 'title' => __('content.posts.empty_title'),
        'text' => __('content.posts.empty_text'), 'action' => ['label' => __('content.posts.create'), 'url' => 'actualites/ajouter']]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table">
      <thead>
        <tr>
          <th scope="col"><?= e(__('content.fields.title')) ?></th>
          <th scope="col"><?= e(__('content.fields.author')) ?></th>
          <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
          <th scope="col"><?= e(__('content.fields.published_at')) ?></th>
          <th scope="col" class="text-end"><?= e(__('cmsadmin.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
          <td>
            <div class="im-property-cell">
              <span class="im-thumb" aria-hidden="true">
                <?php if ($row['cover_image_path']): ?><img src="<?= e(url((string) $row['cover_image_path'])) ?>" alt="" width="48" height="36" loading="lazy"><?php else: ?><span class="mdi mdi-image-outline"></span><?php endif; ?>
              </span>
              <div>
                <a class="im-property-cell__title im-cell-link" href="<?= e(cmsadmin_url('actualites/' . $row['id'] . '/modifier')) ?>"><?= e($row['title']) ?></a>
                <span class="im-property-cell__meta">/actualites/<?= e($row['slug']) ?></span>
              </div>
            </div>
          </td>
          <td><span class="im-cell-sub"><?= e($row['author'] ?? '—') ?></span></td>
          <td><?= cmsadmin_partial('state-badge', ['label' => __('content.' . $row['status']), 'variant' => $row['status'] === 'published' ? 'published' : 'unpublished']) ?></td>
          <td><span class="im-cell-sub"><?= e($row['published_at'] !== null ? substr((string) $row['published_at'], 0, 10) : '—') ?></span></td>
          <td class="text-end">
            <?= cmsadmin_partial('row-actions', ['label' => (string) $row['title'], 'items' => array_values(array_filter([
                ['url' => cmsadmin_url('actualites/' . $row['id'] . '/modifier'), 'label' => __('cmsadmin.edit'), 'icon' => 'mdi-pencil-outline'],
                $row['status'] === 'published' ? ['url' => url('actualites/' . $row['slug']), 'label' => __('content.open'), 'icon' => 'mdi-open-in-new'] : null,
                ['post' => cmsadmin_url('actualites/' . $row['id'] . '/supprimer') . '?retour=' . rawurlencode($current), 'label' => __('cmsadmin.delete'),
                 'icon' => 'mdi-trash-can-outline', 'danger' => true, 'confirm' => __('cmsadmin.confirm_delete', ['name' => $row['title']])],
            ]))]) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= cmsadmin_partial('pagination', $pagination + ['path' => 'actualites', 'query' => array_filter($filters)]) ?>
  <?php endif; ?>
</div>
