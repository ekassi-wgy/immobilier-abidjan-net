<?php

/**
 * Page Contact (lead `general_contact`).
 *
 * Trois temps : les coordonnées directes (appeler, WhatsApp, écrire, s'y rendre), le formulaire,
 * puis la carte. Chaque coordonnée n'apparaît que si elle est renseignée dans Pays & sites ;
 * la carte n'apparaît que si la latitude et la longitude le sont.
 *
 * Tout fonctionne sans JavaScript : l'objet se choisit par boutons radio, et la carte, seule
 * partie scriptée, laisse de toute façon l'adresse et le lien d'itinéraire visibles.
 *
 * @var list<string> $subjects
 * @var App\Models\Site $site
 * @var array  $errors
 * @var array  $old
 * @var array  $flash
 * @var string $csrfToken
 */
$field = static fn (array $data): string => render_view('front/partials/field', $data);

$phoneHref = $site->contactPhone !== null ? 'tel:' . preg_replace('/[^\d+]/', '', $site->contactPhone) : null;
$whatsappHref = $site->contactWhatsapp !== null ? 'https://wa.me/' . preg_replace('/\D+/', '', $site->contactWhatsapp) : null;
$directions = $site->hasLocation()
    ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($site->latitude . ',' . $site->longitude)
    : ($site->address !== null ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($site->address) : null);

$channels = array_values(array_filter([
    $phoneHref !== null ? ['icon' => 'phone', 'label' => __('front.contact.page.phone'), 'value' => $site->contactPhone, 'href' => $phoneHref, 'action' => __('front.contact.page.call'), 'external' => false] : null,
    $whatsappHref !== null ? ['icon' => 'whatsapp', 'label' => 'WhatsApp', 'value' => $site->contactWhatsapp, 'href' => $whatsappHref, 'action' => __('front.contact.page.chat'), 'external' => true] : null,
    $site->contactEmail !== null ? ['icon' => 'mail', 'label' => __('front.contact.page.email'), 'value' => $site->contactEmail, 'href' => 'mailto:' . $site->contactEmail, 'action' => __('front.contact.page.write'), 'external' => false] : null,
    $site->address !== null ? ['icon' => 'pin', 'label' => __('front.contact.page.address'), 'value' => $site->address, 'href' => $directions, 'action' => __('front.contact.page.directions'), 'external' => true] : null,
]));

$selectedSubject = (string) ($old['sujet'] ?? '');
$subjectChoices = '<fieldset class="im-field im-choices">'
    . '<legend class="im-field__label">' . e(__('front.contact.subject')) . '</legend>'
    . '<div class="im-choices__list">';
foreach ($subjects as $subject) {
    $subjectChoices .= '<label class="im-choices__item">'
        . '<input type="radio" name="sujet" value="' . e($subject) . '"' . ($selectedSubject === $subject ? ' checked' : '') . '>'
        . '<span class="im-chip">' . e(__('front.contact.subjects.' . $subject)) . '</span>'
        . '</label>';
}
$subjectChoices .= '</div>'
    . (isset($errors['sujet']) ? '<span class="im-field__error">' . e($errors['sujet']) . '</span>' : '')
    . '</fieldset>';

$fields = $subjectChoices
    . $field(['name' => 'name', 'label' => __('front.contact.name'), 'required' => true, 'maxlength' => 150, 'autocomplete' => 'name', 'errors' => $errors, 'old' => $old])
    . '<div class="im-form__pair">'
    . $field(['name' => 'email', 'label' => __('front.contact.email'), 'type' => 'email', 'autocomplete' => 'email', 'errors' => $errors, 'old' => $old])
    . $field(['name' => 'phone', 'label' => __('front.contact.phone'), 'type' => 'tel', 'maxlength' => 30, 'autocomplete' => 'tel', 'errors' => $errors, 'old' => $old])
    . '</div>'
    . '<p class="im-field__help">' . e(__('front.contact.contact_hint')) . '</p>'
    . $field(['name' => 'message', 'label' => __('front.contact.message'), 'type' => 'textarea', 'required' => true, 'maxlength' => 2000, 'placeholder' => __('front.contact.page.message_placeholder'), 'errors' => $errors, 'old' => $old]);
?>
<section class="im-contact-hero">
  <div class="im-container">
    <p class="im-eyebrow"><?= e(__('front.footer.contact')) ?></p>
    <h1 class="im-h1 im-contact-hero__title"><?= e(__('front.contact.page.title_start')) ?> <span><?= e(__('front.contact.page.title_end')) ?></span></h1>
    <p class="im-lead im-contact-hero__lead"><?= e(__('front.contact.page_lead')) ?></p>
  </div>
</section>

<section class="im-section im-section--tight im-contact-page">
  <div class="im-container im-contact-page__grid">
    <?php if ($channels !== []): ?>
    <aside class="im-contact-page__channels" aria-labelledby="contact-channels-title">
      <h2 class="im-h4" id="contact-channels-title"><?= e(__('front.contact.page.channels_title')) ?></h2>
      <p class="im-contact-page__hint"><?= e(__('front.contact.page.channels_lead')) ?></p>

      <ul class="im-channels">
        <?php foreach ($channels as $channel): ?>
        <li class="im-channel<?= $channel['icon'] === 'pin' ? ' im-channel--stacked' : '' ?>">
          <span class="im-channel__icon im-channel__icon--<?= e($channel['icon']) ?>"><?= icon($channel['icon']) ?></span>
          <span class="im-channel__body">
            <span class="im-channel__label"><?= e($channel['label']) ?></span>
            <span class="im-channel__value"><?= e((string) $channel['value']) ?></span>
          </span>
          <?php if ($channel['href'] !== null): ?>
          <a class="im-channel__action" href="<?= e($channel['href']) ?>"<?= $channel['external'] ? ' target="_blank" rel="noopener"' : '' ?>>
            <?= e($channel['action']) ?> <?= icon($channel['external'] ? 'arrow-up-right' : 'arrow-right') ?>
            <span class="visually-hidden"> — <?= e($channel['label']) ?></span>
          </a>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>

      <p class="im-contact-page__partner">
        <?= e(__('front.contact.page.partner_question')) ?>
        <a href="<?= e(url('devenir-partenaire')) ?>"><?= e(__('front.contact.page.partner_link')) ?> <?= icon('arrow-right') ?></a>
      </p>
    </aside>
    <?php endif; ?>

    <div class="im-contact-page__form">
      <h2 class="im-h3"><?= e(__('front.contact.page.form_title')) ?></h2>
      <p class="im-contact-page__hint"><?= e(__('front.contact.page.form_lead')) ?></p>
      <?= render_view('front/partials/form-shell', [
          'action' => 'contact',
          'fields' => $fields,
          'submit' => __('front.contact.page.submit'),
          'consent' => __('front.contact.consent_general'),
          'errors' => $errors,
          'old' => $old,
          'flash' => $flash,
          'csrfToken' => $csrfToken,
      ]) ?>
    </div>
  </div>
</section>

<?php if ($site->hasLocation()): ?>
<section class="im-contact-map" aria-labelledby="contact-map-title">
  <div class="im-container">
    <div class="im-contact-map__frame">
      <div class="im-contact-map__canvas" id="im-contact-map" role="region" aria-label="<?= e(__('front.contact.page.map_label')) ?>"
           data-point="<?= e(json_encode(['lat' => $site->latitude, 'lng' => $site->longitude])) ?>"
           data-map-images="<?= e(asset('vendors/leaflet/images/')) ?>"></div>

      <div class="im-contact-map__card">
        <p class="im-eyebrow"><?= e(__('front.contact.page.visit_eyebrow')) ?></p>
        <h2 class="im-h4" id="contact-map-title"><?= e(__('front.contact.page.visit_title')) ?></h2>
        <?php if ($site->address !== null): ?><p class="im-contact-map__address"><?= e($site->address) ?></p><?php endif; ?>
        <a class="im-btn" href="<?= e((string) $directions) ?>" target="_blank" rel="noopener"><?= icon('pin') ?> <?= e(__('front.contact.page.directions')) ?></a>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>
