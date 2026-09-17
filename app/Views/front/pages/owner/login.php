<?php
/**
 * Connexion à l'espace propriétaire (particuliers). Le back-office a sa propre connexion.
 *
 * @var array  $errors ['message'?]
 * @var array  $old
 * @var array  $flash
 * @var string $csrfToken
 */
?>
<section class="im-section im-section--tight">
  <div class="im-container im-auth-page">
    <div class="im-auth-page__card">
      <p class="im-eyebrow"><?= e(__('front.nav.owner_space')) ?></p>
      <h1 class="im-h3"><?= e(__('owner.login.title')) ?></h1>
      <p class="im-auth-page__lead"><?= e(__('owner.login.lead')) ?></p>

      <?= render_view('front/partials/flash', ['flash' => $flash]) ?>
      <?php if (isset($errors['message'])): ?><p class="im-alert im-alert--error" role="alert"><?= icon('info') ?> <?= e($errors['message']) ?></p><?php endif; ?>

      <form class="im-form" method="post" action="<?= e(url('mon-espace/connexion')) ?>" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <?= render_view('front/partials/field', ['name' => 'email', 'label' => __('front.contact.email'), 'type' => 'email', 'required' => true, 'autocomplete' => 'email', 'old' => $old]) ?>
        <?= render_view('front/partials/field', ['name' => 'password', 'label' => __('owner.fields.password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password', 'maxlength' => 200]) ?>
        <a class="im-auth-page__forgot" href="<?= e(url('mon-espace/mot-de-passe-oublie')) ?>"><?= e(__('owner.login.forgot')) ?></a>
        <button class="im-btn im-btn--lg im-btn--block" type="submit"><?= e(__('owner.login.submit')) ?></button>
      </form>

      <p class="im-auth-page__switch"><?= e(__('owner.login.no_account')) ?> <a href="<?= e(url('mon-espace/inscription')) ?>"><?= e(__('owner.login.register')) ?></a></p>
    </div>
  </div>
</section>
