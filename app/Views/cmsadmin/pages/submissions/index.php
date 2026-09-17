<?php
/**
 * Biens confiés par des particuliers : onglets par statut, recherche et pagination côté serveur.
 *
 * @var list<array<string,mixed>> $rows
 * @var array                     $filters ['statut','q']
 * @var array<string,int>         $counts
 * @var array                     $pagination
 */
$statusVariant = ['submitted' => 'pending', 'in_review' => 'published', 'published' => 'published', 'rejected' => 'rejected', 'withdrawn' => 'unpublished'];
$tabUrl = static fn (string $status): string => cmsadmin_url('biens-confies' . ($status !== '' ? '?statut=' . $status : ''));
$activeFilters = array_filter($filters);
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('submissions.title'),
    'subtitle' => __('submissions.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('submissions.title')]],
]) ?>

<section class="card im-panel im-panel--flush">
  <nav class="im-tabs" aria-label="<?= e(__('cmsadmin.state')) ?>">
    <a class="im-tabs__item<?= $filters['statut'] === '' ? ' is-current' : '' ?>" href="<?= e($tabUrl('')) ?>"><?= e(__('submissions.all')) ?> <span class="im-tabs__count"><?= e(format_number(array_sum($counts))) ?></span></a>
    <?php foreach ($counts as $status => $count): ?>
    <a class="im-tabs__item<?= $filters['statut'] === $status ? ' is-current' : '' ?>" href="<?= e($tabUrl($status)) ?>"<?= $filters['statut'] === $status ? ' aria-current="page"' : '' ?>><?= e(__('submissions.status.' . $status)) ?> <span class="im-tabs__count"><?= e(format_number($count)) ?></span></a>
    <?php endforeach; ?>
  </nav>

  <form class="im-filters" method="get" action="<?= e(cmsadmin_url('biens-confies')) ?>">
    <?php if ($filters['statut'] !== ''): ?><input type="hidden" name="statut" value="<?= e($filters['statut']) ?>"><?php endif; ?>
    <div class="im-filters__search">
      <span class="mdi mdi-magnify" aria-hidden="true"></span>
      <label class="visually-hidden" for="f-q"><?= e(__('cmsadmin.search')) ?></label>
      <input class="form-control" id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('submissions.search_placeholder')) ?>">
    </div>
    <div class="im-filters__actions">
      <button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.filter')) ?></button>
      <?php if ($filters['q'] !== ''): ?><a class="im-link-muted" href="<?= e($tabUrl($filters['statut'])) ?>"><?= e(__('cmsadmin.reset')) ?></a><?php endif; ?>
    </div>
  </form>

  <?php if ($rows === []): ?>
    <?= cmsadmin_partial('empty-state', [
        'icon' => 'mdi-key-outline',
        'title' => __('submissions.empty_title'),
        'text' => $activeFilters !== [] ? __('cmsadmin.empty_filtered') : __('submissions.empty_text'),
    ]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table im-table--list">
      <thead>
        <tr>
          <th scope="col"><?= e(__('submissions.owner')) ?></th>
          <th scope="col"><?= e(__('submissions.property')) ?></th>
          <th scope="col"><?= e(__('submissions.received')) ?></th>
          <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
          <th scope="col"><span class="visually-hidden"><?= e(__('cmsadmin.actions')) ?></span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): $url = cmsadmin_url('biens-confies/' . $row['id']); ?>
        <tr<?= in_array($row['status'], ['rejected', 'withdrawn'], true) ? ' class="is-muted"' : '' ?>>
          <td>
            <a class="im-cell-main im-cell-link" href="<?= e($url) ?>"><?= e($row['owner_name']) ?></a>
            <span class="im-cell-sub"><?= e(trim(($row['owner_phone'] ?? '') . ' · ' . $row['owner_email'], ' ·')) ?></span>
          </td>
          <td>
            <span class="im-cell-main"><?= e($row['category_name'] . ' · ' . $row['transaction_name']) ?></span>
            <span class="im-cell-sub"><?= e(trim(implode(' · ', array_filter([$row['commune_name'], $row['city_name'], $row['price'] !== null ? format_price($row['price']) : null])))) ?> · <?= e(__n('submissions.photos_count', (int) $row['photos_count'])) ?></span>
          </td>
          <td class="im-cell-sub-text"><?= e(substr((string) $row['created_at'], 0, 16)) ?></td>
          <td>
            <?= cmsadmin_partial('state-badge', ['label' => __('submissions.status.' . $row['status']), 'variant' => $statusVariant[$row['status']] ?? 'unpublished']) ?>
            <?php if ($row['property_reference'] !== null): ?><span class="im-cell-sub mt-1"><?= e($row['property_reference']) ?></span><?php endif; ?>
          </td>
          <td class="text-end"><a class="btn btn-sm im-btn-ghost" href="<?= e($url) ?>"><?= e(__('submissions.open')) ?></a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= cmsadmin_partial('pagination', $pagination + ['path' => 'biens-confies', 'query' => $activeFilters]) ?>
  <?php endif; ?>
</section>
