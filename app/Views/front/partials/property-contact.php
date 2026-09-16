<?php

/**
 * Demande de contact d'une annonce : crée un lead `property_contact`.
 * Aucun compte visiteur — seuls le nom, un moyen de recontact et le message sont demandés.
 *
 * @var array  $property
 * @var string $csrfToken
 * @var array  $errors    [champ => message]
 * @var array  $old       Valeurs ressaisies après une erreur
 * @var array  $flash
 */
$value = static fn (string $field): string => (string) ($old[$field] ?? '');
$whatsappUrl = !empty($property['whatsapp'])
    ? 'https://wa.me/' . preg_replace('/\D+/', '', $property['whatsapp'])
        . '?text=' . rawurlencode(__('front.card.whatsapp_message', ['reference' => $property['reference'], 'title' => $property['title']]))
    : null;
?>
<section class="im-contact" id="contact">
  <div class="im-contact__head">
    <h2 class="im-h3"><?= e(__('front.contact.title')) ?></h2>
    <p class="im-small im-muted"><?= e(__('front.contact.lead')) ?></p>
  </div>

  <?php if (!empty($property['phone']) || $whatsappUrl !== null): ?>
  <div class="im-contact__direct">
    <?php if (!empty($property['phone'])): ?>
    <a class="im-btn im-btn--outline" href="tel:<?= e(preg_replace('/[^\d+]/', '', $property['phone'])) ?>">
      <?= icon('phone') ?> <?= e($property['phone']) ?>
    </a>
    <?php endif; ?>
    <?php if ($whatsappUrl !== null): ?>
    <a class="im-btn im-btn--whatsapp" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener">
      <?= icon('whatsapp') ?> <?= e(__('front.contact.whatsapp')) ?>
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php foreach ($flash as $message): ?>
  <p class="im-alert im-alert--<?= e($message['type'] === 'success' ? 'success' : 'info') ?>" role="status">
    <?= icon('check') ?> <?= e($message['message']) ?>
  </p>
  <?php endforeach; ?>

  <form class="im-contact__form" method="post" action="<?= e(url($property['url'] . '/contact')) ?>" novalidate>
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <?php if (isset($errors['message']) && !isset($errors['name'])): ?>
    <p class="im-alert im-alert--error" role="alert"><?= icon('info') ?> <?= e($errors['message']) ?></p>
    <?php endif; ?>

    <div class="im-field">
      <label class="im-field__label" for="contact-name"><?= e(__('front.contact.name')) ?></label>
      <input class="im-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="contact-name" name="name" type="text"
             value="<?= e($value('name')) ?>" maxlength="150" autocomplete="name" required>
      <?php if (isset($errors['name'])): ?><span class="im-field__error"><?= e($errors['name']) ?></span><?php endif; ?>
    </div>

    <div class="im-contact__pair">
      <div class="im-field">
        <label class="im-field__label" for="contact-email"><?= e(__('front.contact.email')) ?></label>
        <input class="im-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="contact-email" name="email" type="email"
               value="<?= e($value('email')) ?>" maxlength="190" autocomplete="email">
        <?php if (isset($errors['email'])): ?><span class="im-field__error"><?= e($errors['email']) ?></span><?php endif; ?>
      </div>
      <div class="im-field">
        <label class="im-field__label" for="contact-phone"><?= e(__('front.contact.phone')) ?></label>
        <input class="im-control<?= isset($errors['phone']) ? ' is-invalid' : '' ?>" id="contact-phone" name="phone" type="tel"
               value="<?= e($value('phone')) ?>" maxlength="30" autocomplete="tel">
        <?php if (isset($errors['phone'])): ?><span class="im-field__error"><?= e($errors['phone']) ?></span><?php endif; ?>
      </div>
    </div>
    <p class="im-field__help"><?= e(__('front.contact.contact_hint')) ?></p>

    <div class="im-field">
      <label class="im-field__label" for="contact-message"><?= e(__('front.contact.message')) ?></label>
      <textarea class="im-control<?= isset($errors['message']) ? ' is-invalid' : '' ?>" id="contact-message" name="message"
                rows="5" maxlength="2000" required placeholder="<?= e(__('front.contact.message_placeholder')) ?>"><?= e($value('message') !== '' ? $value('message') : __('front.card.whatsapp_message', ['reference' => $property['reference'], 'title' => $property['title']])) ?></textarea>
    </div>

    <label class="im-check im-contact__consent">
      <input type="checkbox" name="consent" value="1"<?= $value('consent') !== '' ? ' checked' : '' ?> required>
      <span class="im-check__box" aria-hidden="true"><?= icon('check') ?></span>
      <span><?= e(__('front.contact.consent')) ?></span>
    </label>
    <?php if (isset($errors['consent'])): ?><span class="im-field__error"><?= e($errors['consent']) ?></span><?php endif; ?>

    <!-- Pot de miel : masqué aux visiteurs, rempli par les robots. -->
    <div class="im-honeypot" aria-hidden="true">
      <label for="contact-site-web">Site web</label>
      <input id="contact-site-web" name="site_web" type="text" tabindex="-1" autocomplete="off">
    </div>

    <button class="im-btn im-btn--block" type="submit"><?= icon('mail') ?> <?= e(__('front.contact.submit')) ?></button>
  </form>
</section>
