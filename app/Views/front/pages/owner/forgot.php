<?php
/**
 * Mot de passe oublié (espace propriétaire). Réponse identique que l'adresse existe ou non.
 *
 * @var array  $flash
 * @var string $csrfToken
 */
?>
<section class="im-section im-section--tight">
  <div class="im-container im-auth-page">
    <div class="im-auth-page__card">
      <p class="im-eyebrow"><?= e(__('front.nav.owner_space')) ?></p>
      <h1 class="im-h3"><?= e(__('owner.forgot.title')) ?></h1>
      <p class="im-auth-page__lead"><?= e(__('owner.forgot.lead')) ?></p>
      <?= render_view('front/partials/flash', ['flash' => $flash]) ?>
      <form class="im-form" method="post" action="<?= e(url('mon-espace/mot-de-passe-oublie')) ?>" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <?= render_view('front/partials/field', ['name' => 'email', 'label' => __('front.contact.email'), 'type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
        <div class="im-honeypot" aria-hidden="true"><label for="forgot-site-web">Site web</label><input id="forgot-site-web" name="site_web" type="text" tabindex="-1" autocomplete="off"></div>
        <button class="im-btn im-btn--lg im-btn--block" type="submit"><?= e(__('owner.forgot.submit')) ?></button>
      </form>
      <p class="im-auth-page__switch"><a href="<?= e(url('mon-espace/connexion')) ?>"><?= icon('arrow-left') ?> <?= e(__('owner.forgot.back')) ?></a></p>
    </div>
  </div>
</section>
