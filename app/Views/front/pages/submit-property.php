<?php

/**
 * Formulaire « Déposer un bien » : le visiteur confie un bien à la plateforme.
 * Il crée une demande de contact (`property_submission`), jamais une annonce ni un compte.
 *
 * @var array  $transactions [['slug','label']]
 * @var array  $types        [slug => libellé]
 * @var array  $groups       [famille => [slug => libellé]]
 * @var array  $errors
 * @var array  $old
 * @var array  $flash
 * @var string $csrfToken
 */
$transactionOptions = [];
foreach ($transactions as $transaction) {
    $transactionOptions[$transaction['slug']] = $transaction['label'];
}
$currency = site()?->country->currencySymbol ?? '';
$field = static fn (array $data): string => render_view('front/partials/field', $data);
$common = ['errors' => $errors, 'old' => $old];

$fields = '<div class="im-form__pair">'
    . $field($common + ['name' => 'transaction', 'label' => __('front.submit.transaction'), 'type' => 'select', 'options' => $transactionOptions, 'required' => true, 'empty' => __('front.submit.choose')])
    . $field($common + ['name' => 'type', 'label' => __('front.filters.type'), 'type' => 'select', 'options' => $groups, 'grouped' => true, 'required' => true, 'empty' => __('front.submit.choose')])
    . '</div>'
    . '<div class="im-form__pair">'
    . $field($common + ['name' => 'commune', 'label' => __('front.submit.place'), 'maxlength' => 120, 'placeholder' => __('front.search.location_placeholder')])
    . $field($common + ['name' => 'surface', 'label' => __('front.submit.area'), 'type' => 'number', 'maxlength' => 10])
    . '</div>'
    . $field($common + ['name' => 'prix', 'label' => __('front.submit.price', ['currency' => $currency]), 'type' => 'number', 'maxlength' => 15, 'help' => __('front.submit.price_help')])
    . '<div class="im-form__pair">'
    . $field($common + ['name' => 'name', 'label' => __('front.contact.name'), 'required' => true, 'maxlength' => 150, 'autocomplete' => 'name'])
    . $field($common + ['name' => 'phone', 'label' => __('front.contact.phone'), 'type' => 'tel', 'required' => true, 'maxlength' => 30, 'autocomplete' => 'tel'])
    . '</div>'
    . $field($common + ['name' => 'email', 'label' => __('front.contact.email'), 'type' => 'email', 'autocomplete' => 'email'])
    . $field($common + ['name' => 'message', 'label' => __('front.submit.description'), 'type' => 'textarea', 'required' => true, 'maxlength' => 2000, 'placeholder' => __('front.submit.description_placeholder')]);
?>
<section class="im-section im-section--tight">
  <div class="im-container im-form-page">
    <div class="im-form-page__intro">
      <p class="im-eyebrow"><?= e(__('front.nav.submit_property')) ?></p>
      <h1 class="im-h2"><?= e(__('front.submit.page_title')) ?></h1>
      <p class="im-lead"><?= e(__('front.submit.page_lead')) ?></p>

      <ol class="im-steps">
        <?php foreach (['step_1', 'step_2', 'step_3'] as $index => $key): ?>
        <li><span class="im-steps__number im-num"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span><?= e(__('front.submit.' . $key)) ?></li>
        <?php endforeach; ?>
      </ol>
    </div>

    <div class="im-form-page__form">
      <?= render_view('front/partials/form-shell', [
          'action' => 'deposer-un-bien',
          'fields' => $fields,
          'submit' => __('front.submit.submit'),
          'consent' => __('front.submit.consent'),
          'errors' => $errors,
          'old' => $old,
          'flash' => $flash,
          'csrfToken' => $csrfToken,
      ]) ?>
    </div>
  </div>
</section>
