<?php
/**
 * Connexion unique au back-office (Super Admin, Admin Pays, agences partenaires).
 * Aucune inscription publique : les comptes sont créés manuellement.
 *
 * @var string      $csrfToken
 * @var string|null $errorMessage  Message générique (ne jamais préciser si l'email existe)
 * @var string      $email         Email ressaisi après échec
 * @var bool        $locked        Trop de tentatives
 * @var array       $flash
 */
$errorMessage ??= null;
$email ??= '';
$locked ??= false;
$flash ??= [];
?>
<main class="im-auth">
  <?= cmsadmin_partial('auth-aside') ?>

  <section class="im-auth__main">
    <div class="im-auth__panel">
      <img class="im-auth__logo-mobile" src="<?= e(cmsadmin_asset('images/logo-immobilier-abidjan-net.png')) ?>" alt="<?= e(site()->name ?? '') ?>" width="178" height="40">

      <h1 class="im-auth__title"><?= e(__('auth.login.title')) ?></h1>
      <p class="im-auth__subtitle"><?= e(__('auth.login.subtitle')) ?></p>

      <?= cmsadmin_partial('flash', ['flash' => $flash]) ?>

      <?php if ($errorMessage !== null): ?>
      <div class="im-flash im-flash--error" role="alert">
        <span class="mdi <?= $locked ? 'mdi-lock-clock' : 'mdi-alert-circle-outline' ?>" aria-hidden="true"></span>
        <p><?= e($errorMessage) ?></p>
      </div>
      <?php endif; ?>

      <form method="post" action="<?= e(route('cmsadmin.login.submit')) ?>" class="im-auth__form">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <div class="mb-3">
          <label class="form-label" for="login-email"><?= e(__('auth.email')) ?></label>
          <input class="form-control form-control-lg" id="login-email" name="email" type="email" autocomplete="username" required maxlength="190"<?= $email === '' ? ' autofocus' : '' ?> value="<?= e($email) ?>">
        </div>

        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-baseline">
            <label class="form-label" for="login-password"><?= e(__('auth.password_label')) ?></label>
            <a class="im-link im-link--small" href="<?= e(route('cmsadmin.password.forgot')) ?>"><?= e(__('auth.login.forgot')) ?></a>
          </div>
          <div class="im-input-affix">
            <input class="form-control form-control-lg" id="login-password" name="password" type="password" autocomplete="current-password" required maxlength="128"<?= $email !== '' ? ' autofocus' : '' ?>>
            <button class="im-input-affix__button" type="button" data-toggle-password="#login-password" aria-label="<?= e(__('auth.show_password')) ?>" aria-pressed="false">
              <span class="mdi mdi-eye-outline" aria-hidden="true"></span>
            </button>
          </div>
        </div>

        <label class="im-check mb-4">
          <input type="checkbox" name="remember" value="1">
          <span class="im-check__box" aria-hidden="true"><span class="mdi mdi-check"></span></span>
          <span><?= e(__('auth.login.remember')) ?></span>
        </label>

        <button class="btn btn-primary btn-lg w-100" type="submit"><?= e(__('auth.login.submit')) ?></button>
      </form>

      <p class="im-auth__footnote">
        <?= e(__('auth.login.partner')) ?> <a class="im-link" href="<?= e(url('devenir-partenaire')) ?>"><?= e(__('auth.login.partner_link')) ?></a>
      </p>
    </div>
  </section>
</main>
