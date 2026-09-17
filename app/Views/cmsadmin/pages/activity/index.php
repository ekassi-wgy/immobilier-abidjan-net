<?php
/**
 * Journal d'activité : liste en lecture seule, filtres et pagination côté serveur.
 * Aucun bouton d'écriture ici — une entrée du journal ne se modifie ni ne se supprime.
 *
 * @var list<array<string,mixed>> $rows
 * @var array                     $filters  ['q','action','module','utilisateur','du','au']
 * @var list<string>              $actions
 * @var list<string>              $modules
 * @var list<array<string,mixed>> $users
 * @var bool                      $showCountry  Super Admin : la colonne Pays est affichée
 * @var array                     $pagination
 */
$activeFilters = array_filter($filters, static fn ($v): bool => (string) $v !== '');

/** Traduit une clé, ou rend la valeur brute si elle n'est pas traduite (nouveau verbe journalisé). */
$translate = static function (string $key, string $raw): string {
    $label = __($key);

    return $label === $key ? $raw : $label;
};
$moduleLabel = static fn (string $module): string => $translate('activity.modules.' . $module, $module);

/** « property.approved » → « Annonces · Validation ». Une action inconnue reste lisible telle quelle. */
$actionLabel = static function (string $action) use ($translate, $moduleLabel): string {
    [$module, $verb] = array_pad(explode('.', $action, 2), 2, '');
    if ($verb === '') {
        return $action;
    }

    return $moduleLabel($module) . ' · ' . $translate('activity.verbs.' . $verb, $verb);
};

/** Les actions destructrices ou sensibles se repèrent d'un coup d'œil. */
$variant = static function (string $action): string {
    $verb = explode('.', $action)[1] ?? '';

    return match (true) {
        str_contains($verb, 'delete') || $verb === 'rejected' || str_contains($verb, 'deactivated') => 'rejected',
        str_contains($verb, 'creat') || $verb === 'approved' || $verb === 'published' => 'published',
        $verb === 'login' || $verb === 'logout' => 'archived',
        default => 'unpublished',
    };
};
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('activity.title'),
    'subtitle' => __('activity.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('activity.title')]],
]) ?>

<section class="card im-panel im-panel--flush">
  <form class="im-filters" method="get" action="<?= e(cmsadmin_url('journal')) ?>">
    <div class="im-filters__search">
      <span class="mdi mdi-magnify" aria-hidden="true"></span>
      <label class="visually-hidden" for="f-q"><?= e(__('cmsadmin.search')) ?></label>
      <input class="form-control" id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('activity.search_placeholder')) ?>">
    </div>
    <div class="im-filters__field">
      <label class="visually-hidden" for="f-module"><?= e(__('activity.module_label')) ?></label>
      <select class="form-select" id="f-module" name="module">
        <option value=""><?= e(__('activity.all_modules')) ?></option>
        <?php foreach ($modules as $module): ?>
        <option value="<?= e($module) ?>"<?= $filters['module'] === $module ? ' selected' : '' ?>><?= e($moduleLabel($module)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="im-filters__field">
      <label class="visually-hidden" for="f-action"><?= e(__('activity.action_label')) ?></label>
      <select class="form-select" id="f-action" name="action">
        <option value=""><?= e(__('activity.all_actions')) ?></option>
        <?php foreach ($actions as $action): ?>
        <option value="<?= e($action) ?>"<?= $filters['action'] === $action ? ' selected' : '' ?>><?= e($actionLabel($action)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="im-filters__field">
      <label class="visually-hidden" for="f-user"><?= e(__('activity.user_label')) ?></label>
      <select class="form-select" id="f-user" name="utilisateur">
        <option value=""><?= e(__('activity.all_users')) ?></option>
        <?php foreach ($users as $user): ?>
        <option value="<?= e((string) $user['user_id']) ?>"<?= $filters['utilisateur'] === (string) $user['user_id'] ? ' selected' : '' ?>><?= e((string) $user['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="im-filters__dates">
      <label for="f-du"><?= e(__('activity.from')) ?></label>
      <input class="form-control" id="f-du" type="date" name="du" value="<?= e($filters['du']) ?>" max="<?= e(gmdate('Y-m-d')) ?>">
      <label for="f-au"><?= e(__('activity.to')) ?></label>
      <input class="form-control" id="f-au" type="date" name="au" value="<?= e($filters['au']) ?>" max="<?= e(gmdate('Y-m-d')) ?>">
    </div>
    <div class="im-filters__actions">
      <button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.filter')) ?></button>
      <?php if ($activeFilters !== []): ?><a class="im-link-muted" href="<?= e(cmsadmin_url('journal')) ?>"><?= e(__('cmsadmin.reset')) ?></a><?php endif; ?>
    </div>
  </form>

  <?php if ($rows === []): ?>
    <?= cmsadmin_partial('empty-state', [
        'icon' => 'mdi-history',
        'title' => __('activity.empty_title'),
        'text' => $activeFilters !== [] ? __('cmsadmin.empty_filtered') : __('activity.empty_text'),
    ]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table im-table--list">
      <thead>
        <tr>
          <th scope="col"><?= e(__('activity.when')) ?></th>
          <th scope="col"><?= e(__('activity.who')) ?></th>
          <th scope="col"><?= e(__('activity.what')) ?></th>
          <th scope="col"><?= e(__('activity.target')) ?></th>
          <?php if ($showCountry): ?><th scope="col"><?= e(__('activity.country')) ?></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row):
            $changes = $row['changes'] !== null ? json_decode((string) $row['changes'], true) : null;
            $before = is_array($changes['before'] ?? null) ? $changes['before'] : [];
            $after = is_array($changes['after'] ?? null) ? $changes['after'] : [];
        ?>
        <tr>
          <td class="im-cell-sub-text im-num"><?= e(str_replace(' ', ' · ', substr((string) $row['created_at'], 0, 16))) ?></td>
          <td>
            <?php if ($row['user_name'] !== null): ?>
            <span class="im-cell-main"><?= e(trim((string) $row['user_name'])) ?></span>
            <span class="im-cell-sub"><?= e((string) $row['user_email']) ?></span>
            <?php else: ?>
            <span class="im-cell-sub-text"><?= e(__('activity.system')) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?= cmsadmin_partial('state-badge', ['label' => $actionLabel((string) $row['action']), 'variant' => $variant((string) $row['action'])]) ?>
            <?php if ($row['ip'] !== null): ?>
            <span class="im-cell-sub mt-1"><?= e((string) (App\Services\IpAddress::fromBinary((string) $row['ip']) ?? '')) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <span class="im-cell-main"><?= e((string) ($row['description'] ?? '—')) ?></span>
            <?php if ($row['entity_type'] !== null): ?>
            <span class="im-cell-sub"><?= e((string) $row['entity_type'] . ($row['entity_id'] !== null ? ' #' . $row['entity_id'] : '')) ?></span>
            <?php endif; ?>
            <?php if ($after !== []): ?>
            <details class="im-log-diff">
              <summary><?= e(__n('activity.changed_fields', count($after))) ?></summary>
              <dl>
                <?php foreach ($after as $field => $value): ?>
                <dt><?= e((string) $field) ?></dt>
                <dd>
                  <del><?= e(mb_substr(trim((string) ($before[$field] ?? '')), 0, 120) ?: '—') ?></del>
                  <ins><?= e(mb_substr(trim((string) $value), 0, 120) ?: '—') ?></ins>
                </dd>
                <?php endforeach; ?>
              </dl>
            </details>
            <?php endif; ?>
          </td>
          <?php if ($showCountry): ?><td class="im-cell-sub-text"><?= e((string) ($row['country_name'] ?? '—')) ?></td><?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= cmsadmin_partial('pagination', $pagination + ['path' => 'journal', 'query' => $activeFilters]) ?>
  <?php endif; ?>
</section>
