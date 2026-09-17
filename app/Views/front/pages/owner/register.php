<?php
/**
 * Création d'un compte particulier. Le compte sert à confier un bien à Weblogy et à en suivre le
 * traitement ; il ne permet pas de publier une annonce.
 *
 * @var array  $errors
 * @var array  $old
 * @var array  $flash
 * @var string $csrfToken
 */
$common = ['errors' => $errors, 'old' => $old];
$field = static fn (array $data): string => render_view('front/partials/field', $data);
$minLength = (int) config('auth.password_min_length', 12);
?>
<section class="im-section im-section--tight">
  <div class="im-container im-form-page">
    <div class="im-form-page__intro">
      <p class="im-eyebrow"><?= e(__('front.nav.owner_space')) ?></p>
      <h1 class="im-h2"><?= e(__('owner.register.title')) ?></h1>
      <p class="im-lead"><?= e(__('owner.register.lead', ['site' => site()->name ?? ''])) ?></p>
      <ul class="im-checklist">
        <?php foreach (['benefit_1', 'benefit_2', 'benefit_3'] as $key): ?>
        <li><?= icon('check') ?> <?= e(__('owner.register.' . $key)) ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="im-form-page__aside-note"><?= icon('info') ?> <?= e(__('owner.register.have_account')) ?> <a href="<?= e(url('mon-espace/connexion')) ?>"><?= e(__('owner.login.submit')) ?></a></p>
    </div>

    <div class="im-form-page__form">
      <?= render_view('front/partials/flash', ['flash' => $flash]) ?>
      <?php if (isset($errors['message'])): ?><p class="im-alert im-alert--error" role="alert"><?= icon('info') ?> <?= e($errors['message']) ?></p><?php endif; ?>

      <form class="im-form" method="post" action="<?= e(url('mon-espace/inscription')) ?>" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <div class="im-form__pair">
          <?= $field($common + ['name' => 'first_name', 'label' => __('owner.fields.first_name'), 'required' => true, 'maxlength' => 80, 'autocomplete' => 'given-name']) ?>
          <?= $field($common + ['name' => 'last_name', 'label' => __('owner.fields.last_name'), 'required' => true, 'maxlength' => 80, 'autocomplete' => 'family-name']) ?>
        </div>
        <div class="im-form__pair">
          <?= $field($common + ['name' => 'email', 'label' => __('front.contact.email'), 'type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
          <?= $field($common + ['name' => 'phone', 'label' => __('front.contact.phone'), 'type' => 'tel', 'required' => true, 'maxlength' => 30, 'autocomplete' => 'tel', 'placeholder' => '+225 07 00 00 00 00']) ?>
        </div>
        <div class="im-form__pair">
          <?= $field(['errors' => $errors, 'name' => 'password', 'label' => __('owner.fields.password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'maxlength' => 200, 'help' => __('owner.fields.password_help', ['min' => $minLength])]) ?>
          <?= $field(['errors' => $errors, 'name' => 'password_confirmation', 'label' => __('owner.fields.password_confirmation'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'maxlength' => 200]) ?>
        </div>

        <label class="im-check im-form__consent">
          <input type="checkbox" name="consent" value="1"<?= !empty($old['consent']) ? ' checked' : '' ?> required>
          <span class="im-check__box" aria-hidden="true"><?= icon('check') ?></span>
          <span><?= e(__('owner.register.consent', ['site' => site()->name ?? ''])) ?></span>
        </label>
        <?php if (isset($errors['consent'])): ?><span class="im-field__error"><?= e($errors['consent']) ?></span><?php endif; ?>

        <div class="im-honeypot" aria-hidden="true">
          <label for="owner-site-web">Site web</label>
          <input id="owner-site-web" name="site_web" type="text" tabindex="-1" autocomplete="off">
        </div>

        <button class="im-btn im-btn--lg" type="submit"><?= e(__('owner.register.submit')) ?></button>
      </form>
    </div>
  </div>
</section>
