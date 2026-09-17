<?php
/**
 * Détail d'une demande de contact : message reçu, coordonnées, suivi.
 *
 * @var array<string,mixed>  $lead
 * @var array<string,string> $errors
 * @var array<int,string>    $users  Personnes à qui confier la demande
 * @var string               $back   Retour à la liste en conservant ses filtres
 */
$statusVariant = ['new' => 'pending', 'read' => 'archived', 'in_progress' => 'published', 'closed' => 'unpublished', 'spam' => 'rejected'];
$statuses = ['new', 'read', 'in_progress', 'closed', 'spam'];
$phone = preg_replace('/[^0-9+]/', '', (string) $lead['phone']);
$whatsapp = preg_replace('/[^0-9]/', '', (string) $lead['phone']);
$payload = $lead['payload'] !== null ? json_decode((string) $lead['payload'], true) : null;
?>
<?= cmsadmin_partial('page-header', [
    'title' => $lead['name'],
    'subtitle' => __('leads.received_on', ['date' => substr((string) $lead['created_at'], 0, 16)]),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('leads.title'), 'url' => 'contacts'], ['label' => $lead['name']]],
]) ?>

<div class="im-form__layout im-form__layout--read-first">
  <div class="im-form__main">
    <section class="card im-panel">
      <div class="im-request-head">
        <h2 class="im-panel__title"><?= e(__('leads.type.' . $lead['type'])) ?></h2>
        <?= cmsadmin_partial('state-badge', ['label' => __('leads.status.' . $lead['status']), 'variant' => $statusVariant[$lead['status']] ?? 'unpublished']) ?>
      </div>
      <dl class="im-detail-list">
        <div><dt><?= e(__('leads.sender')) ?></dt><dd><?= e($lead['name']) ?></dd></div>
        <?php if ($lead['email'] !== null): ?>
        <div><dt><?= e(__('auth.email')) ?></dt><dd><a href="mailto:<?= e($lead['email']) ?>"><?= e($lead['email']) ?></a></dd></div>
        <?php endif; ?>
        <?php if ($lead['phone'] !== null): ?>
        <div><dt><?= e(__('sites.phone')) ?></dt><dd><a href="tel:<?= e($phone) ?>"><?= e($lead['phone']) ?></a><?php if ($whatsapp !== ''): ?> · <a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></dd></div>
        <?php endif; ?>
        <?php if ($lead['property_reference'] !== null): ?>
        <div><dt><?= e(__('properties.singular')) ?></dt><dd><a href="<?= e(cmsadmin_url('annonces/' . $lead['property_reference'])) ?>"><?= e($lead['property_title']) ?></a> <span class="im-cell-sub"><?= e($lead['property_reference']) ?></span></dd></div>
        <?php endif; ?>
        <?php if ($lead['agency_name'] !== null): ?>
        <div><dt><?= e(__('leads.partner')) ?></dt><dd><a href="<?= e(cmsadmin_url('agences/' . $lead['agency_id'] . '/modifier')) ?>"><?= e($lead['agency_name']) ?></a></dd></div>
        <?php endif; ?>
        <div><dt><?= e(__('partners.consent')) ?></dt><dd><?= e(substr((string) $lead['consent_at'], 0, 16)) ?> UTC</dd></div>
        <?php if ($lead['source_url'] !== null): ?>
        <div><dt><?= e(__('leads.source')) ?></dt><dd class="im-cell-sub-text"><?= e($lead['source_url']) ?></dd></div>
        <?php endif; ?>
      </dl>

      <?php
      // Weblogy sollicite lui-même le partenaire : contact propre à l'annonce, sinon celui de l'agence.
      $partnerContacts = array_values(array_filter([
          $lead['property_contact_name'] ?? null,
          $lead['property_contact_phone'] ?? $lead['agency_phone'] ?? null,
          $lead['property_contact_email'] ?? $lead['agency_email'] ?? null,
      ]));
      ?>
      <?php if ($partnerContacts !== []): ?>
      <p class="im-note"><span class="mdi mdi-account-tie-outline" aria-hidden="true"></span> <?= e(__('leads.partner_contact', ['contact' => implode(' · ', $partnerContacts)])) ?></p>
      <?php endif; ?>

      <?php if ($lead['message'] !== null && trim((string) $lead['message']) !== ''): ?>
      <h3 class="im-subtitle"><?= e(__('leads.message')) ?></h3>
      <blockquote class="im-quote"><?= nl2br(e($lead['message'])) ?></blockquote>
      <?php endif; ?>

      <?php if (is_array($payload) && $payload !== []): ?>
      <h3 class="im-subtitle"><?= e(__('leads.details')) ?></h3>
      <dl class="im-detail-list">
        <?php foreach ($payload as $key => $detail): ?>
        <?php $label = 'leads.payload.' . $key; ?>
        <div><dt><?= e(app()->translator()->has($label) ? __($label) : (string) $key) ?></dt><dd><?= e(is_scalar($detail) ? (string) $detail : json_encode($detail, JSON_UNESCAPED_UNICODE)) ?></dd></div>
        <?php endforeach; ?>
      </dl>
      <?php endif; ?>
    </section>
  </div>

  <aside class="im-form__aside">
    <div class="im-sticky">
      <form class="card im-panel" method="post" action="<?= e(cmsadmin_url('contacts/' . $lead['id'])) ?>" novalidate>
        <?= csrf_field() ?>
        <?php if ($back !== ''): ?><input type="hidden" name="_back" value="<?= e($back) ?>"><?php endif; ?>
        <h2 class="im-panel__title"><?= e(__('leads.follow_up')) ?></h2>
        <fieldset class="mt-3 mb-3">
          <legend class="form-label"><?= e(__('cmsadmin.state')) ?></legend>
          <div class="im-segmented im-segmented--stack" role="radiogroup">
            <?php foreach ($statuses as $status): ?>
            <label class="im-segmented__option"><input type="radio" name="status" value="<?= e($status) ?>"<?= $lead['status'] === $status ? ' checked' : '' ?>><span><?= e(__('leads.status.' . $status)) ?></span></label>
            <?php endforeach; ?>
          </div>
          <?php if (isset($errors['status'])): ?><p class="invalid-feedback d-block"><?= e($errors['status']) ?></p><?php endif; ?>
        </fieldset>
        <?= cmsadmin_partial('field', [
            'name' => 'assigned_user_id',
            'type' => 'select',
            'label' => __('leads.assigned'),
            'options' => $users,
            'placeholder' => __('leads.unassigned'),
            'value' => $lead['assigned_user_id'],
            'optional' => true,
            'hint' => __('leads.assigned_hint'),
            'error' => $errors['assigned_user_id'] ?? null,
        ]) ?>
        <?php if ($lead['handled_at'] !== null): ?>
        <p class="form-text"><?= e(__('leads.handled_on', ['date' => substr((string) $lead['handled_at'], 0, 16)])) ?></p>
        <?php endif; ?>
        <div class="d-grid gap-2">
          <button class="btn btn-primary" type="submit"><?= e(__('cmsadmin.save')) ?></button>
          <a class="btn im-btn-ghost" href="<?= e($back !== '' ? url($back) : cmsadmin_url('contacts')) ?>"><?= e(__('leads.back_to_list')) ?></a>
        </div>
      </form>

      <?php if ($lead['email'] !== null || $lead['phone'] !== null): ?>
      <section class="card im-panel">
        <h2 class="im-panel__title"><?= e(__('leads.reply')) ?></h2>
        <p class="im-account__help"><?= e(__('leads.reply_hint')) ?></p>
        <div class="d-grid gap-2">
          <?php if ($lead['email'] !== null): ?>
          <a class="btn im-btn-ghost" href="mailto:<?= e($lead['email']) ?>"><span class="mdi mdi-email-outline" aria-hidden="true"></span> <?= e(__('leads.reply_email')) ?></a>
          <?php endif; ?>
          <?php if ($whatsapp !== ''): ?>
          <a class="btn im-btn-ghost" href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener"><span class="mdi mdi-whatsapp" aria-hidden="true"></span> WhatsApp</a>
          <?php endif; ?>
          <?php if ($lead['phone'] !== null): ?>
          <a class="btn im-btn-ghost" href="tel:<?= e($phone) ?>"><span class="mdi mdi-phone-outline" aria-hidden="true"></span> <?= e(__('leads.reply_call')) ?></a>
          <?php endif; ?>
        </div>
      </section>
      <?php endif; ?>
    </div>
  </aside>
</div>
