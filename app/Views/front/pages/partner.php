<?php

/**
 * Demande « Devenir agence partenaire » → table `partner_requests`.
 * Aucun compte n'est créé ici : l'équipe crée l'agence depuis la demande (lot 1.5).
 *
 * @var array  $cities   [id => nom]
 * @var array  $communes [id => nom]
 * @var array  $volumes
 * @var array  $errors
 * @var array  $old
 * @var array  $flash
 * @var string $csrfToken
 */
$volumeOptions = [];
foreach ($volumes as $volume) {
    $volumeOptions[$volume] = __('front.partner.volume', ['count' => $volume]);
}
$field = static fn (array $data): string => render_view('front/partials/field', $data);
$common = ['errors' => $errors, 'old' => $old];

$fields = $field($common + ['name' => 'agency_name', 'label' => __('front.partner.agency_name'), 'required' => true, 'maxlength' => 150, 'autocomplete' => 'organization'])
    . '<div class="im-form__pair">'
    . $field($common + ['name' => 'name', 'label' => __('front.partner.contact_name'), 'required' => true, 'maxlength' => 150, 'autocomplete' => 'name'])
    . $field($common + ['name' => 'rccm', 'label' => __('front.partner.rccm'), 'maxlength' => 60, 'help' => __('front.partner.rccm_help')])
    . '</div>'
    . '<div class="im-form__pair">'
    . $field($common + ['name' => 'email', 'label' => __('front.contact.email'), 'type' => 'email', 'required' => true, 'autocomplete' => 'email'])
    . $field($common + ['name' => 'phone', 'label' => __('front.contact.phone'), 'type' => 'tel', 'required' => true, 'maxlength' => 30, 'autocomplete' => 'tel'])
    . '</div>'
    . '<div class="im-form__pair">'
    . $field($common + ['name' => 'city_id', 'label' => __('front.filters.city'), 'type' => 'select', 'options' => $cities, 'empty' => __('front.partner.city_empty')])
    . $field($common + ['name' => 'commune_id', 'label' => __('front.filters.commune'), 'type' => 'select', 'options' => $communes, 'empty' => __('front.partner.commune_empty')])
    . '</div>'
    . $field($common + ['name' => 'listings_estimate', 'label' => __('front.partner.volume_label'), 'type' => 'select', 'options' => $volumeOptions, 'empty' => __('front.filters.any')])
    . $field($common + ['name' => 'message', 'label' => __('front.partner.message'), 'type' => 'textarea', 'maxlength' => 2000, 'placeholder' => __('front.partner.message_placeholder')]);
?>
<section class="im-section im-section--tight">
  <div class="im-container im-form-page">
    <div class="im-form-page__intro">
      <p class="im-eyebrow"><?= e(__('front.nav.become_partner')) ?></p>
      <h1 class="im-h2"><?= e(__('front.partner.page_title')) ?></h1>
      <p class="im-lead"><?= e(__('front.partner.page_lead')) ?></p>

      <ol class="im-steps">
        <?php foreach (['step_1', 'step_2', 'step_3'] as $index => $key): ?>
        <li><span class="im-steps__number im-num"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span><?= e(__('front.partner.' . $key)) ?></li>
        <?php endforeach; ?>
      </ol>
    </div>

    <div class="im-form-page__form">
      <?= render_view('front/partials/form-shell', [
          'action' => 'devenir-partenaire',
          'fields' => $fields,
          'submit' => __('front.partner.submit'),
          'consent' => __('front.partner.consent'),
          'errors' => $errors,
          'old' => $old,
          'flash' => $flash,
          'csrfToken' => $csrfToken,
      ]) ?>
    </div>
  </div>
</section>
