<?php
/**
 * Liste des agences partenaires du pays.
 *
 * @var list<array<string,mixed>> $rows
 * @var array                     $filters ['q', 'statut', 'verifiee', 'ville']
 * @var array<string,int>         $counts
 * @var array<int,string>         $cities
 * @var array                     $pagination
 */
$activeFilters = array_filter($filters);
$searchFilters = array_filter(array_diff_key($filters, ['statut' => true]));
$statusVariant = ['active' => 'published', 'suspended' => 'pending', 'closed' => 'unpublished'];
$tabUrl = static fn (string $status): string => cmsadmin_url('agences' . ($status !== '' ? '?statut=' . $status : ''));
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('agencies.title'),
    'subtitle' => __('agencies.subtitle', ['country' => site()?->country->localizedName(locale()) ?? '']),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('agencies.title')]],
    'actions' => [['label' => __('agencies.create'), 'url' => 'agences/ajouter', 'icon' => 'mdi-plus']],
]) ?>

<section class="card im-panel im-panel--flush">
  <nav class="im-tabs" aria-label="<?= e(__('cmsadmin.state')) ?>">
    <a class="im-tabs__item<?= $filters['statut'] === '' ? ' is-current' : '' ?>" href="<?= e($tabUrl('')) ?>"<?= $filters['statut'] === '' ? ' aria-current="page"' : '' ?>><?= e(__('agencies.all')) ?> <span class="im-tabs__count"><?= e(format_number(array_sum($counts))) ?></span></a>
    <?php foreach ($counts as $status => $count): ?>
    <a class="im-tabs__item<?= $filters['statut'] === $status ? ' is-current' : '' ?>" href="<?= e($tabUrl($status)) ?>"<?= $filters['statut'] === $status ? ' aria-current="page"' : '' ?>><?= e(__('agencies.status_plural.' . $status)) ?> <span class="im-tabs__count"><?= e(format_number($count)) ?></span></a>
    <?php endforeach; ?>
  </nav>

  <form class="im-filters" method="get" action="<?= e(cmsadmin_url('agences')) ?>">
    <?php if ($filters['statut'] !== ''): ?><input type="hidden" name="statut" value="<?= e($filters['statut']) ?>"><?php endif; ?>
    <div class="im-filters__search">
      <span class="mdi mdi-magnify" aria-hidden="true"></span>
      <label class="visually-hidden" for="f-q"><?= e(__('cmsadmin.search')) ?></label>
      <input class="form-control" id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('agencies.search_placeholder')) ?>">
    </div>
    <div class="im-filters__field">
      <label class="visually-hidden" for="f-ville"><?= e(__('geo.city.singular')) ?></label>
      <select class="form-select" id="f-ville" name="ville" data-im-select data-placeholder="<?= e(__('geo.all_cities')) ?>">
        <option value=""></option>
        <?php foreach ($cities as $id => $name): ?>
        <option value="<?= e($id) ?>"<?= $filters['ville'] === $id ? ' selected' : '' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="im-filters__field im-filters__field--narrow">
      <label class="visually-hidden" for="f-verifiee"><?= e(__('agencies.verified')) ?></label>
      <select class="form-select" id="f-verifiee" name="verifiee">
        <option value=""><?= e(__('agencies.verified_any')) ?></option>
        <option value="oui"<?= $filters['verifiee'] === 'oui' ? ' selected' : '' ?>><?= e(__('agencies.verified_yes')) ?></option>
        <option value="non"<?= $filters['verifiee'] === 'non' ? ' selected' : '' ?>><?= e(__('agencies.verified_no')) ?></option>
      </select>
    </div>
    <div class="im-filters__actions">
      <button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.filter')) ?></button>
      <?php if ($searchFilters !== []): ?><a class="im-link-muted" href="<?= e($tabUrl($filters['statut'])) ?>"><?= e(__('cmsadmin.reset')) ?></a><?php endif; ?>
    </div>
  </form>

  <?php if ($rows === []): ?>
    <?= cmsadmin_partial('empty-state', [
        'icon' => 'mdi-office-building-outline',
        'title' => __('agencies.empty_title'),
        'text' => $activeFilters !== [] ? __('cmsadmin.empty_filtered') : __('agencies.empty_text'),
        'action' => $activeFilters !== [] ? null : ['label' => __('agencies.create'), 'url' => 'agences/ajouter'],
    ]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table im-table--list">
      <thead>
        <tr>
          <th scope="col"><?= e(__('agencies.singular')) ?></th>
          <th scope="col"><?= e(__('agencies.location')) ?></th>
          <th scope="col"><?= e(__('agencies.contact')) ?></th>
          <th scope="col" class="text-end"><?= e(__('agencies.accounts')) ?></th>
          <th scope="col" class="text-end"><?= e(__('geo.usage.properties_label')) ?></th>
          <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): $editUrl = cmsadmin_url('agences/' . $row['id'] . '/modifier'); ?>
        <tr<?= $row['status'] !== 'active' ? ' class="is-muted"' : '' ?>>
          <td>
            <div class="im-agency-cell">
              <span class="im-logo-thumb" aria-hidden="true">
                <?php if ($row['logo_path']): ?><img src="<?= e(url($row['logo_path'])) ?>" alt="" width="40" height="40" loading="lazy"><?php else: ?><?= e(mb_strtoupper(mb_substr((string) $row['name'], 0, 1))) ?><?php endif; ?>
              </span>
              <div>
                <a class="im-cell-main im-cell-link" href="<?= e($editUrl) ?>"><?= e($row['name']) ?></a>
                <span class="im-cell-sub">
                  <?php if ((int) $row['is_verified'] === 1): ?><span class="im-tag"><span class="mdi mdi-check-decagram" aria-hidden="true"></span> <?= e(__('agencies.verified_badge')) ?></span><?php endif; ?>
                  <?php if ((int) $row['is_featured'] === 1): ?><span class="im-tag im-tag--muted"><?= e(__('agencies.featured_badge')) ?></span><?php endif; ?>
                </span>
              </div>
            </div>
          </td>
          <td><span class="im-cell-main"><?= e($row['commune_name'] ?? '—') ?></span><span class="im-cell-sub"><?= e($row['city_name'] ?? '') ?></span></td>
          <td><span class="im-cell-main"><?= e($row['phone'] ?? '—') ?></span><span class="im-cell-sub"><?= e($row['email'] ?? '') ?></span></td>
          <td class="text-end im-num"><?= e($row['users_count']) ?></td>
          <td class="text-end im-num"><?= e(format_number((int) $row['published_properties_count'])) ?></td>
          <td><?= cmsadmin_partial('state-badge', ['label' => __('agencies.status.' . $row['status']), 'variant' => $statusVariant[$row['status']] ?? 'unpublished']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= cmsadmin_partial('pagination', $pagination + ['path' => 'agences', 'query' => $activeFilters]) ?>
  <?php endif; ?>
</section>
