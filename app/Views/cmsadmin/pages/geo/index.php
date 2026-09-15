<?php
/**
 * Liste d'un niveau du référentiel géographique (villes, communes ou quartiers).
 *
 * @var string                    $level      city | commune | district
 * @var string                    $path       villes | communes | quartiers
 * @var list<array<string,mixed>> $rows
 * @var array                     $filters    ['q', 'etat', 'ville', 'commune']
 * @var array                     $pagination
 * @var array                     $country    ['id', 'name', 'iso2']
 * @var array<int, string>        $countries  Sélecteur de pays (Super Admin uniquement)
 * @var array<int, string>        $cities
 * @var array<int, string>        $communes
 */
$base = 'geo/' . $path;
$query = (string) app()->request()?->server('QUERY_STRING', '');
$current = '/cmsadmin/' . $base . ($query !== '' ? '?' . $query : '');
$activeFilters = array_filter($filters);
$tabs = ['city' => 'villes', 'commune' => 'communes', 'district' => 'quartiers'];
$createUrl = cmsadmin_url($base . '/ajouter') . match ($level) {
    'commune' => $filters['ville'] ? '?ville=' . $filters['ville'] : '',
    'district' => $filters['commune'] ? '?commune=' . $filters['commune'] : '',
    default => '',
};
?>
<?= cmsadmin_partial('page-header', [
    'title' => __("geo.{$level}.plural"),
    'subtitle' => __('geo.subtitle', ['country' => $country['name']]),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('geo.title')], ['label' => __("geo.{$level}.plural")]],
]) ?>

<section class="card im-panel im-panel--flush">
  <div class="im-panel-bar">
    <nav class="im-tabs im-tabs--flat" aria-label="<?= e(__('geo.title')) ?>">
      <?php foreach ($tabs as $tabLevel => $tabPath): ?>
      <a class="im-tabs__item<?= $tabLevel === $level ? ' is-current' : '' ?>" href="<?= e(cmsadmin_url('geo/' . $tabPath)) ?>"<?= $tabLevel === $level ? ' aria-current="page"' : '' ?>><?= e(__("geo.{$tabLevel}.plural")) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="im-panel-bar__actions">
      <?php if ($countries !== []): ?>
      <form method="post" action="<?= e(cmsadmin_url('geo/pays')) ?>" class="im-country-switch">
        <?= csrf_field() ?>
        <input type="hidden" name="_back" value="<?= e('/cmsadmin/' . $base) ?>">
        <label class="visually-hidden" for="geo-country"><?= e(__('geo.working_country')) ?></label>
        <span class="mdi mdi-earth" aria-hidden="true"></span>
        <select class="form-select form-select-sm" id="geo-country" name="country_id" onchange="this.form.submit()">
          <?php foreach ($countries as $id => $name): ?>
          <option value="<?= e($id) ?>"<?= $id === $country['id'] ? ' selected' : '' ?>><?= e($name) ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-sm im-btn-ghost" type="submit"><?= e(__('cmsadmin.apply')) ?></button></noscript>
      </form>
      <?php endif; ?>
      <a class="btn btn-primary btn-sm" href="<?= e($createUrl) ?>"><span class="mdi mdi-plus" aria-hidden="true"></span> <?= e(__("geo.{$level}.create")) ?></a>
    </div>
  </div>

  <form class="im-filters" method="get" action="<?= e(cmsadmin_url($base)) ?>">
    <div class="im-filters__search">
      <span class="mdi mdi-magnify" aria-hidden="true"></span>
      <label class="visually-hidden" for="f-q"><?= e(__('cmsadmin.search')) ?></label>
      <input class="form-control" id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('geo.search_placeholder')) ?>">
    </div>
    <?php if ($level !== 'city'): ?>
    <div class="im-filters__field">
      <label class="visually-hidden" for="f-ville"><?= e(__('geo.city.singular')) ?></label>
      <select class="form-select" id="f-ville" name="ville" data-im-select data-placeholder="<?= e(__('geo.all_cities')) ?>">
        <option value=""></option>
        <?php foreach ($cities as $id => $name): ?>
        <option value="<?= e($id) ?>"<?= $filters['ville'] === $id ? ' selected' : '' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <?php if ($level === 'district'): ?>
    <div class="im-filters__field">
      <label class="visually-hidden" for="f-commune"><?= e(__('geo.commune.singular')) ?></label>
      <select class="form-select" id="f-commune" name="commune" data-im-select data-placeholder="<?= e(__('geo.all_communes')) ?>">
        <option value=""></option>
        <?php foreach ($communes as $id => $name): ?>
        <option value="<?= e($id) ?>"<?= $filters['commune'] === $id ? ' selected' : '' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="im-filters__field im-filters__field--narrow">
      <label class="visually-hidden" for="f-etat"><?= e(__('cmsadmin.state')) ?></label>
      <select class="form-select" id="f-etat" name="etat">
        <option value=""><?= e(__('cmsadmin.all_states')) ?></option>
        <option value="actifs"<?= $filters['etat'] === 'actifs' ? ' selected' : '' ?>><?= e(__('cmsadmin.active_plural')) ?></option>
        <option value="inactifs"<?= $filters['etat'] === 'inactifs' ? ' selected' : '' ?>><?= e(__('cmsadmin.inactive_plural')) ?></option>
      </select>
    </div>
    <div class="im-filters__actions">
      <button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.filter')) ?></button>
      <?php if ($activeFilters !== []): ?>
      <a class="im-link-muted" href="<?= e(cmsadmin_url($base)) ?>"><?= e(__('cmsadmin.reset')) ?></a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($rows === []): ?>
    <?= cmsadmin_partial('empty-state', [
        'icon' => 'mdi-map-marker-off-outline',
        'title' => __('geo.empty_title'),
        'text' => $activeFilters !== [] ? __('cmsadmin.empty_filtered') : __('geo.empty_text'),
        'action' => $activeFilters !== [] ? null : ['label' => __("geo.{$level}.create"), 'url' => $base . '/ajouter'],
    ]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table im-table--list">
      <thead>
        <tr>
          <th scope="col"><?= e(__("geo.{$level}.singular")) ?></th>
          <?php if ($level !== 'city'): ?><th scope="col"><?= e(__($level === 'commune' ? 'geo.city.singular' : 'geo.location')) ?></th><?php endif; ?>
          <?php if ($level !== 'district'): ?><th scope="col" class="text-end"><?= e(__($level === 'city' ? 'geo.commune.plural' : 'geo.district.plural')) ?></th><?php endif; ?>
          <th scope="col" class="text-end"><?= e(__('cmsadmin.sort_order')) ?></th>
          <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
          <th scope="col"><span class="visually-hidden"><?= e(__('cmsadmin.actions')) ?></span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): $editUrl = cmsadmin_url($base . '/' . $row['id'] . '/modifier') . '?retour=' . rawurlencode($current); ?>
        <tr<?= (int) $row['is_active'] === 0 ? ' class="is-muted"' : '' ?>>
          <td>
            <a class="im-cell-main im-cell-link" href="<?= e($editUrl) ?>"><?= e($row['name']) ?></a>
            <span class="im-cell-sub">/<?= e($row['slug']) ?><?= $row['latitude'] !== null ? ' · ' . e(__('geo.has_gps')) : '' ?></span>
          </td>
          <?php if ($level === 'commune'): ?>
          <td><?= e($row['city_name']) ?></td>
          <?php elseif ($level === 'district'): ?>
          <td><span class="im-cell-main"><?= e($row['commune_name']) ?></span><span class="im-cell-sub"><?= e($row['city_name']) ?></span></td>
          <?php endif; ?>
          <?php if ($level === 'city'): ?>
          <td class="text-end im-num"><a class="im-cell-link" href="<?= e(cmsadmin_url('geo/communes?ville=' . $row['id'])) ?>"><?= e(format_number((int) $row['communes_count'])) ?></a></td>
          <?php elseif ($level === 'commune'): ?>
          <td class="text-end im-num"><a class="im-cell-link" href="<?= e(cmsadmin_url('geo/quartiers?commune=' . $row['id'])) ?>"><?= e(format_number((int) $row['districts_count'])) ?></a></td>
          <?php endif; ?>
          <td class="text-end im-num"><?= e($row['sort_order']) ?></td>
          <td><?= cmsadmin_partial('state-badge', ['active' => (bool) $row['is_active']]) ?></td>
          <td class="text-end">
            <?= cmsadmin_partial('row-actions', ['label' => $row['name'], 'items' => array_values(array_filter([
                ['url' => $editUrl, 'label' => __('cmsadmin.edit'), 'icon' => 'mdi-pencil-outline'],
                $level === 'city' ? ['url' => cmsadmin_url('geo/communes/ajouter?ville=' . $row['id']), 'label' => __('geo.commune.create'), 'icon' => 'mdi-plus'] : null,
                $level === 'commune' ? ['url' => cmsadmin_url('geo/quartiers/ajouter?commune=' . $row['id']), 'label' => __('geo.district.create'), 'icon' => 'mdi-plus'] : null,
                ['post' => cmsadmin_url($base . '/' . $row['id'] . '/activation') . '?retour=' . rawurlencode($current), 'label' => __((int) $row['is_active'] === 1 ? 'cmsadmin.deactivate' : 'cmsadmin.activate'), 'icon' => (int) $row['is_active'] === 1 ? 'mdi-eye-off-outline' : 'mdi-eye-outline'],
                ['post' => cmsadmin_url($base . '/' . $row['id'] . '/supprimer') . '?retour=' . rawurlencode($current), 'label' => __('cmsadmin.delete'), 'icon' => 'mdi-trash-can-outline', 'danger' => true, 'confirm' => __('cmsadmin.confirm_delete', ['name' => $row['name']])],
            ]))]) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?= cmsadmin_partial('pagination', $pagination + ['path' => $base, 'query' => $activeFilters]) ?>
  <?php endif; ?>
</section>
