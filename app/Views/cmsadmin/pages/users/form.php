<?php
/**
 * Création / modification d'un compte : utilisateur interne ou compte d'agence ($agency renseigné).
 *
 * @var array<string,mixed>|null $account
 * @var array<string,mixed>      $values
 * @var array<string,string>     $errors
 * @var array<string,mixed>|null $agency
 * @var array<string,string>     $roles
 * @var array<int,string>        $countries  Pays actifs (utilisateurs internes)
 * @var string                   $action
 * @var string                   $backUrl
 * @var bool                     $isSelf
 */
$isEdit = $account !== null;
$value = static fn (string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
$field = static fn (array $options): string => cmsadmin_partial('field', $options + ['class' => '', 'error' => $errors[$options['name']] ?? null, 'value' => $value($options['name'])]);
$name = $isEdit ? $account['first_name'] . ' ' . $account['last_name'] : '';
$pending = $isEdit && (int) $account['must_change_password'] === 1 && $account['last_login_at'] === null;
$breadcrumb = $agency !== null
    ? [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('agencies.title'), 'url' => 'agences'], ['label' => $agency['name'], 'url' => 'agences/' . $agency['id'] . '/modifier'], ['label' => $isEdit ? $name : __('users.create_agency_account')]]
    : [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('users.title'), 'url' => 'utilisateurs'], ['label' => $isEdit ? $name : __('users.create')]];
?>
<?= cmsadmin_partial('page-header', [
    'title' => $isEdit ? $name : __($agency !== null ? 'users.create_agency_account' : 'users.create'),
    'subtitle' => $agency !== null ? __('users.agency_subtitle', ['agency' => $agency['name']]) : __('users.form_subtitle'),
    'breadcrumb' => $breadcrumb,
]) ?>

<?php if ($errors !== []): ?>
<div class="im-flash im-flash--error" role="alert">
  <span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span>
  <p><?= e(__('cmsadmin.form_errors', ['count' => count($errors)])) ?></p>
</div>
<?php endif; ?>

<form class="im-form" method="post" action="<?= e($action) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="im-form__layout">
    <div class="im-form__main">
      <section class="card im-panel im-form-section">
        <header class="im-form-section__head"><span class="im-form-section__index">01</span><h2 class="im-panel__title"><?= e(__('auth.account.identity')) ?></h2></header>
        <div class="row g-3">
          <div class="col-md-6"><?= $field(['name' => 'first_name', 'label' => __('users.first_name'), 'required' => true, 'attributes' => ['maxlength' => 80, 'autocomplete' => 'off']]) ?></div>
          <div class="col-md-6"><?= $field(['name' => 'last_name', 'label' => __('users.last_name'), 'required' => true, 'attributes' => ['maxlength' => 80, 'autocomplete' => 'off']]) ?></div>
          <div class="col-md-6"><?= $field(['name' => 'email', 'type' => 'email', 'label' => __('users.login_email'), 'required' => true, 'hint' => $isEdit ? __('users.email_change_hint') : __('users.email_hint'), 'attributes' => ['maxlength' => 190, 'autocomplete' => 'off', 'spellcheck' => 'false']]) ?></div>
          <div class="col-md-6"><?= $field(['name' => 'job_title', 'label' => __('users.job_title'), 'optional' => true, 'attributes' => ['maxlength' => 100]]) ?></div>
          <div class="col-md-6"><?= $field(['name' => 'phone', 'type' => 'tel', 'label' => __('sites.phone'), 'optional' => true, 'hint' => $agency !== null ? __('users.phone_hint_agency') : null, 'attributes' => ['maxlength' => 30]]) ?></div>
          <?php if ($agency !== null): ?>
          <div class="col-md-6"><?= $field(['name' => 'whatsapp', 'type' => 'tel', 'label' => 'WhatsApp', 'optional' => true, 'attributes' => ['maxlength' => 30]]) ?></div>
          <?php endif; ?>
        </div>
      </section>

      <section class="card im-panel im-form-section">
        <header class="im-form-section__head"><span class="im-form-section__index">02</span><h2 class="im-panel__title"><?= e(__('users.access')) ?></h2></header>
        <?php if ($isSelf): ?>
        <p class="im-note mb-0"><span class="mdi mdi-lock-outline" aria-hidden="true"></span> <?= e(__('users.self_role_locked', ['role' => __('auth.roles.' . $account['role'])])) ?></p>
        <?php else: ?>
        <fieldset class="mb-3">
          <legend class="form-label"><?= e(__('auth.account.role')) ?></legend>
          <div class="im-role-cards">
            <?php foreach ($roles as $code => $label): ?>
            <label class="im-role-card">
              <input type="radio" name="role" value="<?= e($code) ?>"<?= $value('role') === $code ? ' checked' : '' ?><?= $agency === null ? ' data-toggle-country' : '' ?>>
              <span class="im-role-card__body">
                <span class="im-role-card__title"><?= e($label) ?></span>
                <span class="im-role-card__text"><?= e(__('users.role_help.' . $code)) ?></span>
              </span>
            </label>
            <?php endforeach; ?>
          </div>
          <?php if (isset($errors['role'])): ?><p class="invalid-feedback d-block"><?= e($errors['role']) ?></p><?php endif; ?>
        </fieldset>
        <?php if ($agency === null): ?>
        <div data-country-field<?= $value('role') === 'super_admin' ? ' hidden' : '' ?>>
          <?= $field(['name' => 'country_id', 'type' => 'select', 'label' => __('users.country'), 'options' => $countries, 'placeholder' => __('cmsadmin.choose'), 'hint' => __('users.country_hint'), 'class' => 'mb-0']) ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </section>
    </div>

    <aside class="im-form__aside">
      <div class="im-sticky">
        <section class="card im-panel">
          <h2 class="im-panel__title"><?= e(__($isEdit ? 'users.account_status' : 'users.invitation')) ?></h2>
          <?php if ($isEdit): ?>
          <dl class="im-meta-list">
            <div><dt><?= e(__('cmsadmin.state')) ?></dt><dd><?= cmsadmin_partial('state-badge', (int) $account['is_active'] === 0 ? ['active' => false] : ($pending ? ['label' => __('users.invitation_pending'), 'variant' => 'pending'] : ['active' => true])) ?></dd></div>
            <div><dt><?= e(__('auth.account.last_login')) ?></dt><dd><?= $account['last_login_at'] !== null ? e(substr((string) $account['last_login_at'], 0, 16)) . ' UTC' : '—' ?></dd></div>
            <div><dt><?= e(__('users.created_at')) ?></dt><dd><?= e(substr((string) $account['created_at'], 0, 10)) ?></dd></div>
          </dl>
          <?php else: ?>
          <p class="im-note mt-3"><span class="mdi mdi-email-fast-outline" aria-hidden="true"></span> <?= e(__('users.invitation_hint', ['hours' => (int) config('auth.invite_expires', 72)])) ?></p>
          <?php endif; ?>
          <div class="d-grid gap-2">
            <button class="btn btn-primary" type="submit"><?= e(__($isEdit ? 'cmsadmin.save' : 'users.create_submit')) ?></button>
            <a class="btn im-btn-ghost" href="<?= e($backUrl) ?>"><?= e(__('cmsadmin.cancel')) ?></a>
          </div>
        </section>
      </div>
    </aside>
  </div>
</form>
