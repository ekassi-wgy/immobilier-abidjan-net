<?php

/**
 * Enveloppe commune aux formulaires publics autonomes : titre, message de confirmation,
 * jeton CSRF, pot de miel, consentement et bouton d'envoi. Le contenu propre au formulaire
 * est passé en HTML déjà rendu ($fields).
 *
 * @var string $action     Chemin de destination (POST)
 * @var string $title
 * @var string $lead
 * @var string $submit
 * @var string $consent
 * @var string $fields     Champs déjà rendus
 * @var array  $errors
 * @var array  $old
 * @var array  $flash
 * @var string $csrfToken
 * @var bool   $multipart  Envoi de fichiers (enctype multipart/form-data)
 */
$multipart ??= false;
?>
<?php foreach ($flash as $message): ?>
<p class="im-alert im-alert--<?= e($message['type'] === 'success' ? 'success' : 'info') ?>" role="status">
  <?= icon('check') ?> <?= e($message['message']) ?>
</p>
<?php endforeach; ?>

<?php if (isset($errors['message']) && !isset($errors['name'])): ?>
<p class="im-alert im-alert--error" role="alert"><?= icon('info') ?> <?= e($errors['message']) ?></p>
<?php endif; ?>

<form class="im-form" method="post" action="<?= e(url($action)) ?>"<?= $multipart ? ' enctype="multipart/form-data"' : '' ?> novalidate>
  <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

  <?= $fields ?>

  <label class="im-check im-form__consent">
    <input type="checkbox" name="consent" value="1"<?= !empty($old['consent']) ? ' checked' : '' ?> required>
    <span class="im-check__box" aria-hidden="true"><?= icon('check') ?></span>
    <span><?= e($consent) ?></span>
  </label>
  <?php if (isset($errors['consent'])): ?><span class="im-field__error"><?= e($errors['consent']) ?></span><?php endif; ?>

  <!-- Pot de miel : masqué aux visiteurs, rempli par les robots. -->
  <div class="im-honeypot" aria-hidden="true">
    <label for="<?= e($action) ?>-site-web">Site web</label>
    <input id="<?= e($action) ?>-site-web" name="site_web" type="text" tabindex="-1" autocomplete="off">
  </div>

  <button class="im-btn im-btn--lg" type="submit"><?= icon('mail') ?> <?= e($submit) ?></button>
</form>
