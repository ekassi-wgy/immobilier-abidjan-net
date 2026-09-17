<?php
/**
 * Fiche d'une annonce : contenu publié, révision en attente (différences), historique et actions.
 *
 * @var array<string,mixed>            $property
 * @var string                         $price
 * @var string|null                    $location
 * @var array<string, list<array{label: string, value: string, public: bool}>> $criteria
 * @var list<string>                   $features
 * @var list<array<string,mixed>>      $images
 * @var array<string,mixed>            $private
 * @var list<array<string,mixed>>      $history
 * @var array<string,mixed>|null       $revision
 * @var list<array{label: string, before: ?string, after: ?string}> $diff
 * @var list<array<string,mixed>>      $revisionImages
 * @var array<string,mixed>|null       $lastRejectedRevision
 * @var int                            $lifetimeDays
 * @var array                          $user
 */
$isStaff = $user['role'] !== 'agency';
$status = (string) $property['status'];
$url = static fn (string $action = ''): string => cmsadmin_url('annonces/' . $property['reference'] . $action);
$needsReview = $isStaff && ($status === 'pending' || $revision !== null);
$date = static fn (?string $value): string => $value !== null ? substr($value, 0, 16) . ' UTC' : '—';
?>
<?= cmsadmin_partial('page-header', [
    'title' => $property['title'],
    'subtitle' => $property['reference'] . ' · ' . $property['category_name'] . ' · ' . $property['transaction_name'],
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('properties.title'), 'url' => 'annonces'], ['label' => $property['reference']]],
    'actions' => [['label' => __('cmsadmin.edit'), 'url' => 'annonces/' . $property['reference'] . '/modifier', 'icon' => 'mdi-pencil-outline']],
]) ?>

<?php if ($status === 'rejected' && $property['rejection_reason'] !== null): ?>
<div class="im-flash im-flash--error" role="alert">
  <span class="mdi mdi-close-circle-outline" aria-hidden="true"></span>
  <p><strong><?= e(__('properties.rejected_reason')) ?></strong> <?= e($property['rejection_reason']) ?></p>
</div>
<?php endif; ?>
<?php if ($lastRejectedRevision !== null && $revision === null): ?>
<div class="im-flash im-flash--error" role="alert">
  <span class="mdi mdi-close-circle-outline" aria-hidden="true"></span>
  <p><strong><?= e(__('properties.revision_rejected_reason')) ?></strong> <?= e($lastRejectedRevision['rejection_reason']) ?></p>
</div>
<?php endif; ?>

<div class="im-form__layout">
  <div class="im-form__main">
    <?php if ($revision !== null): ?>
    <section class="card im-panel" id="validation">
      <div class="im-request-head">
        <h2 class="im-panel__title"><?= e(__('properties.revision.title')) ?></h2>
        <?= cmsadmin_partial('state-badge', ['label' => __('properties.revision.pending'), 'variant' => 'pending']) ?>
      </div>
      <p class="im-panel__subtitle"><?= e(__('properties.revision.submitted_by', ['name' => $revision['submitted_by_name'] ?? '—', 'date' => $date($revision['submitted_at'])])) ?></p>

      <?php if ($diff === []): ?>
      <p class="im-note"><span class="mdi mdi-information-outline" aria-hidden="true"></span> <?= e(__('properties.revision.no_change')) ?></p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table im-table im-diff">
          <thead><tr><th scope="col"><?= e(__('properties.revision.field')) ?></th><th scope="col"><?= e(__('properties.revision.before')) ?></th><th scope="col"><?= e(__('properties.revision.after')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($diff as $change): ?>
            <tr>
              <th scope="row"><?= e($change['label']) ?></th>
              <td class="im-diff__before"><?= $change['before'] !== null ? nl2br(e(mb_substr($change['before'], 0, 600))) : '<span class="im-muted">—</span>' ?></td>
              <td class="im-diff__after"><?= $change['after'] !== null ? nl2br(e(mb_substr($change['after'], 0, 600))) : '<span class="im-muted">—</span>' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <?php if ($revisionImages !== []): ?>
      <h3 class="im-subtitle"><?= e(__('properties.revision.new_photos')) ?></h3>
      <div class="im-gallery">
        <?php foreach ($revisionImages as $image): ?>
        <figure class="im-gallery__item"><img src="<?= e($image['thumb']) ?>" alt="<?= e($image['alt_text'] ?? '') ?>" loading="lazy"></figure>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="card im-panel">
      <h2 class="im-panel__title"><?= e(__('properties.sections.photos')) ?> <span class="im-count"><?= e(count($images)) ?></span></h2>
      <?php if ($images === []): ?>
      <p class="im-note mt-3"><span class="mdi mdi-image-off-outline" aria-hidden="true"></span> <?= e(__('properties.no_photos')) ?></p>
      <?php else: ?>
      <div class="im-gallery mt-3">
        <?php foreach ($images as $index => $image): ?>
        <figure class="im-gallery__item<?= $index === 0 ? ' is-cover' : '' ?>">
          <a href="<?= e($image['url']) ?>" target="_blank" rel="noopener"><img src="<?= e($image['thumb']) ?>" alt="<?= e($image['alt_text'] ?? '') ?>" loading="lazy"></a>
          <?php if ($index === 0): ?><figcaption><?= e(__('properties.cover')) ?></figcaption><?php endif; ?>
        </figure>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <section class="card im-panel">
      <h2 class="im-panel__title"><?= e(__('properties.sections.summary')) ?></h2>
      <dl class="im-detail-list">
        <div><dt><?= e(__('properties.fields.price')) ?></dt><dd class="im-num"><?= e($price) ?><?= (int) $property['is_negotiable'] === 1 ? ' · ' . e(__('properties.negotiable')) : '' ?></dd></div>
        <?php if ($property['charges'] !== null): ?><div><dt><?= e(__('properties.fields.charges')) ?></dt><dd class="im-num"><?= e(format_price($property['charges'])) ?></dd></div><?php endif; ?>
        <?php if ($property['agency_fee_percent'] !== null): ?><div><dt><?= e(__('properties.fields.agency_fee')) ?></dt><dd class="im-num"><?= e((float) $property['agency_fee_percent']) ?> %</dd></div><?php endif; ?>
        <div><dt><?= e(__('properties.fields.location')) ?></dt><dd><?= e($location ?? '—') ?><?= $property['address'] ? '<br><span class="im-muted">' . e($property['address']) . '</span>' : '' ?></dd></div>
        <div><dt><?= e(__('properties.fields.availability')) ?></dt><dd><?= e(__('properties.availability.' . $property['availability'])) ?><?= $property['available_from'] ? ' · ' . e(__('properties.from_date', ['date' => substr((string) $property['available_from'], 0, 10)])) : '' ?></dd></div>
        <?php if ($property['agency_name'] !== null): ?><div><dt><?= e(__('agencies.singular')) ?></dt><dd><?= e($property['agency_name']) ?><?= $property['agent_name'] ? ' · ' . e($property['agent_name']) : '' ?></dd></div><?php endif; ?>
        <div><dt><?= e(__('properties.fields.contact')) ?></dt><dd><?= e(trim(($property['contact_name'] ?? '') . ' ' . ($property['contact_phone'] ?? ''))) ?: '—' ?></dd></div>
        <?php if ($property['video_url'] || $property['virtual_tour_url'] || $property['document_path']): ?>
        <div><dt><?= e(__('properties.sections.media')) ?></dt><dd>
          <?php if ($property['video_url']): ?><a href="<?= e($property['video_url']) ?>" target="_blank" rel="noopener"><?= e(__('properties.fields.video_url')) ?></a><?php endif; ?>
          <?php if ($property['virtual_tour_url']): ?> · <a href="<?= e($property['virtual_tour_url']) ?>" target="_blank" rel="noopener"><?= e(__('properties.fields.virtual_tour_url')) ?></a><?php endif; ?>
          <?php if ($property['document_path']): ?> · <a href="<?= e(url($property['document_path'])) ?>" target="_blank" rel="noopener"><?= e(__('properties.fields.document')) ?></a><?php endif; ?>
        </dd></div>
        <?php endif; ?>
      </dl>

      <h3 class="im-subtitle"><?= e(__('properties.fields.description')) ?></h3>
      <div class="im-prose"><?= nl2br(e($property['description'])) ?></div>
    </section>

    <?php if ($criteria !== []): ?>
    <section class="card im-panel">
      <h2 class="im-panel__title"><?= e(__('properties.sections.criteria')) ?></h2>
      <?php foreach ($criteria as $group => $items): ?>
      <h3 class="im-subtitle"><?= e($group) ?></h3>
      <dl class="im-detail-list">
        <?php foreach ($items as $item): ?>
        <div><dt><?= e($item['label']) ?><?= $item['public'] ? '' : ' <span class="im-tag im-tag--muted">' . e(__('catalog.attributes.private')) . '</span>' ?></dt><dd><?= e($item['value']) ?></dd></div>
        <?php endforeach; ?>
      </dl>
      <?php endforeach; ?>
      <?php if ($features !== []): ?>
      <h3 class="im-subtitle"><?= e(__('properties.sections.features')) ?></h3>
      <p class="im-chips"><?php foreach ($features as $feature): ?><span class="im-tag"><?= e($feature) ?></span> <?php endforeach; ?></p>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($property['latitude'] !== null): ?>
    <section class="card im-panel">
      <h2 class="im-panel__title"><?= e(__('properties.sections.map')) ?></h2>
      <p class="im-panel__subtitle"><?= e((int) $property['show_exact_location'] === 1 ? __('properties.map_exact') : __('properties.map_approximate')) ?></p>
      <div class="im-map" id="property-map" data-lat="<?= e($property['latitude']) ?>" data-lng="<?= e($property['longitude']) ?>" aria-label="<?= e(__('properties.sections.map')) ?>"></div>
    </section>
    <?php endif; ?>

    <?php if (array_filter($private) !== []): ?>
    <section class="card im-panel">
      <h2 class="im-panel__title"><?= e(__('properties.sections.private')) ?></h2>
      <p class="im-private-zone__label"><span class="mdi mdi-lock-outline" aria-hidden="true"></span> <?= e(__('properties.private_notice')) ?></p>
      <dl class="im-detail-list">
        <?php foreach (['owner_name' => 'properties.fields.owner_name', 'owner_phone' => 'properties.fields.owner_phone', 'owner_email' => 'properties.fields.owner_email', 'notary_name' => 'properties.fields.notary_name', 'notary_reference' => 'properties.fields.notary_reference', 'internal_notes' => 'properties.fields.internal_notes'] as $key => $label): ?>
        <?php if (!empty($private[$key])): ?><div><dt><?= e(__($label)) ?></dt><dd><?= nl2br(e($private[$key])) ?></dd></div><?php endif; ?>
        <?php endforeach; ?>
      </dl>
    </section>
    <?php endif; ?>

    <section class="card im-panel">
      <h2 class="im-panel__title"><?= e(__('properties.sections.history')) ?></h2>
      <ol class="im-timeline">
        <?php foreach ($history as $entry): ?>
        <li>
          <span class="im-timeline__dot" aria-hidden="true"></span>
          <div>
            <p class="im-timeline__title"><?= e(__('properties.history.' . $entry['to_status'], ['from' => $entry['from_status'] !== null ? __('properties.status.' . $entry['from_status']) : ''])) ?></p>
            <p class="im-timeline__meta"><?= e($date($entry['created_at'])) ?> · <?= e($entry['user_name'] ?? __('properties.history.system')) ?></p>
            <?php if ($entry['reason'] !== null): ?><p class="im-timeline__reason"><?= e($entry['reason']) ?></p><?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>

  <aside class="im-form__aside">
    <div class="im-sticky">
      <?php if ($needsReview): ?>
      <section class="card im-panel im-panel--review" id="validation-actions">
        <h2 class="im-panel__title"><?= e(__($revision !== null ? 'properties.review_revision' : 'properties.review')) ?></h2>
        <p class="im-account__help"><?= e(__($revision !== null ? 'properties.review_revision_help' : 'properties.review_help', ['days' => $lifetimeDays])) ?></p>
        <form method="post" action="<?= e($url('/valider')) ?>" class="d-grid mb-2">
          <?= csrf_field() ?>
          <button class="btn btn-primary" type="submit"><span class="mdi mdi-check-decagram-outline" aria-hidden="true"></span> <?= e(__($revision !== null ? 'properties.actions.approve_revision' : 'properties.actions.approve')) ?></button>
        </form>
        <form method="post" action="<?= e($url('/rejeter')) ?>">
          <?= csrf_field() ?>
          <?= cmsadmin_partial('field', ['name' => 'reason', 'type' => 'textarea', 'label' => __('properties.reject_reason'), 'hint' => __('properties.reject_reason_hint'), 'required' => true, 'attributes' => ['maxlength' => 2000, 'rows' => 3]]) ?>
          <button class="btn im-btn-ghost im-btn-danger w-100" type="submit"><?= e(__('properties.actions.reject')) ?></button>
        </form>
      </section>
      <?php endif; ?>

      <section class="card im-panel">
        <h2 class="im-panel__title"><?= e(__('cmsadmin.publication')) ?></h2>
        <dl class="im-meta-list">
          <div><dt><?= e(__('cmsadmin.state')) ?></dt><dd><?= cmsadmin_partial('status-badge', ['status' => $status]) ?></dd></div>
          <?php if ($property['published_at'] !== null): ?><div><dt><?= e(__('properties.published_at')) ?></dt><dd><?= e(substr((string) $property['published_at'], 0, 10)) ?></dd></div><?php endif; ?>
          <?php if ($property['expires_at'] !== null && in_array($status, ['published', 'expired'], true)): ?><div><dt><?= e(__('properties.expires_at')) ?></dt><dd><?= e(substr((string) $property['expires_at'], 0, 10)) ?></dd></div><?php endif; ?>
          <div><dt><?= e(__('properties.views')) ?></dt><dd class="im-num"><?= e(format_number((int) $property['views_count'])) ?></dd></div>
          <div><dt><?= e(__('properties.leads')) ?></dt><dd class="im-num"><?= e(format_number((int) $property['leads_count'])) ?></dd></div>
          <div><dt><?= e(__('properties.created_by')) ?></dt><dd><?= e($property['created_by_name'] ?? '—') ?></dd></div>
        </dl>

        <div class="d-grid gap-2">
          <?php if (in_array($status, ['published', 'expired'], true)): ?>
          <form method="post" action="<?= e($url('/prolonger')) ?>">
            <?= csrf_field() ?>
            <button class="btn im-btn-ghost w-100" type="submit"><span class="mdi mdi-calendar-refresh-outline" aria-hidden="true"></span> <?= e(__('properties.actions.extend', ['days' => $lifetimeDays])) ?></button>
          </form>
          <?php endif; ?>
          <?php if ($isStaff && $status === 'published'): ?>
          <form method="post" action="<?= e($url('/depublier')) ?>">
            <?= csrf_field() ?>
            <button class="btn im-btn-ghost w-100" type="submit" data-confirm="<?= e(__('properties.confirm.unpublish')) ?>"><span class="mdi mdi-eye-off-outline" aria-hidden="true"></span> <?= e(__('properties.actions.unpublish')) ?></button>
          </form>
          <?php endif; ?>
          <?php if (!$isStaff && $status === 'published'): ?>
          <form method="post" action="<?= e($url('/desactiver')) ?>">
            <?= csrf_field() ?>
            <button class="btn im-btn-ghost w-100" type="submit" data-confirm="<?= e(__('properties.confirm.deactivate')) ?>"><span class="mdi mdi-eye-off-outline" aria-hidden="true"></span> <?= e(__('properties.actions.deactivate')) ?></button>
          </form>
          <?php endif; ?>
          <?php if (!$isStaff && $status === 'unpublished' && (int) ($property['deactivated_by_partner'] ?? 0) === 1): ?>
          <form method="post" action="<?= e($url('/reactiver')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary w-100" type="submit"><span class="mdi mdi-eye-outline" aria-hidden="true"></span> <?= e(__('properties.actions.reactivate')) ?></button>
          </form>
          <?php endif; ?>
          <?php if ($isStaff && in_array($status, ['unpublished', 'expired'], true)): ?>
          <form method="post" action="<?= e($url('/republier')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary w-100" type="submit"><span class="mdi mdi-eye-outline" aria-hidden="true"></span> <?= e(__('properties.actions.republish')) ?></button>
          </form>
          <?php endif; ?>
          <?php if (in_array($status, ['published', 'unpublished', 'expired'], true)): ?>
          <form method="post" action="<?= e($url('/archiver')) ?>" class="im-archive-form">
            <?= csrf_field() ?>
            <label class="visually-hidden" for="archive-availability"><?= e(__('properties.archive_as')) ?></label>
            <select class="form-select form-select-sm" id="archive-availability" name="availability">
              <option value="sold"><?= e(__('properties.availability.sold')) ?></option>
              <option value="rented"><?= e(__('properties.availability.rented')) ?></option>
            </select>
            <button class="btn im-btn-ghost" type="submit" data-confirm="<?= e(__('properties.confirm.archive')) ?>"><span class="mdi mdi-archive-outline" aria-hidden="true"></span> <?= e(__('properties.actions.archive')) ?></button>
          </form>
          <?php endif; ?>
        </div>
      </section>

      <?php if ($isStaff): ?>
      <section class="card im-panel">
        <h2 class="im-panel__title"><?= e(__('properties.promotion')) ?></h2>
        <form method="post" action="<?= e($url('/mise-en-avant')) ?>">
          <?= csrf_field() ?>
          <?php if ((int) $property['is_featured'] === 1): ?>
          <p class="im-account__help"><?= e($property['featured_until'] !== null ? __('properties.featured_until', ['date' => substr((string) $property['featured_until'], 0, 10)]) : __('properties.featured_no_end')) ?></p>
          <button class="btn im-btn-ghost w-100" type="submit"><span class="mdi mdi-star-off-outline" aria-hidden="true"></span> <?= e(__('properties.actions.unfeature')) ?></button>
          <?php else: ?>
          <?= cmsadmin_partial('field', ['name' => 'featured_until', 'type' => 'date', 'label' => __('properties.featured_until_label'), 'optional' => true, 'hint' => __('properties.featured_hint')]) ?>
          <button class="btn im-btn-ghost w-100" type="submit"<?= $status !== 'published' ? ' disabled' : '' ?>><span class="mdi mdi-star-outline" aria-hidden="true"></span> <?= e(__('properties.actions.feature')) ?></button>
          <?php endif; ?>
        </form>
      </section>
      <?php endif; ?>

      <?php if ($isStaff || in_array($status, ['draft', 'pending', 'rejected'], true)): ?>
      <form method="post" action="<?= e($url('/supprimer')) ?>" class="im-danger-zone">
        <?= csrf_field() ?>
        <button class="btn im-btn-ghost im-btn-danger w-100" type="submit" data-confirm="<?= e(__('properties.confirm.delete', ['ref' => $property['reference']])) ?>"><span class="mdi mdi-trash-can-outline" aria-hidden="true"></span> <?= e(__('properties.actions.delete')) ?></button>
        <p class="form-text"><?= e(__('properties.delete_hint')) ?></p>
      </form>
      <?php endif; ?>
    </div>
  </aside>
</div>
