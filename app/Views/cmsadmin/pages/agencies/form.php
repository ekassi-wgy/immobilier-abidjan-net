<?php
/**
 * Création / modification d'une agence partenaire, avec ses comptes.
 *
 * @var array<string,mixed>|null  $agency
 * @var array<string,mixed>       $values
 * @var array<string,string>      $errors
 * @var array<int,string>         $cities
 * @var array<int,string>         $communes    [id => « Ville · Commune »]
 * @var list<array<string,mixed>> $accounts
 * @var int                       $propertiesCount
 * @var array<string,mixed>|null  $partnerRequest Demande à l'origine de la création
 * @var array<string,string>      $statuses
 */
$isEdit = $agency !== null;
$value = static fn (string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
$zones = array_map('intval', (array) $value('zones', []));
$field = static fn (array $options): string => cmsadmin_partial('field', $options + ['class' => '', 'error' => $errors[$options['name']] ?? null, 'value' => $value($options['name'])]);
$accountUrl = static fn (array $account, string $suffix = ''): string => cmsadmin_url('agences/' . $agency['id'] . '/comptes/' . $account['id'] . $suffix);
?>
<?= cmsadmin_partial('page-header', [
    'title' => $isEdit ? $agency['name'] : __('agencies.create'),
    'subtitle' => $isEdit ? trim(($agency['commune_name'] ?? '') . ' · ' . ($agency['city_name'] ?? ''), ' ·') : __('agencies.create_subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('agencies.title'), 'url' => 'agences'], ['label' => $isEdit ? $agency['name'] : __('agencies.create')]],
]) ?>

<?php if ($partnerRequest !== null): ?>
<div class="im-flash im-flash--info" role="status">
  <span class="mdi mdi-handshake-outline" aria-hidden="true"></span>
  <p><?= e(__('agencies.from_request', ['name' => $partnerRequest['agency_name'], 'date' => substr((string) $partnerRequest['created_at'], 0, 10)])) ?></p>
</div>
<?php endif; ?>

<?php if ($errors !== []): ?>
<div class="im-flash im-flash--error" role="alert">
  <span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span>
  <p><?= e(__('cmsadmin.form_errors', ['count' => count($errors)])) ?></p>
</div>
<?php endif; ?>

<div class="im-form__layout">
  <div class="im-form__main">
    <form class="im-form" id="agency-form" method="post" enctype="multipart/form-data" action="<?= e(cmsadmin_url($isEdit ? 'agences/' . $agency['id'] : 'agences')) ?>" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= 2 * 1024 * 1024 ?>">
      <?php if ($partnerRequest !== null): ?><input type="hidden" name="partner_request_id" value="<?= e($partnerRequest['id']) ?>"><?php endif; ?>

      <section class="card im-panel im-form-section" id="identite">
        <header class="im-form-section__head"><span class="im-form-section__index">01</span><h2 class="im-panel__title"><?= e(__('agencies.identity')) ?></h2></header>
        <div class="row g-3">
          <div class="col-md-7"><?= $field(['name' => 'name', 'label' => __('agencies.name'), 'required' => true, 'attributes' => ['maxlength' => 150, 'data-slug-source' => 'slug']]) ?></div>
          <div class="col-md-5"><?= $field(['name' => 'slug', 'label' => __('cmsadmin.slug'), 'optional' => !$isEdit, 'hint' => $isEdit ? __('agencies.slug_warning') : __('cmsadmin.slug_hint'), 'attributes' => ['maxlength' => 170, 'spellcheck' => 'false']]) ?></div>
          <div class="col-md-6"><?= $field(['name' => 'legal_name', 'label' => __('agencies.legal_name'), 'optional' => true, 'attributes' => ['maxlength' => 190]]) ?></div>
          <div class="col-md-3"><?= $field(['name' => 'rccm', 'label' => __('agencies.rccm'), 'optional' => true, 'attributes' => ['maxlength' => 60, 'placeholder' => 'CI-ABJ-2024-B-12345']]) ?></div>
          <div class="col-md-3"><?= $field(['name' => 'tax_id', 'label' => __('agencies.tax_id'), 'optional' => true, 'attributes' => ['maxlength' => 60]]) ?></div>
          <div class="col-12"><?= $field(['name' => 'description', 'type' => 'textarea', 'label' => __('agencies.description'), 'optional' => true, 'hint' => __('agencies.description_hint'), 'attributes' => ['maxlength' => 5000]]) ?></div>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="coordonnees">
        <header class="im-form-section__head"><span class="im-form-section__index">02</span><h2 class="im-panel__title"><?= e(__('sites.contacts')) ?></h2></header>
        <p class="im-panel__subtitle"><?= e(__('agencies.contacts_hint')) ?></p>
        <div class="row g-3">
          <div class="col-md-4"><?= $field(['name' => 'email', 'type' => 'email', 'label' => __('auth.email'), 'optional' => true, 'attributes' => ['maxlength' => 190]]) ?></div>
          <div class="col-md-4"><?= $field(['name' => 'phone', 'type' => 'tel', 'label' => __('sites.phone'), 'optional' => true, 'attributes' => ['maxlength' => 30, 'placeholder' => '+225 07 00 00 00 00']]) ?></div>
          <div class="col-md-4"><?= $field(['name' => 'whatsapp', 'type' => 'tel', 'label' => 'WhatsApp', 'optional' => true, 'attributes' => ['maxlength' => 30]]) ?></div>
          <div class="col-md-6"><?= $field(['name' => 'website', 'type' => 'url', 'label' => __('agencies.website'), 'optional' => true, 'attributes' => ['maxlength' => 255, 'placeholder' => 'https://']]) ?></div>
          <div class="col-md-6"><?= $field(['name' => 'address', 'label' => __('sites.address'), 'optional' => true, 'attributes' => ['maxlength' => 255]]) ?></div>
          <div class="col-md-6"><?= $field(['name' => 'city_id', 'type' => 'select', 'label' => __('geo.city.singular'), 'options' => $cities, 'placeholder' => '', 'optional' => true, 'attributes' => ['data-im-select' => '', 'data-placeholder' => __('cmsadmin.choose')]]) ?></div>
          <div class="col-md-6"><?= $field(['name' => 'commune_id', 'type' => 'select', 'label' => __('agencies.commune'), 'options' => $communes, 'placeholder' => '', 'optional' => true, 'attributes' => ['data-im-select' => '', 'data-placeholder' => __('cmsadmin.choose')]]) ?></div>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="zones">
        <header class="im-form-section__head"><span class="im-form-section__index">03</span><h2 class="im-panel__title"><?= e(__('agencies.zones')) ?></h2></header>
        <p class="im-panel__subtitle"><?= e(__('agencies.zones_hint')) ?></p>
        <label class="visually-hidden" for="f-zones"><?= e(__('agencies.zones')) ?></label>
        <select class="form-select" id="f-zones" name="zones[]" multiple data-im-select data-placeholder="<?= e(__('agencies.zones_placeholder')) ?>">
          <?php foreach ($communes as $id => $label): ?>
          <option value="<?= e($id) ?>"<?= in_array($id, $zones, true) ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </section>

      <section class="card im-panel im-form-section" id="logo">
        <header class="im-form-section__head"><span class="im-form-section__index">04</span><h2 class="im-panel__title"><?= e(__('agencies.logo')) ?></h2></header>
        <div class="im-logo-field">
          <span class="im-logo-thumb im-logo-thumb--lg" aria-hidden="true">
            <?php if ($isEdit && $agency['logo_path']): ?><img src="<?= e(url($agency['logo_path'])) ?>" alt="" width="96" height="96"><?php else: ?><span class="mdi mdi-image-outline"></span><?php endif; ?>
          </span>
          <div class="im-logo-field__body">
            <label class="form-label" for="f-logo"><?= e(__($isEdit && $agency['logo_path'] ? 'agencies.logo_replace' : 'agencies.logo_add')) ?> <span class="im-optional"><?= e(__('cmsadmin.optional')) ?></span></label>
            <input class="form-control<?= isset($errors['logo']) ? ' is-invalid' : '' ?>" id="f-logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="f-logo-hint<?= isset($errors['logo']) ? ' f-logo-error' : '' ?>">
            <p class="form-text" id="f-logo-hint"><?= e(__('agencies.logo_hint')) ?></p>
            <?php if (isset($errors['logo'])): ?><p class="invalid-feedback d-block" id="f-logo-error"><?= e($errors['logo']) ?></p><?php endif; ?>
            <?php if ($isEdit && $agency['logo_path']): ?>
            <label class="im-check mt-2"><input type="checkbox" name="remove_logo" value="1"><span class="im-check__box" aria-hidden="true"><span class="mdi mdi-check"></span></span><span><?= e(__('agencies.logo_remove')) ?></span></label>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <?php if (!$isEdit): ?>
      <section class="card im-panel im-form-section" id="responsable">
        <header class="im-form-section__head"><span class="im-form-section__index">05</span><h2 class="im-panel__title"><?= e(__('agencies.owner')) ?></h2></header>
        <?= cmsadmin_partial('switch', ['name' => 'create_owner', 'label' => __('agencies.owner_create'), 'checked' => (bool) $value('create_owner', 0), 'hint' => __('agencies.owner_hint')]) ?>
        <div class="row g-3">
          <div class="col-md-4"><?= $field(['name' => 'owner_first_name', 'label' => __('users.first_name'), 'attributes' => ['maxlength' => 80, 'autocomplete' => 'off']]) ?></div>
          <div class="col-md-4"><?= $field(['name' => 'owner_last_name', 'label' => __('users.last_name'), 'attributes' => ['maxlength' => 80, 'autocomplete' => 'off']]) ?></div>
          <div class="col-md-4"><?= $field(['name' => 'owner_email', 'type' => 'email', 'label' => __('users.login_email'), 'attributes' => ['maxlength' => 190, 'autocomplete' => 'off']]) ?></div>
        </div>
      </section>
      <?php endif; ?>
    </form>

    <?php if ($isEdit): ?>
    <section class="card im-panel im-form-section" id="comptes">
      <header class="im-form-section__head im-form-section__head--actions">
        <span class="im-form-section__index">05</span>
        <h2 class="im-panel__title"><?= e(__('agencies.accounts')) ?></h2>
        <a class="btn btn-primary btn-sm ms-auto" href="<?= e(cmsadmin_url('agences/' . $agency['id'] . '/comptes/ajouter')) ?>"><span class="mdi mdi-account-plus-outline" aria-hidden="true"></span> <?= e(__('users.create_agency_account')) ?></a>
      </header>
      <p class="im-panel__subtitle"><?= e(__('agencies.accounts_hint')) ?></p>
      <?php if ($accounts === []): ?>
      <p class="im-note"><span class="mdi mdi-account-alert-outline" aria-hidden="true"></span> <?= e(__('agencies.no_accounts')) ?></p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table im-table">
          <thead><tr><th scope="col"><?= e(__('users.singular')) ?></th><th scope="col"><?= e(__('auth.account.role')) ?></th><th scope="col"><?= e(__('auth.account.last_login')) ?></th><th scope="col"><?= e(__('cmsadmin.state')) ?></th><th scope="col"><span class="visually-hidden"><?= e(__('cmsadmin.actions')) ?></span></th></tr></thead>
          <tbody>
            <?php foreach ($accounts as $account): $pending = (int) $account['must_change_password'] === 1 && $account['last_login_at'] === null; $name = $account['first_name'] . ' ' . $account['last_name']; ?>
            <tr<?= (int) $account['is_active'] === 0 ? ' class="is-muted"' : '' ?>>
              <td><a class="im-cell-main im-cell-link" href="<?= e($accountUrl($account, '/modifier')) ?>"><?= e($name) ?></a><span class="im-cell-sub"><?= e($account['email']) ?></span></td>
              <td><?= e(__('auth.roles.' . $account['role'])) ?></td>
              <td class="im-cell-sub-text"><?= $account['last_login_at'] !== null ? e(substr((string) $account['last_login_at'], 0, 16)) . ' UTC' : '—' ?></td>
              <td><?= cmsadmin_partial('state-badge', (int) $account['is_active'] === 0 ? ['active' => false] : ($pending ? ['label' => __('users.invitation_pending'), 'variant' => 'pending'] : ['active' => true])) ?></td>
              <td class="text-end">
                <?= cmsadmin_partial('row-actions', ['label' => $name, 'items' => array_values(array_filter([
                    ['url' => $accountUrl($account, '/modifier'), 'label' => __('cmsadmin.edit'), 'icon' => 'mdi-pencil-outline'],
                    (int) $account['is_active'] === 1 ? ['post' => $accountUrl($account, '/invitation'), 'label' => __($pending ? 'users.resend_invitation' : 'users.send_reset_link'), 'icon' => 'mdi-email-fast-outline'] : null,
                    ['post' => $accountUrl($account, '/activation'), 'label' => __((int) $account['is_active'] === 1 ? 'cmsadmin.deactivate' : 'cmsadmin.activate'), 'icon' => (int) $account['is_active'] === 1 ? 'mdi-account-off-outline' : 'mdi-account-check-outline'],
                    ['post' => $accountUrl($account, '/supprimer'), 'label' => __('cmsadmin.delete'), 'icon' => 'mdi-trash-can-outline', 'danger' => true, 'confirm' => __('users.confirm_delete', ['name' => $name])],
                ]))]) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </div>

  <aside class="im-form__aside">
    <div class="im-sticky">
      <section class="card im-panel">
        <h2 class="im-panel__title"><?= e(__('cmsadmin.publication')) ?></h2>
        <div class="mt-3">
          <?= cmsadmin_partial('field', ['name' => 'status', 'type' => 'select', 'label' => __('cmsadmin.state'), 'options' => $statuses, 'value' => $value('status', 'active'), 'required' => true, 'hint' => __('agencies.status_hint'), 'error' => $errors['status'] ?? null, 'attributes' => ['form' => 'agency-form']]) ?>
          <div class="mb-3">
            <input type="hidden" name="is_verified" value="0" form="agency-form">
            <label class="im-switch"><input type="checkbox" name="is_verified" value="1" form="agency-form"<?= (int) $value('is_verified', 0) === 1 ? ' checked' : '' ?>><span class="im-switch__track" aria-hidden="true"></span><span><?= e(__('agencies.verified_switch')) ?></span></label>
            <p class="form-text"><?= e($isEdit && $agency['verified_at'] ? __('agencies.verified_since', ['date' => substr((string) $agency['verified_at'], 0, 10)]) : __('agencies.verified_hint')) ?></p>
          </div>
          <div class="mb-3">
            <input type="hidden" name="is_featured" value="0" form="agency-form">
            <label class="im-switch"><input type="checkbox" name="is_featured" value="1" form="agency-form"<?= (int) $value('is_featured', 0) === 1 ? ' checked' : '' ?>><span class="im-switch__track" aria-hidden="true"></span><span><?= e(__('agencies.featured_switch')) ?></span></label>
            <p class="form-text"><?= e(__('agencies.featured_hint')) ?></p>
          </div>
        </div>
        <?php if ($isEdit): ?>
        <dl class="im-meta-list">
          <div><dt><?= e(__('geo.usage.properties_label')) ?></dt><dd class="im-num"><?= e(format_number($propertiesCount)) ?></dd></div>
          <div><dt><?= e(__('agencies.accounts')) ?></dt><dd class="im-num"><?= e(count($accounts)) ?></dd></div>
          <div><dt><?= e(__('agencies.created_at')) ?></dt><dd><?= e(substr((string) $agency['created_at'], 0, 10)) ?></dd></div>
        </dl>
        <?php endif; ?>
        <div class="d-grid gap-2">
          <button class="btn btn-primary" type="submit" form="agency-form"><?= e(__($isEdit ? 'cmsadmin.save' : 'agencies.create_submit')) ?></button>
          <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url('agences')) ?>"><?= e(__('cmsadmin.cancel')) ?></a>
        </div>
      </section>

      <?php if ($isEdit && $propertiesCount === 0): ?>
      <form method="post" action="<?= e(cmsadmin_url('agences/' . $agency['id'] . '/supprimer')) ?>" class="im-danger-zone">
        <?= csrf_field() ?>
        <button class="btn im-btn-ghost im-btn-danger w-100" type="submit" data-confirm="<?= e(__('agencies.confirm_delete', ['name' => $agency['name']])) ?>"><span class="mdi mdi-trash-can-outline" aria-hidden="true"></span> <?= e(__('agencies.delete')) ?></button>
        <p class="form-text"><?= e(__('agencies.delete_hint')) ?></p>
      </form>
      <?php endif; ?>
    </div>
  </aside>
</div>
