<?php
/**
 * Profil public de l'agence connectée : modifiable par le responsable, consultable par l'agent.
 *
 * @var array<string,mixed>       $agency
 * @var array<string,mixed>       $values
 * @var array<string,string>      $errors
 * @var bool                      $isOwner
 * @var array<int,string>         $cities
 * @var array<int,string>         $communes   [id => « Ville · Commune »]
 * @var list<string>              $zoneNames  Zones actuelles, pour la lecture seule
 * @var int                       $propertiesCount
 * @var list<array<string,mixed>> $accounts
 */
$value = static fn (string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
$zones = array_map('intval', (array) $value('zones', []));
$field = static fn (array $options): string => cmsadmin_partial('field', $options + ['class' => '', 'error' => $errors[$options['name']] ?? null, 'value' => $value($options['name'])]);
$statusVariant = ['active' => 'published', 'suspended' => 'pending', 'closed' => 'unpublished'];
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('agency_profile.title'),
    'subtitle' => __($isOwner ? 'agency_profile.subtitle' : 'agency_profile.subtitle_agent'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('agency_profile.title')]],
]) ?>

<?php if ($errors !== []): ?>
<div class="im-flash im-flash--error" role="alert">
  <span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span>
  <p><?= e(__('cmsadmin.form_errors', ['count' => count($errors)])) ?></p>
</div>
<?php endif; ?>

<div class="im-form__layout">
  <div class="im-form__main">
    <?php if ($isOwner): ?>
    <form class="im-form" id="profile-form" method="post" enctype="multipart/form-data" action="<?= e(cmsadmin_url('profil-agence')) ?>" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= 2 * 1024 * 1024 ?>">

      <section class="card im-panel im-form-section" id="presentation">
        <header class="im-form-section__head"><span class="im-form-section__index">01</span><h2 class="im-panel__title"><?= e(__('agency_profile.presentation')) ?></h2></header>
        <p class="im-panel__subtitle"><?= e(__('agency_profile.presentation_hint')) ?></p>
        <div class="im-logo-field">
          <span class="im-logo-thumb im-logo-thumb--lg" aria-hidden="true">
            <?php if ($agency['logo_path']): ?><img src="<?= e(url($agency['logo_path'])) ?>" alt="" width="96" height="96"><?php else: ?><span class="mdi mdi-image-outline"></span><?php endif; ?>
          </span>
          <div class="im-logo-field__body">
            <label class="form-label" for="f-logo"><?= e(__($agency['logo_path'] ? 'agencies.logo_replace' : 'agencies.logo_add')) ?> <span class="im-optional"><?= e(__('cmsadmin.optional')) ?></span></label>
            <input class="form-control<?= isset($errors['logo']) ? ' is-invalid' : '' ?>" id="f-logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="f-logo-hint<?= isset($errors['logo']) ? ' f-logo-error' : '' ?>">
            <p class="form-text" id="f-logo-hint"><?= e(__('agencies.logo_hint')) ?></p>
            <?php if (isset($errors['logo'])): ?><p class="invalid-feedback d-block" id="f-logo-error"><?= e($errors['logo']) ?></p><?php endif; ?>
            <?php if ($agency['logo_path']): ?>
            <label class="im-check mt-2"><input type="checkbox" name="remove_logo" value="1"><span class="im-check__box" aria-hidden="true"><span class="mdi mdi-check"></span></span><span><?= e(__('agencies.logo_remove')) ?></span></label>
            <?php endif; ?>
          </div>
        </div>
        <div class="mt-3"><?= $field(['name' => 'description', 'type' => 'textarea', 'label' => __('agencies.description'), 'optional' => true, 'hint' => __('agencies.description_hint'), 'attributes' => ['maxlength' => 5000, 'rows' => 6]]) ?></div>
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
    </form>
    <?php else: ?>
    <section class="card im-panel">
      <h2 class="im-panel__title"><?= e(__('agency_profile.presentation')) ?></h2>
      <div class="im-logo-field mt-3">
        <span class="im-logo-thumb im-logo-thumb--lg" aria-hidden="true">
          <?php if ($agency['logo_path']): ?><img src="<?= e(url($agency['logo_path'])) ?>" alt="" width="96" height="96"><?php else: ?><span class="mdi mdi-image-outline"></span><?php endif; ?>
        </span>
        <div class="im-logo-field__body">
          <?php if (trim((string) ($agency['description'] ?? '')) !== ''): ?>
          <p class="im-panel__subtitle"><?= nl2br(e($agency['description'])) ?></p>
          <?php else: ?>
          <p class="im-panel__subtitle"><?= e(__('agency_profile.no_description')) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <dl class="im-detail-list">
        <div><dt><?= e(__('auth.email')) ?></dt><dd><?= e($agency['email'] ?? '—') ?></dd></div>
        <div><dt><?= e(__('sites.phone')) ?></dt><dd><?= e($agency['phone'] ?? '—') ?></dd></div>
        <div><dt>WhatsApp</dt><dd><?= e($agency['whatsapp'] ?? '—') ?></dd></div>
        <div><dt><?= e(__('agencies.website')) ?></dt><dd><?= e($agency['website'] ?? '—') ?></dd></div>
        <div><dt><?= e(__('sites.address')) ?></dt><dd><?= e($agency['address'] ?? '—') ?></dd></div>
        <div><dt><?= e(__('agencies.location')) ?></dt><dd><?= e(trim(($agency['commune_name'] ?? '') . ' · ' . ($agency['city_name'] ?? ''), ' ·') ?: '—') ?></dd></div>
        <div><dt><?= e(__('agencies.zones')) ?></dt><dd><?= e($zoneNames !== [] ? implode(', ', $zoneNames) : '—') ?></dd></div>
      </dl>
      <p class="im-note"><span class="mdi mdi-information-outline" aria-hidden="true"></span> <?= e(__('agency_profile.agent_note')) ?></p>
    </section>
    <?php endif; ?>

    <section class="card im-panel">
      <h2 class="im-panel__title"><?= e(__('agencies.accounts')) ?></h2>
      <p class="im-panel__subtitle"><?= e(__('agency_profile.accounts_hint')) ?></p>
      <div class="table-responsive">
        <table class="table im-table">
          <thead><tr><th scope="col"><?= e(__('users.singular')) ?></th><th scope="col"><?= e(__('auth.account.role')) ?></th><th scope="col"><?= e(__('auth.account.last_login')) ?></th><th scope="col"><?= e(__('cmsadmin.state')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($accounts as $account): ?>
            <tr<?= (int) $account['is_active'] === 0 ? ' class="is-muted"' : '' ?>>
              <td><span class="im-cell-main"><?= e($account['first_name'] . ' ' . $account['last_name']) ?></span><span class="im-cell-sub"><?= e($account['email']) ?></span></td>
              <td><?= e(__('auth.roles.' . $account['role'])) ?></td>
              <td class="im-cell-sub-text"><?= $account['last_login_at'] !== null ? e(substr((string) $account['last_login_at'], 0, 16)) . ' UTC' : '—' ?></td>
              <td><?= cmsadmin_partial('state-badge', ['active' => (int) $account['is_active'] === 1]) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <aside class="im-form__aside">
    <div class="im-sticky">
      <section class="card im-panel">
        <h2 class="im-panel__title"><?= e($agency['name']) ?></h2>
        <p class="im-panel__subtitle">
          <?= cmsadmin_partial('state-badge', ['label' => __('agencies.status.' . $agency['status']), 'variant' => $statusVariant[$agency['status']] ?? 'unpublished']) ?>
          <?php if ((int) $agency['is_verified'] === 1): ?><span class="im-tag"><span class="mdi mdi-check-decagram" aria-hidden="true"></span> <?= e(__('agencies.verified_badge')) ?></span><?php endif; ?>
        </p>
        <dl class="im-meta-list">
          <div><dt><?= e(__('agencies.legal_name')) ?></dt><dd><?= e($agency['legal_name'] ?? '—') ?></dd></div>
          <div><dt><?= e(__('agencies.rccm')) ?></dt><dd><?= e($agency['rccm'] ?? '—') ?></dd></div>
          <div><dt><?= e(__('geo.usage.properties_label')) ?></dt><dd class="im-num"><?= e(format_number($propertiesCount)) ?></dd></div>
          <div><dt><?= e(__('agencies.created_at')) ?></dt><dd><?= e(substr((string) $agency['created_at'], 0, 10)) ?></dd></div>
        </dl>
        <p class="im-note"><span class="mdi mdi-lock-outline" aria-hidden="true"></span> <?= e(__('agency_profile.locked_hint')) ?></p>
        <?php if ($isOwner): ?>
        <div class="d-grid gap-2">
          <button class="btn btn-primary" type="submit" form="profile-form"><?= e(__('cmsadmin.save')) ?></button>
        </div>
        <?php endif; ?>
      </section>
    </div>
  </aside>
</div>
