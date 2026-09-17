<?php
/**
 * Création / modification d'une annonce. Les critères dépendent de la catégorie (fragment rechargé en arrière-plan),
 * les photos sont envoyées au fil de l'eau et réordonnables.
 *
 * @var array<string,mixed>|null  $property
 * @var array<string,mixed>       $values
 * @var array<string,string>      $errors
 * @var array<string,mixed>|null  $revision  Révision en cours (agence)
 * @var bool                      $isStaff
 * @var bool                      $publishesDirectly  Une soumission de ce compte est publiée sans validation
 * @var array<string,mixed>|null  $schema
 * @var array<string, array<int,string>> $categories
 * @var array<string, array<int,string>> $features
 * @var array<int,string>         $cities
 * @var array<int,string>         $communes
 * @var array<int,string>         $districts
 * @var array<int,string>         $agencies
 * @var array<int,string>         $agents
 * @var list<array{id: ?int, token: ?string, alt: ?string, thumb: string}> $photos
 * @var int                       $maxPhotos
 * @var int                       $maxPhotoMb
 * @var string                    $currency
 * @var array{lat: float, lng: float, zoom: int} $mapCenter
 */
$isEdit = $property !== null;
$value = static fn (string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
$field = static fn (array $options): string => cmsadmin_partial('field', $options + ['class' => '', 'error' => $errors[$options['name']] ?? null, 'value' => $value($options['name'])]);
$selectedFeatures = array_map('intval', (array) $value('features', []));
$transactions = [];
foreach ($schema['transactions'] ?? [] as $id => $transaction) {
    $transactions[$id] = $transaction['name'];
}
$sections = ['bien' => __('properties.sections.property'), 'prix' => __('properties.sections.price'), 'localisation' => __('properties.sections.location'), 'criteres' => __('properties.sections.criteria'), 'equipements' => __('properties.sections.features'), 'photos' => __('properties.sections.photos'), 'contact' => __('properties.sections.contact'), 'interne' => __('properties.sections.private')];
// Publication directe des partenaires (Paramètres) : sans elle, toute modification d'une annonce en ligne est une révision.
$publishesDirectly ??= false;
$directPublish = !$isStaff && $publishesDirectly;
$publishedByAgency = $isEdit && !$isStaff && !$directPublish && $property['status'] === 'published';
$canDraft = !$isEdit || $property['status'] === 'draft';
$submitLabel = match (true) {
    $publishedByAgency => 'properties.actions.submit_revision',
    $isEdit && $property['status'] !== 'draft' => 'cmsadmin.save',
    $publishesDirectly => 'properties.actions.submit_publish',
    default => 'properties.actions.submit',
};
?>
<?= cmsadmin_partial('page-header', [
    'title' => $isEdit ? __('properties.edit_title', ['ref' => $property['reference']]) : __('properties.create_title'),
    'subtitle' => $isEdit ? $property['title'] : __('properties.create_subtitle'),
    'breadcrumb' => [
        ['label' => __('auth.dashboard'), 'url' => '/'],
        ['label' => __('properties.title'), 'url' => 'annonces'],
        ['label' => $isEdit ? $property['reference'] : __('properties.create')],
    ],
]) ?>

<?php if ($publishedByAgency): ?>
<div class="im-flash im-flash--info" role="status">
  <span class="mdi mdi-information-outline" aria-hidden="true"></span>
  <p><?= e(__($revision !== null ? 'properties.revision.editing_again' : 'properties.revision.notice')) ?></p>
</div>
<?php endif; ?>
<?php if ($errors !== []): ?>
<div class="im-flash im-flash--error" role="alert">
  <span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span>
  <p><?= e(__('cmsadmin.form_errors', ['count' => count($errors)])) ?></p>
</div>
<?php endif; ?>

<form class="im-form" id="property-form" method="post" enctype="multipart/form-data"
      action="<?= e(cmsadmin_url($isEdit ? 'annonces/' . $property['reference'] : 'annonces')) ?>" novalidate
      data-criteria-url="<?= e(cmsadmin_url('annonces/criteres')) ?>" data-options-url="<?= e(cmsadmin_url('annonces/listes')) ?>"
      data-photos-url="<?= e(cmsadmin_url('annonces/photos')) ?>" data-reference="<?= e($isEdit ? $property['reference'] : '') ?>"
      data-max-photos="<?= e($maxPhotos) ?>">
  <?= csrf_field() ?>

  <div class="im-form__layout">
    <div class="im-form__main">
      <section class="card im-panel im-form-section" id="bien">
        <header class="im-form-section__head"><span class="im-form-section__index">01</span><h2 class="im-panel__title"><?= e($sections['bien']) ?></h2></header>
        <div class="row g-3">
          <?php if ($isStaff): ?>
          <div class="col-md-4">
            <?= $field(['name' => 'source', 'type' => 'select', 'label' => __('properties.fields.source'), 'options' => ['agency' => __('properties.source.agency'), 'private_owner' => __('properties.source.private_owner'), 'platform' => __('properties.source.platform')], 'value' => $value('source', 'agency'), 'required' => true, 'attributes' => ['data-source-select' => '']]) ?>
          </div>
          <div class="col-md-4" data-agency-field<?= $value('source', 'agency') !== 'agency' ? ' hidden' : '' ?>>
            <?= $field(['name' => 'agency_id', 'type' => 'select', 'label' => __('agencies.singular'), 'options' => $agencies, 'placeholder' => __('cmsadmin.choose'), 'attributes' => ['data-im-select' => '', 'data-placeholder' => __('cmsadmin.choose'), 'data-agency-select' => '']]) ?>
          </div>
          <?php endif; ?>
          <div class="col-md-4">
            <?= $field(['name' => 'agent_user_id', 'type' => 'select', 'label' => __('properties.fields.agent'), 'options' => $agents, 'placeholder' => __('properties.no_agent'), 'optional' => true, 'hint' => __('properties.agent_hint')]) ?>
          </div>
          <div class="col-md-6">
            <?= $field(['name' => 'category_id', 'type' => 'select', 'label' => __('properties.fields.category'), 'options' => $categories, 'placeholder' => __('cmsadmin.choose'), 'required' => true, 'attributes' => ['data-im-select' => '', 'data-placeholder' => __('cmsadmin.choose'), 'data-category-select' => '']]) ?>
          </div>
          <div class="col-md-6" data-transaction-field>
            <?= $field(['name' => 'transaction_type_id', 'type' => 'select', 'label' => __('properties.fields.transaction'), 'options' => $transactions, 'placeholder' => __('cmsadmin.choose'), 'required' => true, 'hint' => __('properties.transaction_hint')]) ?>
          </div>
          <div class="col-md-8">
            <?= $field(['name' => 'title', 'label' => __('properties.fields.title'), 'required' => true, 'hint' => __('properties.title_hint'), 'attributes' => ['maxlength' => 160, 'placeholder' => __('properties.title_placeholder')]]) ?>
          </div>
          <div class="col-md-4">
            <?= $field(['name' => 'internal_reference', 'label' => __('properties.fields.internal_reference'), 'optional' => true, 'hint' => __('properties.internal_reference_hint'), 'attributes' => ['maxlength' => 50]]) ?>
          </div>
          <div class="col-12">
            <?= $field(['name' => 'description', 'type' => 'textarea', 'label' => __('properties.fields.description'), 'required' => true, 'hint' => __('properties.description_hint'), 'attributes' => ['maxlength' => 20000, 'rows' => 8]]) ?>
          </div>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="prix">
        <header class="im-form-section__head"><span class="im-form-section__index">02</span><h2 class="im-panel__title"><?= e($sections['prix']) ?></h2></header>
        <div class="row g-3">
          <div class="col-md-5">
            <?= $field(['name' => 'price', 'label' => __('properties.fields.price'), 'suffix' => $currency, 'attributes' => ['inputmode' => 'numeric', 'data-price-input' => ''], 'hint' => __('properties.price_hint')]) ?>
          </div>
          <div class="col-md-3">
            <?= $field(['name' => 'price_period', 'type' => 'select', 'label' => __('properties.fields.price_period'), 'options' => array_combine(['total', 'month', 'week', 'night', 'year'], array_map(static fn (string $p): string => __('properties.period.' . $p), ['total', 'month', 'week', 'night', 'year'])), 'value' => $value('price_period', 'total')]) ?>
          </div>
          <div class="col-md-4">
            <?= $field(['name' => 'agency_fee_percent', 'label' => __('properties.fields.agency_fee'), 'suffix' => '%', 'optional' => true, 'attributes' => ['inputmode' => 'decimal']]) ?>
          </div>
          <div class="col-md-5">
            <?= $field(['name' => 'charges', 'label' => __('properties.fields.charges'), 'suffix' => $currency, 'optional' => true, 'attributes' => ['inputmode' => 'numeric']]) ?>
          </div>
          <div class="col-md-7 d-flex flex-column justify-content-end">
            <?= cmsadmin_partial('switch', ['name' => 'price_on_request', 'label' => __('properties.price_on_request'), 'checked' => (bool) $value('price_on_request', 0), 'class' => 'mb-2']) ?>
            <?= cmsadmin_partial('switch', ['name' => 'is_negotiable', 'label' => __('properties.negotiable'), 'checked' => (bool) $value('is_negotiable', 0), 'class' => 'mb-0']) ?>
          </div>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="localisation">
        <header class="im-form-section__head"><span class="im-form-section__index">03</span><h2 class="im-panel__title"><?= e($sections['localisation']) ?></h2></header>
        <div class="row g-3">
          <div class="col-md-4">
            <?= $field(['name' => 'city_id', 'type' => 'select', 'label' => __('geo.city.singular'), 'options' => $cities, 'placeholder' => __('cmsadmin.choose'), 'required' => true, 'attributes' => ['data-city-select' => '']]) ?>
          </div>
          <div class="col-md-4">
            <?= $field(['name' => 'commune_id', 'type' => 'select', 'label' => __('geo.commune.singular'), 'options' => $communes, 'placeholder' => __('cmsadmin.choose'), 'attributes' => ['data-commune-select' => '']]) ?>
          </div>
          <div class="col-md-4">
            <?= $field(['name' => 'district_id', 'type' => 'select', 'label' => __('geo.district.singular'), 'options' => $districts, 'placeholder' => __('properties.district_optional'), 'optional' => true, 'attributes' => ['data-district-select' => '']]) ?>
          </div>
          <div class="col-md-8">
            <?= $field(['name' => 'address', 'label' => __('properties.fields.address'), 'optional' => true, 'hint' => __('properties.address_hint'), 'attributes' => ['maxlength' => 255, 'placeholder' => __('properties.address_placeholder')]]) ?>
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <?= cmsadmin_partial('switch', ['name' => 'show_exact_location', 'label' => __('properties.show_exact_location'), 'checked' => (bool) $value('show_exact_location', 0), 'hint' => __('properties.show_exact_location_hint'), 'class' => 'mb-0']) ?>
          </div>
          <div class="col-12">
            <div class="im-map im-map--editor" id="property-map"
                 data-lat="<?= e($mapCenter['lat']) ?>" data-lng="<?= e($mapCenter['lng']) ?>" data-zoom="<?= e($mapCenter['zoom']) ?>"
                 data-has-marker="<?= $value('latitude') !== '' && $value('latitude') !== null ? '1' : '0' ?>"></div>
            <p class="form-text"><?= e(__('properties.map_hint')) ?></p>
          </div>
          <div class="col-6 col-md-3">
            <?= $field(['name' => 'latitude', 'label' => __('geo.latitude'), 'optional' => true, 'attributes' => ['inputmode' => 'decimal', 'data-lat-input' => '']]) ?>
          </div>
          <div class="col-6 col-md-3">
            <?= $field(['name' => 'longitude', 'label' => __('geo.longitude'), 'optional' => true, 'attributes' => ['inputmode' => 'decimal', 'data-lng-input' => '']]) ?>
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <button class="btn im-btn-ghost btn-sm" type="button" data-clear-marker><span class="mdi mdi-map-marker-off-outline" aria-hidden="true"></span> <?= e(__('properties.clear_marker')) ?></button>
          </div>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="criteres">
        <header class="im-form-section__head">
          <span class="im-form-section__index">04</span>
          <div><h2 class="im-panel__title"><?= e($sections['criteres']) ?></h2><p class="im-panel__subtitle"><?= e(__('properties.criteria_subtitle')) ?></p></div>
        </header>
        <div data-criteria-container>
          <?= render_view('cmsadmin/pages/properties/criteria', ['schema' => $schema, 'values' => (array) $value('attributes', []), 'errors' => $errors]) ?>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="equipements">
        <header class="im-form-section__head"><span class="im-form-section__index">05</span><h2 class="im-panel__title"><?= e($sections['equipements']) ?></h2></header>
        <?php foreach ($features as $group => $items): ?>
        <h3 class="im-subtitle"><?= e(__('catalog.features.groups.' . $group)) ?></h3>
        <div class="im-checks">
          <?php foreach ($items as $id => $label): ?>
          <label class="im-check">
            <input type="checkbox" name="features[]" value="<?= e($id) ?>"<?= in_array($id, $selectedFeatures, true) ? ' checked' : '' ?>>
            <span class="im-check__box" aria-hidden="true"><span class="mdi mdi-check"></span></span>
            <span><?= e($label) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </section>

      <section class="card im-panel im-form-section" id="photos">
        <header class="im-form-section__head">
          <span class="im-form-section__index">06</span>
          <div><h2 class="im-panel__title"><?= e($sections['photos']) ?></h2><p class="im-panel__subtitle"><?= e(__('properties.photos_subtitle', ['max' => $maxPhotos, 'mb' => $maxPhotoMb])) ?></p></div>
        </header>
        <?php if (isset($errors['photos'])): ?><p class="invalid-feedback d-block"><?= e($errors['photos']) ?></p><?php endif; ?>

        <label class="im-dropzone" for="photo-input" data-dropzone>
          <span class="mdi mdi-image-plus-outline" aria-hidden="true"></span>
          <span class="im-dropzone__title"><?= e(__('properties.photos_drop')) ?></span>
          <span class="im-dropzone__text"><?= e(__('properties.photos_browse')) ?></span>
          <input class="visually-hidden" id="photo-input" type="file" accept="image/jpeg,image/png,image/webp" multiple data-photo-input>
        </label>
        <p class="im-photo-status" role="status" data-photo-status></p>

        <ul class="im-photo-grid" data-photo-list>
          <?php foreach ($photos as $index => $photo): ?>
          <li class="im-photo" draggable="true" data-photo-item>
            <input type="hidden" name="photos[<?= e($index) ?>][id]" value="<?= e($photo['id'] ?? '') ?>" data-photo-id>
            <input type="hidden" name="photos[<?= e($index) ?>][token]" value="<?= e($photo['token'] ?? '') ?>" data-photo-token>
            <img src="<?= e($photo['thumb']) ?>" alt="" loading="lazy">
            <span class="im-photo__cover"><?= e(__('properties.cover')) ?></span>
            <div class="im-photo__actions">
              <button type="button" class="im-icon-btn im-icon-btn--sm" data-photo-up aria-label="<?= e(__('properties.photo_up')) ?>"><span class="mdi mdi-arrow-left" aria-hidden="true"></span></button>
              <button type="button" class="im-icon-btn im-icon-btn--sm" data-photo-down aria-label="<?= e(__('properties.photo_down')) ?>"><span class="mdi mdi-arrow-right" aria-hidden="true"></span></button>
              <button type="button" class="im-icon-btn im-icon-btn--sm" data-photo-remove aria-label="<?= e(__('properties.photo_remove')) ?>"><span class="mdi mdi-close" aria-hidden="true"></span></button>
            </div>
            <input class="form-control form-control-sm im-photo__alt" name="photos[<?= e($index) ?>][alt]" value="<?= e($photo['alt'] ?? '') ?>" maxlength="190" placeholder="<?= e(__('properties.photo_alt')) ?>" aria-label="<?= e(__('properties.photo_alt')) ?>">
          </li>
          <?php endforeach; ?>
        </ul>
        <template data-photo-template>
          <li class="im-photo" draggable="true" data-photo-item>
            <input type="hidden" name="photos[__INDEX__][id]" value="" data-photo-id>
            <input type="hidden" name="photos[__INDEX__][token]" value="__TOKEN__" data-photo-token>
            <img src="__THUMB__" alt="" loading="lazy">
            <span class="im-photo__cover"><?= e(__('properties.cover')) ?></span>
            <div class="im-photo__actions">
              <button type="button" class="im-icon-btn im-icon-btn--sm" data-photo-up aria-label="<?= e(__('properties.photo_up')) ?>"><span class="mdi mdi-arrow-left" aria-hidden="true"></span></button>
              <button type="button" class="im-icon-btn im-icon-btn--sm" data-photo-down aria-label="<?= e(__('properties.photo_down')) ?>"><span class="mdi mdi-arrow-right" aria-hidden="true"></span></button>
              <button type="button" class="im-icon-btn im-icon-btn--sm" data-photo-remove aria-label="<?= e(__('properties.photo_remove')) ?>"><span class="mdi mdi-close" aria-hidden="true"></span></button>
            </div>
            <input class="form-control form-control-sm im-photo__alt" name="photos[__INDEX__][alt]" value="" maxlength="190" placeholder="<?= e(__('properties.photo_alt')) ?>" aria-label="<?= e(__('properties.photo_alt')) ?>">
          </li>
        </template>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <?= $field(['name' => 'video_url', 'type' => 'url', 'label' => __('properties.fields.video_url'), 'optional' => true, 'hint' => __('properties.video_hint'), 'attributes' => ['maxlength' => 255, 'placeholder' => 'https://']]) ?>
          </div>
          <div class="col-md-6">
            <?= $field(['name' => 'virtual_tour_url', 'type' => 'url', 'label' => __('properties.fields.virtual_tour_url'), 'optional' => true, 'attributes' => ['maxlength' => 255, 'placeholder' => 'https://']]) ?>
          </div>
          <div class="col-12">
            <label class="form-label" for="f-document"><?= e(__('properties.fields.document')) ?> <span class="im-optional"><?= e(__('cmsadmin.optional')) ?></span></label>
            <input class="form-control<?= isset($errors['document']) ? ' is-invalid' : '' ?>" id="f-document" name="document" type="file" accept="application/pdf">
            <?php if (isset($errors['document'])): ?><p class="invalid-feedback d-block"><?= e($errors['document']) ?></p><?php endif; ?>
            <?php if ($value('document_path')): ?>
            <p class="form-text"><a href="<?= e(url((string) $value('document_path'))) ?>" target="_blank" rel="noopener"><?= e(basename((string) $value('document_path'))) ?></a></p>
            <label class="im-check mt-1"><input type="checkbox" name="remove_document" value="1"><span class="im-check__box" aria-hidden="true"><span class="mdi mdi-check"></span></span><span><?= e(__('properties.remove_document')) ?></span></label>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="contact">
        <header class="im-form-section__head"><span class="im-form-section__index">07</span><h2 class="im-panel__title"><?= e($sections['contact']) ?></h2></header>
        <p class="im-panel__subtitle"><?= e(__('properties.contact_subtitle')) ?></p>
        <div class="row g-3">
          <div class="col-md-3"><?= $field(['name' => 'contact_name', 'label' => __('properties.fields.contact_name'), 'optional' => true, 'attributes' => ['maxlength' => 120]]) ?></div>
          <div class="col-md-3"><?= $field(['name' => 'contact_phone', 'type' => 'tel', 'label' => __('properties.fields.contact_phone'), 'optional' => true, 'attributes' => ['maxlength' => 30, 'placeholder' => '+225 07 00 00 00 00']]) ?></div>
          <div class="col-md-3"><?= $field(['name' => 'contact_whatsapp', 'type' => 'tel', 'label' => __('properties.fields.contact_whatsapp'), 'optional' => true, 'attributes' => ['maxlength' => 30]]) ?></div>
          <div class="col-md-3"><?= $field(['name' => 'contact_email', 'type' => 'email', 'label' => __('properties.fields.contact_email'), 'optional' => true, 'attributes' => ['maxlength' => 190]]) ?></div>
          <div class="col-md-4"><?= $field(['name' => 'availability', 'type' => 'select', 'label' => __('properties.fields.availability'), 'options' => array_combine(['available', 'reserved', 'sold', 'rented'], array_map(static fn (string $a): string => __('properties.availability.' . $a), ['available', 'reserved', 'sold', 'rented'])), 'value' => $value('availability', 'available'), 'required' => true]) ?></div>
          <div class="col-md-4"><?= $field(['name' => 'available_from', 'type' => 'date', 'label' => __('properties.fields.available_from'), 'optional' => true]) ?></div>
        </div>
      </section>

      <section class="card im-panel im-form-section" id="interne">
        <header class="im-form-section__head"><span class="im-form-section__index">08</span><h2 class="im-panel__title"><?= e($sections['interne']) ?></h2></header>
        <div class="im-private-zone">
          <p class="im-private-zone__label"><span class="mdi mdi-lock-outline" aria-hidden="true"></span> <?= e(__('properties.private_notice')) ?></p>
          <div class="row g-3">
            <?php if ($isStaff): ?>
            <div class="col-md-4" data-owner-field<?= $value('source', 'agency') !== 'private_owner' ? ' hidden' : '' ?>><?= $field(['name' => 'owner_name', 'label' => __('properties.fields.owner_name'), 'optional' => true, 'attributes' => ['maxlength' => 150]]) ?></div>
            <div class="col-md-4" data-owner-field<?= $value('source', 'agency') !== 'private_owner' ? ' hidden' : '' ?>><?= $field(['name' => 'owner_phone', 'type' => 'tel', 'label' => __('properties.fields.owner_phone'), 'optional' => true, 'attributes' => ['maxlength' => 30]]) ?></div>
            <div class="col-md-4" data-owner-field<?= $value('source', 'agency') !== 'private_owner' ? ' hidden' : '' ?>><?= $field(['name' => 'owner_email', 'type' => 'email', 'label' => __('properties.fields.owner_email'), 'optional' => true, 'attributes' => ['maxlength' => 190]]) ?></div>
            <?php endif; ?>
            <div class="col-md-6"><?= $field(['name' => 'notary_name', 'label' => __('properties.fields.notary_name'), 'optional' => true, 'attributes' => ['maxlength' => 150]]) ?></div>
            <div class="col-md-6"><?= $field(['name' => 'notary_reference', 'label' => __('properties.fields.notary_reference'), 'optional' => true, 'attributes' => ['maxlength' => 100]]) ?></div>
            <div class="col-12"><?= $field(['name' => 'internal_notes', 'type' => 'textarea', 'label' => __('properties.fields.internal_notes'), 'optional' => true, 'attributes' => ['maxlength' => 5000, 'rows' => 3]]) ?></div>
          </div>
        </div>
      </section>
    </div>

    <aside class="im-form__aside">
      <div class="im-sticky">
        <section class="card im-panel">
          <h2 class="im-panel__title"><?= e(__('cmsadmin.publication')) ?></h2>
          <dl class="im-meta-list">
            <div><dt><?= e(__('cmsadmin.state')) ?></dt><dd><?= $isEdit ? cmsadmin_partial('status-badge', ['status' => $property['status']]) : '<span class="im-muted">' . e(__('properties.new')) . '</span>' ?></dd></div>
            <?php if ($isEdit): ?><div><dt><?= e(__('properties.fields.reference')) ?></dt><dd><?= e($property['reference']) ?></dd></div><?php endif; ?>
          </dl>
          <?php if ($isStaff): ?>
          <?= cmsadmin_partial('switch', ['name' => 'is_featured', 'label' => __('properties.featured'), 'checked' => (bool) $value('is_featured', 0), 'hint' => __('properties.featured_form_hint')]) ?>
          <?= $field(['name' => 'featured_until', 'type' => 'date', 'label' => __('properties.featured_until_label'), 'optional' => true]) ?>
          <?php else: ?>
          <p class="im-note"><span class="mdi mdi-information-outline" aria-hidden="true"></span> <?= e(__($publishedByAgency ? 'properties.revision.note' : ($directPublish ? 'properties.direct_publish_notice' : 'properties.review_notice'))) ?></p>
          <?php endif; ?>
          <div class="d-grid gap-2">
            <button class="btn btn-primary" type="submit" name="intent" value="submit"><?= e(__($submitLabel)) ?></button>
            <?php if ($canDraft): ?>
            <button class="btn im-btn-ghost" type="submit" name="intent" value="draft"><span class="mdi mdi-content-save-outline" aria-hidden="true"></span> <?= e(__('properties.actions.save_draft')) ?></button>
            <?php endif; ?>
            <a class="btn im-btn-ghost" href="<?= e($isEdit ? cmsadmin_url('annonces/' . $property['reference']) : cmsadmin_url('annonces')) ?>"><?= e(__('cmsadmin.cancel')) ?></a>
          </div>
        </section>

        <nav class="im-toc" aria-label="<?= e(__('catalog.on_this_page')) ?>">
          <p class="im-toc__title"><?= e(__('catalog.on_this_page')) ?></p>
          <ol>
            <?php foreach ($sections as $anchor => $label): ?><li><a href="#<?= e($anchor) ?>"><?= e($label) ?></a></li><?php endforeach; ?>
          </ol>
        </nav>
      </div>
    </aside>
  </div>
</form>
