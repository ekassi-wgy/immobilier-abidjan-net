<?php
/**
 * Demande de lien de réinitialisation. Le message de confirmation ne dit jamais si l'adresse existe.
 *
 * @var string      $csrfToken
 * @var string      $email
 * @var string|null $errorMessage
 * @var array       $flash
 */
$errorMessage ??= null;
$email ??= '';
$flash ??= [];
?>
<main class="im-auth">
  <?= cmsadmin_partial('auth-aside') ?>

  <section class="im-auth__main">
    <div class="im-auth__panel">
      <img class="im-auth__logo-mobile" src="<?= e(cmsadmin_asset('images/logo-immobilier-abidjan-net.png')) ?>" alt="<?= e(site()->name ?? '') ?>" width="178" height="40">

      <h1 class="im-auth__title"><?= e(__('auth.forgot.title')) ?></h1>
      <p class="im-auth__subtitle"><?= e(__('auth.forgot.subtitle')) ?></p>

      <?= cmsadmin_partial('flash', ['flash' => $flash]) ?>

      <?php if ($errorMessage !== null): ?>
      <div class="im-flash im-flash--error" role="alert">
        <span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span>
        <p><?= e($errorMessage) ?></p>
      </div>
      <?php endif; ?>

      <form method="post" action="<?= e(route('cmsadmin.password.email')) ?>" class="im-auth__form">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <div class="mb-4">
          <label class="form-label" for="forgot-email"><?= e(__('auth.email')) ?></label>
          <input class="form-control form-control-lg" id="forgot-email" name="email" type="email" autocomplete="username" required maxlength="190" autofocus value="<?= e($email) ?>">
        </div>

        <button class="btn btn-primary btn-lg w-100" type="submit"><?= e(__('auth.forgot.submit')) ?></button>
      </form>

      <p class="im-auth__footnote">
        <a class="im-link" href="<?= e(route('cmsadmin.login')) ?>"><span class="mdi mdi-arrow-left" aria-hidden="true"></span> <?= e(__('auth.back_to_login')) ?></a>
      </p>
    </div>
  </section>
</main>
