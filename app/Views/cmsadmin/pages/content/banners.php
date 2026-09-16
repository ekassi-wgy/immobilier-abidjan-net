<?php
/**
 * Bannières (lot 2.2). Celles de l'emplacement « home_hero » alimentent le diaporama de
 * l'accueil ; tant qu'aucune n'est active, l'accueil affiche les photos provisoires.
 *
 * @var list<array<string,mixed>> $rows
 * @var string                    $placement
 * @var list<string>              $placements
 * @var array                     $pagination
 */
$query = (string) app()->request()?->server('QUERY_STRING', '');
$current = '/cmsadmin/bannieres' . ($query !== '' ? '?' . $query : '');
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('content.banners.title'),
    'subtitle' => __('content.banners.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('content.banners.title')]],
    'actions' => [['label' => __('content.banners.create'), 'url' => 'bannieres/ajouter', 'icon' => 'mdi-plus', 'variant' => 'primary']],
]) ?>

<div class="card im-panel">
  <form class="im-filters-bar" method="get" action="<?= e(cmsadmin_url('bannieres')) ?>">
    <select class="form-select" name="emplacement" aria-label="<?= e(__('content.fields.placement')) ?>">
      <option value=""><?= e(__('content.banners.all_placements')) ?></option>
      <?php foreach ($placements as $key): ?>
      <option value="<?= e($key) ?>"<?= $placement === $key ? ' selected' : '' ?>><?= e(__('content.placements.' . $key)) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn im-btn-ghost" type="submit"><?= e(__('cmsadmin.filter')) ?></button>
  </form>

  <?php if ($rows === []): ?>
    <?= cmsadmin_partial('empty-state', ['icon' => 'mdi-image-multiple-outline', 'title' => __('content.banners.empty_title'),
        'text' => __('content.banners.empty_text'), 'action' => ['label' => __('content.banners.create'), 'url' => 'bannieres/ajouter']]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table">
      <thead>
        <tr>
          <th scope="col"><?= e(__('content.fields.image')) ?></th>
          <th scope="col"><?= e(__('content.fields.placement')) ?></th>
          <th scope="col"><?= e(__('content.fields.period')) ?></th>
          <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
          <th scope="col" class="text-end"><?= e(__('cmsadmin.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
          <td>
            <div class="im-property-cell">
              <span class="im-thumb" aria-hidden="true"><img src="<?= e(url((string) $row['image_path'])) ?>" alt="" width="48" height="36" loading="lazy"></span>
              <div>
                <a class="im-property-cell__title im-cell-link" href="<?= e(cmsadmin_url('bannieres/' . $row['id'] . '/modifier')) ?>"><?= e($row['title'] ?? $row['caption'] ?? __('content.banners.untitled')) ?></a>
                <?php if (!empty($row['caption'])): ?><span class="im-property-cell__meta"><?= e($row['caption']) ?></span><?php endif; ?>
              </div>
            </div>
          </td>
          <td><span class="im-tag"><?= e(__('content.placements.' . $row['placement'])) ?></span></td>
          <td><span class="im-cell-sub">
            <?= e($row['starts_at'] !== null ? substr((string) $row['starts_at'], 0, 10) : '—') ?> → <?= e($row['ends_at'] !== null ? substr((string) $row['ends_at'], 0, 10) : '—') ?>
          </span></td>
          <td><?= cmsadmin_partial('state-badge', ['label' => __((int) $row['is_active'] === 1 ? 'cmsadmin.active' : 'cmsadmin.inactive'), 'variant' => (int) $row['is_active'] === 1 ? 'published' : 'unpublished']) ?></td>
          <td class="text-end">
            <?= cmsadmin_partial('row-actions', ['label' => (string) ($row['title'] ?? $row['placement']), 'items' => [
                ['url' => cmsadmin_url('bannieres/' . $row['id'] . '/modifier'), 'label' => __('cmsadmin.edit'), 'icon' => 'mdi-pencil-outline'],
                ['post' => cmsadmin_url('bannieres/' . $row['id'] . '/supprimer') . '?retour=' . rawurlencode($current), 'label' => __('cmsadmin.delete'),
                 'icon' => 'mdi-trash-can-outline', 'danger' => true, 'confirm' => __('cmsadmin.confirm_delete', ['name' => $row['title'] ?? $row['placement']])],
            ]]) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= cmsadmin_partial('pagination', $pagination + ['path' => 'bannieres', 'query' => array_filter(['emplacement' => $placement])]) ?>
  <?php endif; ?>
</div>
