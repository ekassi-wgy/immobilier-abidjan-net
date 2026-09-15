<?php
/**
 * Utilisateurs internes (Super Admins, Admins Pays).
 *
 * @var list<array<string,mixed>> $rows
 * @var array                     $filters ['q', 'role', 'etat']
 * @var array<string,string>      $roles
 * @var int                       $currentUserId
 */
$activeFilters = array_filter($filters);
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('users.title'),
    'subtitle' => __('users.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('users.title')]],
    'actions' => [['label' => __('users.create'), 'url' => 'utilisateurs/ajouter', 'icon' => 'mdi-account-plus-outline']],
]) ?>

<section class="card im-panel im-panel--flush">
  <form class="im-filters" method="get" action="<?= e(cmsadmin_url('utilisateurs')) ?>">
    <div class="im-filters__search">
      <span class="mdi mdi-magnify" aria-hidden="true"></span>
      <label class="visually-hidden" for="f-q"><?= e(__('cmsadmin.search')) ?></label>
      <input class="form-control" id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('users.search_placeholder')) ?>">
    </div>
    <div class="im-filters__field">
      <label class="visually-hidden" for="f-role"><?= e(__('auth.account.role')) ?></label>
      <select class="form-select" id="f-role" name="role">
        <option value=""><?= e(__('users.all_roles')) ?></option>
        <?php foreach ($roles as $code => $label): ?><option value="<?= e($code) ?>"<?= $filters['role'] === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
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
      <?php if ($activeFilters !== []): ?><a class="im-link-muted" href="<?= e(cmsadmin_url('utilisateurs')) ?>"><?= e(__('cmsadmin.reset')) ?></a><?php endif; ?>
    </div>
  </form>

  <?php if ($rows === []): ?>
    <?= cmsadmin_partial('empty-state', ['icon' => 'mdi-account-search-outline', 'title' => __('users.empty'), 'text' => __('cmsadmin.empty_filtered')]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table im-table--list">
      <thead>
        <tr>
          <th scope="col"><?= e(__('users.singular')) ?></th>
          <th scope="col"><?= e(__('auth.account.role')) ?></th>
          <th scope="col"><?= e(__('auth.account.last_login')) ?></th>
          <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
          <th scope="col"><span class="visually-hidden"><?= e(__('cmsadmin.actions')) ?></span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row):
            $name = $row['first_name'] . ' ' . $row['last_name'];
            $pending = (int) $row['must_change_password'] === 1 && $row['last_login_at'] === null;
            $isSelf = (int) $row['id'] === $currentUserId;
            $editUrl = cmsadmin_url('utilisateurs/' . $row['id'] . '/modifier');
        ?>
        <tr<?= (int) $row['is_active'] === 0 ? ' class="is-muted"' : '' ?>>
          <td>
            <a class="im-cell-main im-cell-link" href="<?= e($editUrl) ?>"><?= e($name) ?></a>
            <span class="im-cell-sub"><?= e($row['email']) ?><?php if ($isSelf): ?> <span class="im-tag"><?= e(__('users.you')) ?></span><?php endif; ?></span>
          </td>
          <td><span class="im-cell-main"><?= e(__('auth.roles.' . $row['role'])) ?></span><span class="im-cell-sub"><?= e($row['country_name'] ?? __('users.all_countries')) ?><?= $row['job_title'] ? ' · ' . e($row['job_title']) : '' ?></span></td>
          <td class="im-cell-sub-text"><?= $row['last_login_at'] !== null ? e(substr((string) $row['last_login_at'], 0, 16)) . ' UTC' : '—' ?></td>
          <td><?= cmsadmin_partial('state-badge', (int) $row['is_active'] === 0 ? ['active' => false] : ($pending ? ['label' => __('users.invitation_pending'), 'variant' => 'pending'] : ['active' => true])) ?></td>
          <td class="text-end">
            <?= cmsadmin_partial('row-actions', ['label' => $name, 'items' => array_values(array_filter([
                ['url' => $editUrl, 'label' => __('cmsadmin.edit'), 'icon' => 'mdi-pencil-outline'],
                (int) $row['is_active'] === 1 && !$isSelf ? ['post' => cmsadmin_url('utilisateurs/' . $row['id'] . '/invitation'), 'label' => __($pending ? 'users.resend_invitation' : 'users.send_reset_link'), 'icon' => 'mdi-email-fast-outline'] : null,
                !$isSelf ? ['post' => cmsadmin_url('utilisateurs/' . $row['id'] . '/activation'), 'label' => __((int) $row['is_active'] === 1 ? 'cmsadmin.deactivate' : 'cmsadmin.activate'), 'icon' => (int) $row['is_active'] === 1 ? 'mdi-account-off-outline' : 'mdi-account-check-outline'] : null,
                !$isSelf ? ['post' => cmsadmin_url('utilisateurs/' . $row['id'] . '/supprimer'), 'label' => __('cmsadmin.delete'), 'icon' => 'mdi-trash-can-outline', 'danger' => true, 'confirm' => __('users.confirm_delete', ['name' => $name])] : null,
            ]))]) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
