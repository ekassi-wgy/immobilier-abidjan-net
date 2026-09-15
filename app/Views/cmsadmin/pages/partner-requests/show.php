<?php
/**
 * Détail d'une demande « Devenir partenaire ».
 *
 * @var array<string,mixed>  $item
 * @var array<string,string> $errors
 * @var bool                 $emailInUse Un compte utilise déjà l'adresse du demandeur
 */
$variant = ['new' => 'archived', 'contacted' => 'pending', 'approved' => 'published', 'rejected' => 'unpublished'];
$approved = $item['status'] === 'approved';
$whatsapp = preg_replace('/[^0-9]/', '', (string) $item['phone']);
?>
<?= cmsadmin_partial('page-header', [
    'title' => $item['agency_name'],
    'subtitle' => __('partners.received_on', ['date' => substr((string) $item['created_at'], 0, 16)]),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('partners.title'), 'url' => 'demandes-partenariat'], ['label' => $item['agency_name']]],
]) ?>

<div class="im-form__layout">
  <div class="im-form__main">
    <section class="card im-panel">
      <div class="im-request-head">
        <h2 class="im-panel__title"><?= e(__('partners.request')) ?></h2>
        <?= cmsadmin_partial('state-badge', ['label' => __('partners.status.' . $item['status']), 'variant' => $variant[$item['status']]]) ?>
      </div>
      <dl class="im-detail-list">
        <div><dt><?= e(__('partners.contact')) ?></dt><dd><?= e($item['contact_name']) ?></dd></div>
        <div><dt><?= e(__('auth.email')) ?></dt><dd><a href="mailto:<?= e($item['email']) ?>"><?= e($item['email']) ?></a></dd></div>
        <div><dt><?= e(__('sites.phone')) ?></dt><dd><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $item['phone'])) ?>"><?= e($item['phone']) ?></a><?php if ($whatsapp !== ''): ?> · <a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></dd></div>
        <div><dt><?= e(__('agencies.rccm')) ?></dt><dd><?= e($item['rccm'] ?? '—') ?></dd></div>
        <div><dt><?= e(__('agencies.location')) ?></dt><dd><?= e(trim(($item['commune_name'] ?? '') . ' · ' . ($item['city_name'] ?? ''), ' ·') ?: '—') ?></dd></div>
        <div><dt><?= e(__('partners.listings')) ?></dt><dd class="im-num"><?= e($item['listings_estimate'] ?? '—') ?></dd></div>
        <div><dt><?= e(__('partners.consent')) ?></dt><dd><?= e(substr((string) $item['consent_at'], 0, 16)) ?> UTC</dd></div>
      </dl>
      <?php if ($item['message'] !== null && trim((string) $item['message']) !== ''): ?>
      <h3 class="im-subtitle"><?= e(__('partners.message')) ?></h3>
      <blockquote class="im-quote"><?= nl2br(e($item['message'])) ?></blockquote>
      <?php endif; ?>
    </section>

    <form class="card im-panel" method="post" action="<?= e(cmsadmin_url('demandes-partenariat/' . $item['id'])) ?>" novalidate>
      <?= csrf_field() ?>
      <h2 class="im-panel__title"><?= e(__('partners.follow_up')) ?></h2>
      <?php if ($approved): ?>
      <p class="im-note mt-3"><span class="mdi mdi-check-decagram-outline" aria-hidden="true"></span> <?= e(__('partners.approved_note', ['agency' => $item['created_agency_name'] ?? '—', 'user' => $item['handled_by_name'] ?? '—'])) ?></p>
      <?php else: ?>
      <fieldset class="mt-3 mb-3">
        <legend class="form-label"><?= e(__('cmsadmin.state')) ?></legend>
        <div class="im-segmented" role="radiogroup">
          <?php foreach (['new', 'contacted', 'rejected'] as $status): ?>
          <label class="im-segmented__option"><input type="radio" name="status" value="<?= e($status) ?>"<?= $item['status'] === $status ? ' checked' : '' ?>><span><?= e(__('partners.status.' . $status)) ?></span></label>
          <?php endforeach; ?>
        </div>
        <?php if (isset($errors['status'])): ?><p class="invalid-feedback d-block"><?= e($errors['status']) ?></p><?php endif; ?>
      </fieldset>
      <?php endif; ?>
      <?= cmsadmin_partial('field', ['name' => 'internal_notes', 'type' => 'textarea', 'label' => __('partners.notes'), 'value' => $item['internal_notes'] ?? '', 'optional' => true, 'hint' => __('partners.notes_hint'), 'error' => $errors['internal_notes'] ?? null, 'attributes' => ['maxlength' => 5000]]) ?>
      <?php if ($approved): ?><input type="hidden" name="status" value="approved"><?php endif; ?>
      <div><button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.save')) ?></button></div>
    </form>
  </div>

  <aside class="im-form__aside">
    <div class="im-sticky">
      <section class="card im-panel">
        <h2 class="im-panel__title"><?= e(__('partners.next_step')) ?></h2>
        <?php if ($approved && $item['agency_id'] !== null): ?>
        <p class="im-account__help"><?= e(__('partners.agency_created')) ?></p>
        <a class="btn btn-primary w-100" href="<?= e(cmsadmin_url('agences/' . $item['agency_id'] . '/modifier')) ?>"><?= e(__('partners.open_agency')) ?></a>
        <?php elseif ($item['status'] === 'rejected'): ?>
        <p class="im-account__help"><?= e(__('partners.rejected_help')) ?></p>
        <?php else: ?>
        <p class="im-account__help"><?= e(__('partners.create_help')) ?></p>
        <?php if ($emailInUse): ?><p class="im-note"><span class="mdi mdi-account-alert-outline" aria-hidden="true"></span> <?= e(__('partners.email_in_use')) ?></p><?php endif; ?>
        <a class="btn btn-primary w-100" href="<?= e(cmsadmin_url('agences/ajouter?demande=' . $item['id'])) ?>"><span class="mdi mdi-office-building-plus-outline" aria-hidden="true"></span> <?= e(__('partners.create_agency')) ?></a>
        <?php endif; ?>
      </section>
    </div>
  </aside>
</div>
