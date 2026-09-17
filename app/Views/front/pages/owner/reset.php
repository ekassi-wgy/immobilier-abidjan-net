<?php
/**
 * Nouveau mot de passe à partir d'un lien reçu par email (espace propriétaire).
 *
 * @var array  $errors
 * @var array  $old    ['token', 'valid']
 * @var string $csrfToken
 */
$minLength = (int) config('auth.password_min_length', 12);
?>
<section class="im-section im-section--tight">
  <div class="im-container im-auth-page">
    <div class="im-auth-page__card">
      <p class="im-eyebrow"><?= e(__('front.nav.owner_space')) ?></p>
      <?php if (!$old['valid']): ?>
      <h1 class="im-h3"><?= e(__('owner.reset.invalid_title')) ?></h1>
      <p class="im-auth-page__lead"><?= e(__('owner.reset.invalid_text')) ?></p>
      <a class="im-btn im-btn--block" href="<?= e(url('mon-espace/mot-de-passe-oublie')) ?>"><?= e(__('owner.reset.new_link')) ?></a>
      <?php else: ?>
      <h1 class="im-h3"><?= e(__('owner.reset.title')) ?></h1>
      <form class="im-form" method="post" action="<?= e(url('mon-espace/mot-de-passe/' . $old['token'])) ?>" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <?= render_view('front/partials/field', ['errors' => $errors, 'name' => 'password', 'label' => __('owner.fields.password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'maxlength' => 200, 'help' => __('owner.fields.password_help', ['min' => $minLength])]) ?>
        <?= render_view('front/partials/field', ['errors' => $errors, 'name' => 'password_confirmation', 'label' => __('owner.fields.password_confirmation'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'maxlength' => 200]) ?>
        <button class="im-btn im-btn--lg im-btn--block" type="submit"><?= e(__('owner.reset.submit')) ?></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</section>
