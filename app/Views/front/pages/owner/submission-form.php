<?php
/**
 * Confier un bien à Weblogy : formulaire du particulier (compte confirmé).
 * Le particulier décrit son bien ; l'équipe rédige et publie l'annonce.
 *
 * @var App\Models\User                     $user
 * @var array<int, string>                  $transactions
 * @var array<string, array<int, string>>   $categories  [famille => [id => type]]
 * @var array<int, string>                  $cities
 * @var array<int, string>                  $communes    [id => « Ville · Commune »]
 * @var array<string, string>               $titles      Titres de propriété (critère title_type)
 * @var array                               $limits
 * @var array                               $errors
 * @var array                               $old
 * @var array                               $flash
 * @var string                              $csrfToken
 */
$common = ['errors' => $errors, 'old' => $old];
$field = static fn (array $data): string => render_view('front/partials/field', $data);
$section = static fn (int $index, string $title, string $body, string $hint = ''): string => '<fieldset class="im-form__section">'
    . '<legend><span class="im-form__index">' . str_pad((string) $index, 2, '0', STR_PAD_LEFT) . '</span> ' . e($title) . '</legend>'
    . ($hint !== '' ? '<p class="im-form__hint">' . e($hint) . '</p>' : '')
    . $body . '</fieldset>';

$selectedTransaction = (string) ($old['transaction_type_id'] ?? '');
$transactionChoices = '<div class="im-field im-choices"><span class="im-field__label">' . e(__('owner.submission.transaction')) . '</span><div class="im-choices__list" role="radiogroup" aria-label="' . e(__('owner.submission.transaction')) . '">';
foreach ($transactions as $id => $label) {
    $transactionChoices .= '<label class="im-choices__item"><input type="radio" name="transaction_type_id" value="' . e((string) $id) . '"' . ($selectedTransaction === (string) $id ? ' checked' : '') . ' required><span class="im-chip">' . e($label) . '</span></label>';
}
$transactionChoices .= '</div>' . (isset($errors['transaction_type_id']) ? '<span class="im-field__error">' . e($errors['transaction_type_id']) . '</span>' : '') . '</div>';

$periods = [];
foreach (['total', 'month', 'week', 'night', 'year'] as $period) {
    $periods[$period] = __('properties.period.' . $period);
}

$fields = $section(1, __('owner.submission.section_property'),
        $transactionChoices
        . $field($common + ['name' => 'category_id', 'label' => __('owner.submission.category'), 'type' => 'select', 'options' => $categories, 'grouped' => true, 'empty' => __('owner.submission.category_empty'), 'required' => true])
        . $field($common + ['name' => 'description', 'label' => __('owner.submission.description'), 'type' => 'textarea', 'required' => true, 'maxlength' => 5000, 'placeholder' => __('owner.submission.description_placeholder')]))
    . $section(2, __('owner.submission.section_location'),
        '<div class="im-form__pair">'
        . $field($common + ['name' => 'city_id', 'label' => __('front.filters.city'), 'type' => 'select', 'options' => $cities, 'empty' => __('front.partner.city_empty'), 'required' => true])
        . $field($common + ['name' => 'commune_id', 'label' => __('front.filters.commune'), 'type' => 'select', 'options' => $communes, 'empty' => __('front.partner.commune_empty')])
        . '</div>'
        . $field($common + ['name' => 'address', 'label' => __('owner.submission.address'), 'maxlength' => 255, 'help' => __('owner.submission.address_help')]),
        __('owner.submission.section_location_hint'))
    . $section(3, __('owner.submission.section_features'),
        '<div class="im-form__pair">'
        . $field($common + ['name' => 'living_area', 'label' => __('owner.submission.living_area'), 'type' => 'number', 'maxlength' => 10])
        . $field($common + ['name' => 'land_area', 'label' => __('owner.submission.land_area'), 'type' => 'number', 'maxlength' => 12])
        . '</div><div class="im-form__triple">'
        . $field($common + ['name' => 'rooms', 'label' => __('owner.submission.rooms'), 'type' => 'number', 'maxlength' => 3])
        . $field($common + ['name' => 'bedrooms', 'label' => __('owner.submission.bedrooms'), 'type' => 'number', 'maxlength' => 3])
        . $field($common + ['name' => 'bathrooms', 'label' => __('owner.submission.bathrooms'), 'type' => 'number', 'maxlength' => 3])
        . '</div>'
        . ($titles !== [] ? $field($common + ['name' => 'title_type', 'label' => __('owner.submission.title_type'), 'type' => 'select', 'options' => $titles, 'empty' => __('owner.submission.title_type_empty')]) : ''))
    . $section(4, __('owner.submission.section_price'),
        '<div class="im-form__pair">'
        . $field($common + ['name' => 'price', 'label' => __('owner.submission.price', ['currency' => site()->country->currencySymbol ?? 'FCFA']), 'type' => 'number', 'maxlength' => 15, 'help' => __('owner.submission.price_help')])
        . $field($common + ['name' => 'price_period', 'label' => __('owner.submission.price_period'), 'type' => 'select', 'options' => $periods])
        . '</div>'
        . '<label class="im-check"><input type="checkbox" name="is_negotiable" value="1"' . (!empty($old['is_negotiable']) ? ' checked' : '') . '><span class="im-check__box" aria-hidden="true">' . icon('check') . '</span><span>' . e(__('owner.submission.negotiable')) . '</span></label>'
        . $field($common + ['name' => 'conditions', 'label' => __('owner.submission.conditions'), 'type' => 'textarea', 'maxlength' => 2000, 'placeholder' => __('owner.submission.conditions_placeholder')]))
    . $section(5, __('owner.submission.section_files'),
        $field($common + ['name' => 'photos', 'label' => __('owner.submission.photos'), 'type' => 'file', 'multiple' => true, 'required' => true, 'accept' => 'image/jpeg,image/png,image/webp', 'help' => __('owner.submission.photos_help', ['max' => $limits['photos'], 'mb' => $limits['photo_mb']])])
        . $field($common + ['name' => 'documents', 'label' => __('owner.submission.documents'), 'type' => 'file', 'multiple' => true, 'accept' => '.pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp', 'help' => __('owner.submission.documents_help', ['max' => $limits['documents'], 'mb' => $limits['document_mb']])]),
        __('owner.submission.section_files_hint'));
?>
<section class="im-section im-section--tight">
  <div class="im-container im-form-page">
    <div class="im-form-page__intro">
      <p class="im-eyebrow"><?= e(__('front.nav.entrust_property')) ?></p>
      <h1 class="im-h2"><?= e(__('owner.submission.form_title')) ?></h1>
      <p class="im-lead"><?= e(__('owner.submission.form_lead', ['site' => site()->name ?? ''])) ?></p>

      <div class="im-owner-contact-card">
        <p class="im-eyebrow"><?= e(__('owner.submission.your_details')) ?></p>
        <p><strong><?= e($user->fullName()) ?></strong></p>
        <p><?= e($user->email) ?><?= $user->phone !== null ? ' · ' . e($user->phone) : '' ?></p>
        <a class="im-link" href="<?= e(url('mon-espace/profil')) ?>"><?= e(__('owner.submission.edit_details')) ?> <?= icon('arrow-right', 'im-icon--arrow') ?></a>
      </div>
    </div>

    <div class="im-form-page__form">
      <?= render_view('front/partials/flash', ['flash' => $flash]) ?>
      <?php if (isset($errors['message'])): ?><p class="im-alert im-alert--error" role="alert"><?= icon('info') ?> <?= e($errors['message']) ?></p><?php endif; ?>
      <?php if ($errors !== [] && !isset($errors['message'])): ?><p class="im-alert im-alert--error" role="alert"><?= icon('info') ?> <?= e(__('owner.submission.errors_summary')) ?></p><?php endif; ?>

      <form class="im-form" method="post" action="<?= e(url('mon-espace/biens')) ?>" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <?= $fields ?>

        <label class="im-check im-form__consent">
          <input type="checkbox" name="consent" value="1"<?= !empty($old['consent']) ? ' checked' : '' ?> required>
          <span class="im-check__box" aria-hidden="true"><?= icon('check') ?></span>
          <span><?= e(__('owner.submission.consent', ['site' => site()->name ?? ''])) ?></span>
        </label>
        <?php if (isset($errors['consent'])): ?><span class="im-field__error"><?= e($errors['consent']) ?></span><?php endif; ?>

        <button class="im-btn im-btn--lg" type="submit"><?= icon('key') ?> <?= e(__('owner.submission.submit')) ?></button>
      </form>
    </div>
  </div>
</section>
