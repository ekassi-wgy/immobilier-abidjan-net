<?php
/**
 * Détail d'un bien confié : propriétaire, description, photos et documents, décision, création de l'annonce.
 *
 * @var array<string,mixed>       $item
 * @var list<array<string,mixed>> $photos
 * @var list<array<string,mixed>> $documents
 * @var array<string,string>      $errors
 */
$statusVariant = ['submitted' => 'pending', 'in_review' => 'published', 'published' => 'published', 'rejected' => 'rejected', 'withdrawn' => 'unpublished'];
$ownerName = trim($item['owner_first_name'] . ' ' . $item['owner_last_name']);
$fileUrl = static fn (array $file): string => cmsadmin_url('biens-confies/' . $item['id'] . '/fichiers/' . $file['id']);
$phone = preg_replace('/[^0-9+]/', '', (string) $item['owner_phone']);
$closed = in_array($item['status'], ['published', 'withdrawn'], true);
$canConvert = $item['property_id'] === null && !in_array($item['status'], ['rejected', 'withdrawn'], true);
$choices = $item['property_id'] !== null ? ['in_review'] : ['submitted', 'in_review', 'rejected'];
$titleLabel = null;
if (!empty($item['title_type'])) {
    $titleLabel = app()->db()->scalar(
        "SELECT o.label FROM property_attribute_options o JOIN property_attributes a ON a.id = o.attribute_id WHERE a.code = 'title_type' AND o.code = :code",
        ['code' => $item['title_type']]
    ) ?? $item['title_type'];
}
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('submissions.show_title', ['id' => $item['id']]),
    'subtitle' => $item['category_name'] . ' · ' . $item['transaction_name'] . ' · ' . trim(implode(', ', array_filter([$item['commune_name'], $item['city_name']]))),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('submissions.title'), 'url' => 'biens-confies'], ['label' => '#' . $item['id']]],
]) ?>

<div class="im-form__layout im-form__layout--read-first">
  <div class="im-form__main">
    <section class="card im-panel">
      <div class="im-request-head">
        <h2 class="im-panel__title"><?= e(__('submissions.owner')) ?></h2>
        <?= cmsadmin_partial('state-badge', ['label' => __('submissions.status.' . $item['status']), 'variant' => $statusVariant[$item['status']] ?? 'unpublished']) ?>
      </div>
      <dl class="im-detail-list">
        <div><dt><?= e(__('submissions.owner_name')) ?></dt><dd><?= e($ownerName) ?></dd></div>
        <div><dt><?= e(__('auth.email')) ?></dt><dd><a href="mailto:<?= e($item['owner_email']) ?>"><?= e($item['owner_email']) ?></a><?= $item['owner_verified_at'] !== null ? ' <span class="mdi mdi-check-decagram-outline" title="' . e(__('submissions.email_verified')) . '"></span>' : '' ?></dd></div>
        <?php if ($item['owner_phone'] !== null): ?>
        <div><dt><?= e(__('sites.phone')) ?></dt><dd><a href="tel:<?= e($phone) ?>"><?= e($item['owner_phone']) ?></a> · <a href="https://wa.me/<?= e(preg_replace('/\D/', '', $phone)) ?>" target="_blank" rel="noopener">WhatsApp</a></dd></div>
        <?php endif; ?>
        <div><dt><?= e(__('submissions.owner_since')) ?></dt><dd><?= e(substr((string) $item['owner_since'], 0, 10)) ?></dd></div>
        <div><dt><?= e(__('submissions.consent')) ?></dt><dd><?= e(substr((string) $item['consent_at'], 0, 16)) ?> UTC</dd></div>
      </dl>
    </section>

    <section class="card im-panel">
      <h2 class="im-panel__title"><?= e(__('submissions.property')) ?></h2>
      <dl class="im-detail-list">
        <div><dt><?= e(__('submissions.location')) ?></dt><dd><?= e(trim(implode(', ', array_filter([$item['commune_name'], $item['city_name']])))) ?></dd></div>
        <?php if ($item['address'] !== null): ?><div><dt><?= e(__('owner.submission.address')) ?></dt><dd><?= e($item['address']) ?></dd></div><?php endif; ?>
        <div><dt><?= e(__('owner.submission.price_short')) ?></dt><dd class="im-num"><?= $item['price'] !== null ? e(format_price($item['price']) . ($item['price_period'] !== 'total' ? ' ' . price_period_label((string) $item['price_period']) : '') . ((int) $item['is_negotiable'] === 1 ? ' · ' . __('owner.submission.negotiable_short') : '')) : e(__('submissions.price_advice')) ?></dd></div>
        <?php foreach (['living_area' => ' m²', 'land_area' => ' m²', 'rooms' => '', 'bedrooms' => '', 'bathrooms' => ''] as $key => $unit): if ($item[$key] === null) { continue; } ?>
        <div><dt><?= e(__('owner.submission.' . $key)) ?></dt><dd class="im-num"><?= e(format_decimal((float) $item[$key], 0) . $unit) ?></dd></div>
        <?php endforeach; ?>
        <?php if ($titleLabel !== null): ?><div><dt><?= e(__('owner.submission.title_type')) ?></dt><dd><?= e((string) $titleLabel) ?></dd></div><?php endif; ?>
      </dl>
      <h3 class="im-subtitle"><?= e(__('owner.submission.description')) ?></h3>
      <blockquote class="im-quote"><?= nl2br(e((string) $item['description'])) ?></blockquote>
      <?php if ($item['conditions'] !== null): ?>
      <h3 class="im-subtitle"><?= e(__('owner.submission.conditions')) ?></h3>
      <blockquote class="im-quote"><?= nl2br(e((string) $item['conditions'])) ?></blockquote>
      <?php endif; ?>
    </section>

    <section class="card im-panel">
      <h2 class="im-panel__title"><?= e(__n('submissions.photos_count', count($photos))) ?></h2>
      <?php if ($photos !== []): ?>
      <ul class="im-thumb-grid">
        <?php foreach ($photos as $photo): ?>
        <li><a href="<?= e($fileUrl($photo) . '?apercu=1') ?>" target="_blank" rel="noopener"><img src="<?= e($fileUrl($photo) . '?apercu=1') ?>" alt="" loading="lazy"></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <h3 class="im-subtitle"><?= e(__('partners.documents')) ?></h3>
      <?php if ($documents === []): ?>
      <p class="im-note"><span class="mdi mdi-information-outline" aria-hidden="true"></span> <?= e(__('partners.no_documents')) ?></p>
      <?php else: ?>
      <ul class="im-file-list">
        <?php foreach ($documents as $file): ?>
        <li>
          <span class="mdi <?= e($file['mime'] === 'application/pdf' ? 'mdi-file-pdf-box' : 'mdi-file-image-outline') ?>" aria-hidden="true"></span>
          <span class="im-file-list__body"><span class="im-cell-main"><?= e($file['original_name']) ?></span><span class="im-cell-sub"><?= e(format_decimal((int) $file['size'] / 1048576, 1)) ?> Mo</span></span>
          <a class="btn btn-sm im-btn-ghost" href="<?= e($fileUrl($file)) ?>"><span class="mdi mdi-download" aria-hidden="true"></span> <?= e(__('partners.download')) ?></a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
  </div>

  <aside class="im-form__aside">
    <div class="im-sticky">
      <section class="card im-panel">
        <h2 class="im-panel__title"><?= e(__('submissions.listing')) ?></h2>
        <?php if ($item['property_id'] !== null): ?>
        <p class="im-note mt-2"><span class="mdi mdi-home-outline" aria-hidden="true"></span> <?= e(__('submissions.listing_created', ['ref' => $item['property_reference'], 'status' => __('properties.status.' . $item['property_status'])])) ?></p>
        <a class="btn btn-primary w-100" href="<?= e(cmsadmin_url('annonces/' . $item['property_reference'])) ?>"><?= e(__('submissions.open_listing')) ?></a>
        <?php elseif ($canConvert): ?>
        <p class="form-text"><?= e(__('submissions.convert_hint')) ?></p>
        <form method="post" action="<?= e(cmsadmin_url('biens-confies/' . $item['id'] . '/annonce')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-primary w-100" type="submit"><span class="mdi mdi-home-plus-outline" aria-hidden="true"></span> <?= e(__('submissions.convert')) ?></button>
        </form>
        <?php else: ?>
        <p class="form-text"><?= e(__('submissions.convert_closed')) ?></p>
        <?php endif; ?>
      </section>

      <form class="card im-panel" method="post" action="<?= e(cmsadmin_url('biens-confies/' . $item['id'])) ?>" novalidate>
        <?= csrf_field() ?>
        <h2 class="im-panel__title"><?= e(__('submissions.follow_up')) ?></h2>
        <?php if ($closed): ?>
        <input type="hidden" name="status" value="<?= e($item['status']) ?>">
        <p class="im-note mt-2"><span class="mdi mdi-information-outline" aria-hidden="true"></span> <?= e(__('submissions.closed_' . $item['status'])) ?></p>
        <?php else: ?>
        <fieldset class="mt-3 mb-3">
          <legend class="form-label"><?= e(__('cmsadmin.state')) ?></legend>
          <div class="im-segmented im-segmented--stack" role="radiogroup">
            <?php foreach ($choices as $status): ?>
            <label class="im-segmented__option"><input type="radio" name="status" value="<?= e($status) ?>"<?= $item['status'] === $status ? ' checked' : '' ?>><span><?= e(__('submissions.status.' . $status)) ?></span></label>
            <?php endforeach; ?>
          </div>
          <?php if (isset($errors['status'])): ?><p class="invalid-feedback d-block"><?= e($errors['status']) ?></p><?php endif; ?>
        </fieldset>
        <?= cmsadmin_partial('field', ['name' => 'rejection_reason', 'type' => 'textarea', 'label' => __('submissions.rejection_reason'), 'value' => $item['rejection_reason'] ?? '', 'optional' => true, 'hint' => __('submissions.rejection_hint'), 'error' => $errors['rejection_reason'] ?? null, 'attributes' => ['maxlength' => 2000, 'rows' => 3]]) ?>
        <?php endif; ?>
        <?= cmsadmin_partial('field', ['name' => 'internal_notes', 'type' => 'textarea', 'label' => __('partners.notes'), 'value' => $item['internal_notes'] ?? '', 'optional' => true, 'hint' => __('submissions.notes_hint'), 'error' => $errors['internal_notes'] ?? null, 'attributes' => ['maxlength' => 5000, 'rows' => 4]]) ?>
        <?php if ($item['handled_at'] !== null): ?><p class="form-text"><?= e(__('submissions.handled', ['date' => substr((string) $item['handled_at'], 0, 16), 'name' => $item['handled_by_name'] ?? '—'])) ?></p><?php endif; ?>
        <div class="d-grid gap-2">
          <button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.save')) ?></button>
          <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url('biens-confies')) ?>"><?= e(__('submissions.back')) ?></a>
        </div>
      </form>
    </div>
  </aside>
</div>
