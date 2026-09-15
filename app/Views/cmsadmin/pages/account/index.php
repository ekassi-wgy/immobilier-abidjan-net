<?php
/**
 * Mon compte : identité (lecture seule en attendant la gestion des utilisateurs, lot 1.5) et changement de mot de passe.
 *
 * @var App\Models\User       $account
 * @var string                $csrfToken
 * @var array<string, string> $errors
 * @var int                   $minLength
 */
$errors ??= [];
$lastLogin = $account->lastLoginAt !== null
    ? (new IntlDateFormatter(locale(), IntlDateFormatter::LONG, IntlDateFormatter::SHORT, site()->country->timezone ?? 'UTC'))
        ->format(new DateTimeImmutable($account->lastLoginAt, new DateTimeZone('UTC')))
    : __('auth.account.never');
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('auth.account.title'),
    'subtitle' => __('auth.account.subtitle'),
    'breadcrumb' => [
        ['label' => __('auth.dashboard'), 'url' => '/'],
        ['label' => __('auth.account.title')],
    ],
]) ?>

<div class="im-account">
  <section class="card im-panel">
    <h2 class="im-panel__title"><?= e(__('auth.account.identity')) ?></h2>
    <dl class="im-meta-list">
      <div><dt><?= e(__('auth.account.name')) ?></dt><dd><?= e($account->fullName()) ?></dd></div>
      <div><dt><?= e(__('auth.email')) ?></dt><dd><?= e($account->email) ?></dd></div>
      <div><dt><?= e(__('auth.account.role')) ?></dt><dd><?= e(__('auth.roles.' . $account->role)) ?></dd></div>
      <?php if ($account->agencyName !== null): ?>
      <div><dt><?= e(__('auth.account.agency')) ?></dt><dd><?= e($account->agencyName) ?></dd></div>
      <?php endif; ?>
      <div><dt><?= e(__('auth.account.last_login')) ?></dt><dd><?= e($lastLogin) ?></dd></div>
    </dl>
    <p class="im-account__help mb-0 mt-3"><?= e(__('auth.account.profile_later')) ?></p>
  </section>

  <section class="card im-panel" id="mot-de-passe">
    <h2 class="im-panel__title"><?= e(__('auth.account.security')) ?></h2>
    <p class="im-account__help"><?= e(__('auth.account.security_help')) ?></p>

    <form method="post" action="<?= e(route('cmsadmin.account.password')) ?>" novalidate>
      <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
      <input type="email" name="username" value="<?= e($account->email) ?>" autocomplete="username" hidden>

      <?= cmsadmin_partial('password-field', [
          'id' => 'account-current', 'name' => 'current_password', 'label' => __('auth.change.current'), 'autocomplete' => 'current-password',
          'error' => $errors['current_password'] ?? null,
      ]) ?>
      <?= cmsadmin_partial('password-field', [
          'id' => 'account-password', 'name' => 'password', 'label' => __('auth.change.new'), 'autocomplete' => 'new-password',
          'hint' => __('auth.change.hint', ['min' => $minLength]), 'error' => $errors['password'] ?? null,
      ]) ?>
      <?= cmsadmin_partial('password-field', [
          'id' => 'account-confirmation', 'name' => 'password_confirmation', 'label' => __('auth.change.confirm'), 'autocomplete' => 'new-password',
          'error' => $errors['password_confirmation'] ?? null,
      ]) ?>

      <button class="btn btn-primary mt-2" type="submit"><?= e(__('auth.change.submit')) ?></button>
    </form>
  </section>
</div>
