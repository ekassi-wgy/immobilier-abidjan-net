<?php
/**
 * Changement obligatoire du mot de passe provisoire (première connexion d'un compte créé par un administrateur).
 *
 * @var string                $csrfToken
 * @var App\Models\User       $user
 * @var array<string, string> $errors
 * @var int                   $minLength
 */
$errors ??= [];
?>
<main class="im-auth">
  <?= cmsadmin_partial('auth-aside') ?>

  <section class="im-auth__main">
    <div class="im-auth__panel">
      <img class="im-auth__logo-mobile" src="<?= e(cmsadmin_asset('images/logo-immobilier-abidjan-net.png')) ?>" alt="<?= e(site()->name ?? '') ?>" width="178" height="40">

      <h1 class="im-auth__title"><?= e(__('auth.change.title')) ?></h1>
      <p class="im-auth__subtitle"><?= e(__('auth.change.subtitle')) ?></p>

      <form method="post" action="<?= e(route('cmsadmin.password.change.submit')) ?>" class="im-auth__form" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="email" name="username" value="<?= e($user->email) ?>" autocomplete="username" hidden>

        <?= cmsadmin_partial('password-field', [
            'id' => 'change-current', 'name' => 'current_password', 'label' => __('auth.change.current'), 'autocomplete' => 'current-password',
            'error' => $errors['current_password'] ?? null, 'autofocus' => true,
        ]) ?>
        <?= cmsadmin_partial('password-field', [
            'id' => 'change-password', 'name' => 'password', 'label' => __('auth.change.new'), 'autocomplete' => 'new-password',
            'hint' => __('auth.change.hint', ['min' => $minLength]), 'error' => $errors['password'] ?? null,
        ]) ?>
        <?= cmsadmin_partial('password-field', [
            'id' => 'change-confirmation', 'name' => 'password_confirmation', 'label' => __('auth.change.confirm'), 'autocomplete' => 'new-password',
            'error' => $errors['password_confirmation'] ?? null,
        ]) ?>

        <button class="btn btn-primary btn-lg w-100 mt-2" type="submit"><?= e(__('auth.change.submit')) ?></button>
      </form>

      <form method="post" action="<?= e(route('cmsadmin.logout')) ?>" class="im-auth__footnote">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit" class="im-link im-link--button"><span class="mdi mdi-logout" aria-hidden="true"></span> <?= e(__('auth.logout')) ?></button>
      </form>
    </div>
  </section>
</main>
