<?php

/**
 * Formulaire de contact général (lead `general_contact`).
 *
 * @var array  $subjects
 * @var object $site
 * @var array  $errors
 * @var array  $old
 * @var array  $flash
 * @var string $csrfToken
 */
$subjectOptions = [];
foreach ($subjects as $subject) {
    $subjectOptions[$subject] = __('front.contact.subjects.' . $subject);
}
$field = static fn (array $data): string => render_view('front/partials/field', $data);

$fields = $field(['name' => 'name', 'label' => __('front.contact.name'), 'required' => true, 'maxlength' => 150, 'autocomplete' => 'name', 'errors' => $errors, 'old' => $old])
    . '<div class="im-form__pair">'
    . $field(['name' => 'email', 'label' => __('front.contact.email'), 'type' => 'email', 'autocomplete' => 'email', 'errors' => $errors, 'old' => $old])
    . $field(['name' => 'phone', 'label' => __('front.contact.phone'), 'type' => 'tel', 'maxlength' => 30, 'autocomplete' => 'tel', 'errors' => $errors, 'old' => $old])
    . '</div>'
    . '<p class="im-field__help">' . e(__('front.contact.contact_hint')) . '</p>'
    . $field(['name' => 'sujet', 'label' => __('front.contact.subject'), 'type' => 'select', 'options' => $subjectOptions, 'errors' => $errors, 'old' => $old])
    . $field(['name' => 'message', 'label' => __('front.contact.message'), 'type' => 'textarea', 'required' => true, 'maxlength' => 2000, 'errors' => $errors, 'old' => $old]);
?>
<section class="im-section im-section--tight">
  <div class="im-container im-form-page">
    <div class="im-form-page__intro">
      <p class="im-eyebrow"><?= e(__('front.footer.contact')) ?></p>
      <h1 class="im-h2"><?= e(__('front.contact.page_title')) ?></h1>
      <p class="im-lead"><?= e(__('front.contact.page_lead')) ?></p>

      <?php if (!empty($site->contactPhone) || !empty($site->contactEmail)): ?>
      <ul class="im-form-page__contact">
        <?php if (!empty($site->contactPhone)): ?>
        <li><?= icon('phone') ?> <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $site->contactPhone)) ?>"><?= e($site->contactPhone) ?></a></li>
        <?php endif; ?>
        <?php if (!empty($site->contactWhatsapp)): ?>
        <li><?= icon('whatsapp') ?> <a href="https://wa.me/<?= e(preg_replace('/\D+/', '', $site->contactWhatsapp)) ?>" target="_blank" rel="noopener">WhatsApp</a></li>
        <?php endif; ?>
        <?php if (!empty($site->contactEmail)): ?>
        <li><?= icon('mail') ?> <a href="mailto:<?= e($site->contactEmail) ?>"><?= e($site->contactEmail) ?></a></li>
        <?php endif; ?>
      </ul>
      <?php endif; ?>
    </div>

    <div class="im-form-page__form">
      <?= render_view('front/partials/form-shell', [
          'action' => 'contact',
          'fields' => $fields,
          'submit' => __('front.contact.submit'),
          'consent' => __('front.contact.consent_general'),
          'errors' => $errors,
          'old' => $old,
          'flash' => $flash,
          'csrfToken' => $csrfToken,
      ]) ?>
    </div>
  </div>
</section>
