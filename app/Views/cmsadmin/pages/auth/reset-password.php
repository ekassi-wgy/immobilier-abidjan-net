<?php
/**
 * Choix du nouveau mot de passe depuis le lien reçu par email.
 *
 * @var string                $csrfToken
 * @var App\Models\User|null  $user     null si le lien est invalide ou expiré
 * @var string                $token
 * @var array<string, string> $errors   [champ => message]
 * @var int                   $minLength
 */
$errors ??= [];
?>
<main class="im-auth">
  <?= cmsadmin_partial('auth-aside') ?>

  <section class="im-auth__main">
    <div class="im-auth__panel">
      <?= logo_picture('cmsadmin/assets/images/logo-immobilier-abidjan-net.png', ['class' => 'im-auth__logo-mobile', 'alt' => site()->name ?? '', 'width' => 178, 'height' => 40]) ?>

      <?php if ($user === null): ?>
      <h1 class="im-auth__title"><?= e(__('auth.reset.invalid_title')) ?></h1>
      <p class="im-auth__subtitle"><?= e(__('auth.reset.invalid_text')) ?></p>
      <a class="btn btn-primary btn-lg w-100" href="<?= e(route('cmsadmin.password.forgot')) ?>"><?= e(__('auth.reset.request_new')) ?></a>
      <?php else: ?>
      <h1 class="im-auth__title"><?= e(__('auth.reset.title')) ?></h1>
      <p class="im-auth__subtitle"><?= e(__('auth.reset.subtitle', ['email' => $user->email])) ?></p>

      <form method="post" action="<?= e(route('cmsadmin.password.update', ['token' => $token])) ?>" class="im-auth__form" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="email" name="username" value="<?= e($user->email) ?>" autocomplete="username" hidden>

        <?= cmsadmin_partial('password-field', [
            'id' => 'reset-password', 'name' => 'password', 'label' => __('auth.change.new'), 'autocomplete' => 'new-password',
            'hint' => __('auth.change.hint', ['min' => $minLength]), 'error' => $errors['password'] ?? null, 'autofocus' => true,
        ]) ?>
        <?= cmsadmin_partial('password-field', [
            'id' => 'reset-confirmation', 'name' => 'password_confirmation', 'label' => __('auth.change.confirm'), 'autocomplete' => 'new-password',
            'error' => $errors['password_confirmation'] ?? null,
        ]) ?>

        <button class="btn btn-primary btn-lg w-100 mt-2" type="submit"><?= e(__('auth.reset.submit')) ?></button>
      </form>
      <?php endif; ?>

      <p class="im-auth__footnote">
        <a class="im-link" href="<?= e(route('cmsadmin.login')) ?>"><span class="mdi mdi-arrow-left" aria-hidden="true"></span> <?= e(__('auth.back_to_login')) ?></a>
      </p>
    </div>
  </section>
</main>
