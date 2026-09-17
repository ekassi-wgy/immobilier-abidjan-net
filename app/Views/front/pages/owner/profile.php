<?php
/**
 * Espace propriétaire : coordonnées du compte et mot de passe.
 * L'adresse email identifie le compte : sa modification passe par l'équipe Weblogy.
 *
 * @var App\Models\User $user
 * @var array           $errors
 * @var array           $passwordErrors
 * @var array           $old
 * @var array           $flash
 * @var string          $csrfToken
 */
$minLength = (int) config('auth.password_min_length', 12);
$common = ['errors' => $errors, 'old' => $old];
?>
<section class="im-section im-section--tight">
  <div class="im-container">
    <?= render_view('front/partials/owner-nav', ['user' => $user, 'current' => 'profil', 'csrfToken' => $csrfToken]) ?>
    <?= render_view('front/partials/flash', ['flash' => $flash]) ?>

    <div class="im-owner-grid">
      <section class="im-owner-panel">
        <h2 class="im-h4"><?= e(__('owner.profile.details_title')) ?></h2>
        <form class="im-form" method="post" action="<?= e(url('mon-espace/profil')) ?>" novalidate>
          <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
          <div class="im-form__pair">
            <?= render_view('front/partials/field', $common + ['name' => 'first_name', 'label' => __('owner.fields.first_name'), 'required' => true, 'maxlength' => 80, 'autocomplete' => 'given-name']) ?>
            <?= render_view('front/partials/field', $common + ['name' => 'last_name', 'label' => __('owner.fields.last_name'), 'required' => true, 'maxlength' => 80, 'autocomplete' => 'family-name']) ?>
          </div>
          <?= render_view('front/partials/field', $common + ['name' => 'phone', 'label' => __('front.contact.phone'), 'type' => 'tel', 'required' => true, 'maxlength' => 30, 'autocomplete' => 'tel']) ?>
          <div class="im-field">
            <span class="im-field__label"><?= e(__('front.contact.email')) ?></span>
            <p class="im-owner-static"><?= e($user->email) ?> <?= $user->hasVerifiedEmail() ? icon('verified', '', __('owner.profile.email_verified')) : '' ?></p>
            <span class="im-field__help"><?= e(__('owner.profile.email_help')) ?></span>
          </div>
          <button class="im-btn" type="submit"><?= e(__('owner.profile.save')) ?></button>
        </form>
      </section>

      <section class="im-owner-panel">
        <h2 class="im-h4"><?= e(__('owner.profile.password_title')) ?></h2>
        <form class="im-form" method="post" action="<?= e(url('mon-espace/profil/mot-de-passe')) ?>" novalidate>
          <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
          <input type="email" name="username" value="<?= e($user->email) ?>" autocomplete="username" hidden>
          <?= render_view('front/partials/field', ['errors' => $passwordErrors, 'name' => 'current_password', 'label' => __('owner.fields.current_password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password', 'maxlength' => 200]) ?>
          <?= render_view('front/partials/field', ['errors' => $passwordErrors, 'name' => 'password', 'label' => __('owner.fields.new_password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'maxlength' => 200, 'help' => __('owner.fields.password_help', ['min' => $minLength])]) ?>
          <?= render_view('front/partials/field', ['errors' => $passwordErrors, 'name' => 'password_confirmation', 'label' => __('owner.fields.password_confirmation'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'maxlength' => 200]) ?>
          <button class="im-btn im-btn--outline" type="submit"><?= e(__('owner.profile.password_save')) ?></button>
        </form>
      </section>
    </div>
  </div>
</section>
