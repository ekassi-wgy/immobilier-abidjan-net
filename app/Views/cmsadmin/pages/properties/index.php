<?php
/**
 * Liste des annonces : onglets par statut, filtres et pagination côté serveur.
 * Une agence ne voit que ses annonces (filtrage côté contrôleur).
 *
 * @var list<array<string,mixed>> $rows
 * @var array                     $filters  ['q','statut','categorie','commune','agence','une']
 * @var array<string,int>         $counts   Par statut + 'revision'
 * @var array<int,string>         $categories
 * @var array<int,string>         $communes
 * @var array<int,string>         $agencies
 * @var array                     $pagination
 * @var bool                      $isStaff
 */
$query = (string) app()->request()?->server('QUERY_STRING', '');
$current = '/cmsadmin/annonces' . ($query !== '' ? '?' . $query : '');
$activeFilters = array_filter($filters);
$searchFilters = array_filter(array_diff_key($filters, ['statut' => true]));
$tabs = ['' => __('properties.tabs.all'), 'pending' => __('properties.status.pending'), 'revision' => __('properties.tabs.revision'), 'published' => __('properties.status.published'), 'rejected' => __('properties.status.rejected'), 'unpublished' => __('properties.status.unpublished'), 'expired' => __('properties.status.expired'), 'archived' => __('properties.status.archived')];
$tabUrl = static fn (string $status): string => cmsadmin_url('annonces' . ($status !== '' ? '?statut=' . $status : ''));
?>
<?= cmsadmin_partial('page-header', [
    'title' => __($isStaff ? 'properties.title' : 'properties.title_agency'),
    'subtitle' => __($isStaff ? 'properties.subtitle' : 'properties.subtitle_agency'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('properties.title')]],
    'actions' => [['label' => __('properties.create'), 'url' => 'annonces/nouvelle', 'icon' => 'mdi-plus']],
]) ?>

<section class="card im-panel im-panel--flush">
  <nav class="im-tabs" aria-label="<?= e(__('cmsadmin.state')) ?>">
    <?php foreach ($tabs as $status => $label): $count = $status === '' ? array_sum(array_diff_key($counts, ['revision' => true])) : ($counts[$status] ?? 0); ?>
    <a class="im-tabs__item<?= $filters['statut'] === $status ? ' is-current' : '' ?>" href="<?= e($tabUrl($status)) ?>"<?= $filters['statut'] === $status ? ' aria-current="page"' : '' ?>>
      <?= e($label) ?> <span class="im-tabs__count"><?= e(format_number($count)) ?></span>
    </a>
    <?php endforeach; ?>
  </nav>

  <form class="im-filters" method="get" action="<?= e(cmsadmin_url('annonces')) ?>">
    <?php if ($filters['statut'] !== ''): ?><input type="hidden" name="statut" value="<?= e($filters['statut']) ?>"><?php endif; ?>
    <div class="im-filters__search">
      <span class="mdi mdi-magnify" aria-hidden="true"></span>
      <label class="visually-hidden" for="f-q"><?= e(__('cmsadmin.search')) ?></label>
      <input class="form-control" id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('properties.search_placeholder')) ?>">
    </div>
    <div class="im-filters__field">
      <label class="visually-hidden" for="f-categorie"><?= e(__('properties.fields.category')) ?></label>
      <select class="form-select" id="f-categorie" name="categorie" data-im-select data-placeholder="<?= e(__('properties.all_categories')) ?>">
        <option value=""></option>
        <?php foreach ($categories as $id => $name): ?><option value="<?= e($id) ?>"<?= $filters['categorie'] === $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="im-filters__field">
      <label class="visually-hidden" for="f-commune"><?= e(__('geo.commune.singular')) ?></label>
      <select class="form-select" id="f-commune" name="commune" data-im-select data-placeholder="<?= e(__('geo.all_communes')) ?>">
        <option value=""></option>
        <?php foreach ($communes as $id => $name): ?><option value="<?= e($id) ?>"<?= $filters['commune'] === $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php if ($isStaff): ?>
    <div class="im-filters__field">
      <label class="visually-hidden" for="f-agence"><?= e(__('agencies.singular')) ?></label>
      <select class="form-select" id="f-agence" name="agence" data-im-select data-placeholder="<?= e(__('properties.all_agencies')) ?>">
        <option value=""></option>
        <?php foreach ($agencies as $id => $name): ?><option value="<?= e($id) ?>"<?= $filters['agence'] === $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="im-filters__actions">
      <button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.filter')) ?></button>
      <?php if ($searchFilters !== []): ?><a class="im-link-muted" href="<?= e($tabUrl($filters['statut'])) ?>"><?= e(__('cmsadmin.reset')) ?></a><?php endif; ?>
    </div>
  </form>

  <?php if ($rows === []): ?>
    <?= cmsadmin_partial('empty-state', [
        'icon' => 'mdi-home-search-outline',
        'title' => __('properties.empty_title'),
        'text' => $activeFilters !== [] ? __('cmsadmin.empty_filtered') : __('properties.empty_text'),
        'action' => $activeFilters !== [] ? null : ['label' => __('properties.create'), 'url' => 'annonces/nouvelle'],
    ]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table im-table--list">
      <thead>
        <tr>
          <th scope="col"><?= e(__('properties.singular')) ?></th>
          <th scope="col"><?= e(__('agencies.location')) ?></th>
          <th scope="col" class="text-end"><?= e(__('properties.fields.price')) ?></th>
          <?php if ($isStaff): ?><th scope="col"><?= e(__('agencies.singular')) ?></th><?php endif; ?>
          <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
          <th scope="col"><span class="visually-hidden"><?= e(__('cmsadmin.actions')) ?></span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row):
            $url = cmsadmin_url('annonces/' . $row['reference']);
            $expiring = $row['status'] === 'published' && $row['expires_at'] !== null && strtotime((string) $row['expires_at']) < time() + 7 * 86400;
        ?>
        <tr<?= in_array($row['status'], ['archived', 'expired', 'rejected'], true) ? ' class="is-muted"' : '' ?>>
          <td>
            <div class="im-property-cell">
              <span class="im-thumb" aria-hidden="true">
                <?php if ($row['cover_path']): ?><img src="<?= e(url($row['cover_path'] . '-400.webp')) ?>" alt="" width="56" height="42" loading="lazy"><?php else: ?><span class="mdi mdi-image-off-outline"></span><?php endif; ?>
              </span>
              <div>
                <a class="im-property-cell__title im-cell-link" href="<?= e($url) ?>"><?= e($row['title']) ?></a>
                <span class="im-property-cell__meta">
                  <?= e($row['reference']) ?> · <?= e($row['category_name']) ?> · <?= e($row['transaction_name']) ?>
                  <?php if ((int) $row['is_featured'] === 1): ?><span class="im-tag"><?= e(__('properties.featured')) ?></span><?php endif; ?>
                  <?php if ($row['pending_revision_id'] !== null): ?><span class="im-tag im-tag--warning"><?= e(__('properties.tabs.revision')) ?></span><?php endif; ?>
                </span>
              </div>
            </div>
          </td>
          <td><span class="im-cell-main"><?= e($row['commune_name'] ?? $row['city_name']) ?></span><span class="im-cell-sub"><?= e($row['district_name'] ?? $row['city_name']) ?></span></td>
          <td class="text-end">
            <span class="im-cell-main im-num"><?= e($row['price'] !== null ? format_price($row['price']) : __('common.price_on_request')) ?></span>
            <span class="im-cell-sub"><?= e(price_period_label((string) $row['price_period'])) ?></span>
          </td>
          <?php if ($isStaff): ?><td class="im-cell-sub-text"><?= e($row['agency_name'] ?? __('properties.source.' . $row['source'])) ?></td><?php endif; ?>
          <td>
            <?= cmsadmin_partial('status-badge', ['status' => $row['status']]) ?>
            <span class="im-cell-sub mt-1">
              <?php if ($expiring): ?><?= e(__('properties.expires_on', ['date' => substr((string) $row['expires_at'], 0, 10)])) ?><?php else: ?><?= e(__('properties.updated_on', ['date' => substr((string) ($row['updated_at'] ?? $row['created_at']), 0, 10)])) ?><?php endif; ?>
            </span>
          </td>
          <td class="text-end">
            <?= cmsadmin_partial('row-actions', ['label' => $row['reference'], 'items' => array_values(array_filter([
                ['url' => $url, 'label' => __('properties.open'), 'icon' => 'mdi-file-document-outline'],
                ['url' => $url . '/modifier', 'label' => __('cmsadmin.edit'), 'icon' => 'mdi-pencil-outline'],
                $isStaff && ($row['status'] === 'pending' || $row['pending_revision_id'] !== null) ? ['url' => $url . '#validation', 'label' => __('properties.review'), 'icon' => 'mdi-check-decagram-outline'] : null,
            ]))]) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= cmsadmin_partial('pagination', $pagination + ['path' => 'annonces', 'query' => $activeFilters]) ?>
  <?php endif; ?>
</section>
