<?php
/**
 * Demandes « Devenir partenaire ».
 *
 * @var list<array<string,mixed>> $rows
 * @var array                     $filters ['q', 'statut']
 * @var array<string,int>         $counts
 * @var array                     $pagination
 */
$variant = ['new' => 'archived', 'contacted' => 'pending', 'approved' => 'published', 'rejected' => 'unpublished'];
$tabUrl = static fn (string $status): string => cmsadmin_url('demandes-partenariat' . ($status !== '' ? '?statut=' . $status : ''));
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('partners.title'),
    'subtitle' => __('partners.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('agencies.title'), 'url' => 'agences'], ['label' => __('partners.title')]],
]) ?>

<section class="card im-panel im-panel--flush">
  <nav class="im-tabs" aria-label="<?= e(__('cmsadmin.state')) ?>">
    <a class="im-tabs__item<?= $filters['statut'] === '' ? ' is-current' : '' ?>" href="<?= e($tabUrl('')) ?>"><?= e(__('agencies.all')) ?> <span class="im-tabs__count"><?= e(format_number(array_sum($counts))) ?></span></a>
    <?php foreach ($counts as $status => $count): ?>
    <a class="im-tabs__item<?= $filters['statut'] === $status ? ' is-current' : '' ?>" href="<?= e($tabUrl($status)) ?>"<?= $filters['statut'] === $status ? ' aria-current="page"' : '' ?>><?= e(__('partners.status_plural.' . $status)) ?> <span class="im-tabs__count"><?= e(format_number($count)) ?></span></a>
    <?php endforeach; ?>
  </nav>

  <form class="im-filters" method="get" action="<?= e(cmsadmin_url('demandes-partenariat')) ?>">
    <?php if ($filters['statut'] !== ''): ?><input type="hidden" name="statut" value="<?= e($filters['statut']) ?>"><?php endif; ?>
    <div class="im-filters__search">
      <span class="mdi mdi-magnify" aria-hidden="true"></span>
      <label class="visually-hidden" for="f-q"><?= e(__('cmsadmin.search')) ?></label>
      <input class="form-control" id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('partners.search_placeholder')) ?>">
    </div>
    <div class="im-filters__actions">
      <button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.filter')) ?></button>
      <?php if ($filters['q'] !== ''): ?><a class="im-link-muted" href="<?= e($tabUrl($filters['statut'])) ?>"><?= e(__('cmsadmin.reset')) ?></a><?php endif; ?>
    </div>
  </form>

  <?php if ($rows === []): ?>
    <?= cmsadmin_partial('empty-state', ['icon' => 'mdi-handshake-outline', 'title' => __('partners.empty_title'), 'text' => __('partners.empty_text')]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table im-table--list">
      <thead>
        <tr>
          <th scope="col"><?= e(__('agencies.singular')) ?></th>
          <th scope="col"><?= e(__('partners.contact')) ?></th>
          <th scope="col"><?= e(__('agencies.location')) ?></th>
          <th scope="col"><?= e(__('partners.received')) ?></th>
          <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): $url = cmsadmin_url('demandes-partenariat/' . $row['id']); ?>
        <tr<?= $row['status'] === 'new' ? ' class="is-unread"' : '' ?>>
          <td>
            <a class="im-cell-main im-cell-link" href="<?= e($url) ?>"><?= e($row['agency_name']) ?></a>
            <span class="im-cell-sub"><?= $row['listings_estimate'] !== null ? e(__('partners.listings_estimate', ['count' => (int) $row['listings_estimate']])) : '' ?></span>
          </td>
          <td><span class="im-cell-main"><?= e($row['contact_name']) ?></span><span class="im-cell-sub"><?= e($row['phone']) ?> · <?= e($row['email']) ?></span></td>
          <td><span class="im-cell-main"><?= e($row['commune_name'] ?? '—') ?></span><span class="im-cell-sub"><?= e($row['city_name'] ?? '') ?></span></td>
          <td class="im-cell-sub-text"><?= e(substr((string) $row['created_at'], 0, 10)) ?></td>
          <td><?= cmsadmin_partial('state-badge', ['label' => __('partners.status.' . $row['status']), 'variant' => $variant[$row['status']]]) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= cmsadmin_partial('pagination', $pagination + ['path' => 'demandes-partenariat', 'query' => array_filter($filters)]) ?>
  <?php endif; ?>
</section>
