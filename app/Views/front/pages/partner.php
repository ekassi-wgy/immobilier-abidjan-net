<?php

/**
 * Dossier « Devenir partenaire » → table `partner_requests` et pièces `partner_request_files`.
 *
 * Destiné aux professionnels : agences, promoteurs, gestionnaires de biens et autres professionnels
 * habilités. Aucun compte n'est créé ici : Weblogy étudie le dossier et crée le compte partenaire
 * après validation. Les pièces justificatives sont stockées hors du dossier public.
 *
 * @var array  $cities      [id => nom]
 * @var array  $communes    [id => nom]
 * @var array  $volumes
 * @var array  $types       Types de professionnels
 * @var array  $legalForms  [code => libellé]
 * @var array  $documents   [champ => ['required' => bool]]
 * @var int    $maxMb
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
$section = static fn (int $index, string $title, string $body, string $hint = ''): string => '<fieldset class="im-form__section">'
    . '<legend><span class="im-form__index">' . str_pad((string) $index, 2, '0', STR_PAD_LEFT) . '</span> ' . e($title) . '</legend>'
    . ($hint !== '' ? '<p class="im-form__hint">' . e($hint) . '</p>' : '')
    . $body . '</fieldset>';

$selectedType = (string) ($old['partner_type'] ?? 'agency');
$typeChoices = '<div class="im-field im-choices"><span class="im-field__label">' . e(__('front.partner.type_label')) . '</span><div class="im-choices__list" role="radiogroup" aria-label="' . e(__('front.partner.type_label')) . '">';
foreach ($types as $type) {
    $typeChoices .= '<label class="im-choices__item"><input type="radio" name="partner_type" value="' . e($type) . '"' . ($selectedType === $type ? ' checked' : '') . '><span class="im-chip">' . e(__('front.agencies.types.' . $type)) . '</span></label>';
}
$typeChoices .= '</div>' . (isset($errors['partner_type']) ? '<span class="im-field__error">' . e($errors['partner_type']) . '</span>' : '') . '</div>';

$documentFields = '';
foreach ($documents as $name => $document) {
    $documentFields .= $field($common + [
        'name' => $name,
        'label' => __('front.partner.documents.' . $name),
        'type' => 'file',
        'accept' => '.pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp',
        'required' => $document['required'],
        'help' => __('front.partner.documents.help', ['max' => $maxMb]),
    ]);
}

$fields = $section(1, __('front.partner.section_company'),
        $typeChoices
        . '<div class="im-form__pair">'
        . $field($common + ['name' => 'agency_name', 'label' => __('front.partner.agency_name'), 'required' => true, 'maxlength' => 150, 'autocomplete' => 'organization'])
        . $field($common + ['name' => 'legal_name', 'label' => __('front.partner.legal_name'), 'maxlength' => 190, 'help' => __('front.partner.legal_name_help')])
        . '</div><div class="im-form__pair">'
        . $field($common + ['name' => 'legal_form', 'label' => __('front.partner.legal_form'), 'type' => 'select', 'options' => $legalForms, 'empty' => __('front.partner.legal_form_empty')])
        . $field($common + ['name' => 'years_active', 'label' => __('front.partner.years_active'), 'type' => 'number', 'maxlength' => 3])
        . '</div>'
        . $field($common + ['name' => 'listings_estimate', 'label' => __('front.partner.volume_label'), 'type' => 'select', 'options' => $volumeOptions, 'empty' => __('front.filters.any')]))
    . $section(2, __('front.partner.section_legal'),
        '<div class="im-form__pair">'
        . $field($common + ['name' => 'rccm', 'label' => __('front.partner.rccm'), 'required' => true, 'maxlength' => 60, 'placeholder' => 'CI-ABJ-2020-B-12345'])
        . $field($common + ['name' => 'tax_id', 'label' => __('front.partner.tax_id'), 'maxlength' => 60])
        . '</div>'
        . $field($common + ['name' => 'professional_card', 'label' => __('front.partner.professional_card'), 'maxlength' => 60, 'help' => __('front.partner.professional_card_help')]))
    . $section(3, __('front.partner.section_company_contact'),
        '<div class="im-form__pair">'
        . $field($common + ['name' => 'company_phone', 'label' => __('front.partner.company_phone'), 'type' => 'tel', 'maxlength' => 30, 'autocomplete' => 'tel'])
        . $field($common + ['name' => 'company_email', 'label' => __('front.partner.company_email'), 'type' => 'email', 'autocomplete' => 'email'])
        . '</div>'
        . $field($common + ['name' => 'website', 'label' => __('front.partner.website'), 'maxlength' => 255, 'placeholder' => 'https://'])
        . $field($common + ['name' => 'address', 'label' => __('front.partner.address'), 'maxlength' => 255, 'autocomplete' => 'street-address'])
        . '<div class="im-form__pair">'
        . $field($common + ['name' => 'city_id', 'label' => __('front.filters.city'), 'type' => 'select', 'options' => $cities, 'empty' => __('front.partner.city_empty'), 'required' => true])
        . $field($common + ['name' => 'commune_id', 'label' => __('front.filters.commune'), 'type' => 'select', 'options' => $communes, 'empty' => __('front.partner.commune_empty')])
        . '</div>')
    . $section(4, __('front.partner.section_manager'),
        '<div class="im-form__pair">'
        . $field($common + ['name' => 'name', 'label' => __('front.partner.contact_name'), 'required' => true, 'maxlength' => 150, 'autocomplete' => 'name'])
        . $field($common + ['name' => 'contact_role', 'label' => __('front.partner.contact_role'), 'maxlength' => 100, 'autocomplete' => 'organization-title'])
        . '</div><div class="im-form__pair">'
        . $field($common + ['name' => 'email', 'label' => __('front.contact.email'), 'type' => 'email', 'required' => true, 'autocomplete' => 'email'])
        . $field($common + ['name' => 'phone', 'label' => __('front.contact.phone'), 'type' => 'tel', 'required' => true, 'maxlength' => 30, 'autocomplete' => 'tel'])
        . '</div>',
        __('front.partner.section_manager_hint'))
    . $section(5, __('front.partner.section_documents'), $documentFields, __('front.partner.section_documents_hint'))
    . $section(6, __('front.partner.section_message'),
        $field($common + ['name' => 'message', 'label' => __('front.partner.message'), 'type' => 'textarea', 'maxlength' => 2000, 'placeholder' => __('front.partner.message_placeholder')]));
?>
<section class="im-section im-section--tight">
  <div class="im-container im-form-page">
    <div class="im-form-page__intro">
      <p class="im-eyebrow"><?= e(__('front.nav.become_partner')) ?></p>
      <h1 class="im-h2"><?= e(__('front.partner.page_title')) ?></h1>
      <p class="im-lead"><?= e(__('front.partner.page_lead', ['site' => site()->name ?? ''])) ?></p>

      <ol class="im-steps">
        <?php foreach (['step_1', 'step_2', 'step_3', 'step_4'] as $index => $key): ?>
        <li><span class="im-steps__number im-num"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span><?= e(__('front.partner.' . $key)) ?></li>
        <?php endforeach; ?>
      </ol>

      <p class="im-form-page__aside-note"><?= icon('info') ?> <?= e(__('front.partner.owner_redirect')) ?> <a href="<?= e(url('confiez-nous-votre-bien')) ?>"><?= e(__('front.nav.entrust_property')) ?></a></p>
    </div>

    <div class="im-form-page__form">
      <?= render_view('front/partials/form-shell', [
          'action' => 'devenir-partenaire',
          'fields' => $fields,
          'submit' => __('front.partner.submit'),
          'consent' => __('front.partner.consent', ['site' => site()->name ?? '']),
          'errors' => $errors,
          'old' => $old,
          'flash' => $flash,
          'csrfToken' => $csrfToken,
          'multipart' => true,
      ]) ?>
    </div>
  </div>
</section>
