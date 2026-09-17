<?php
/**
 * Espace propriétaire : biens confiés à Weblogy et leur état d'avancement.
 *
 * @var App\Models\User           $user
 * @var list<array<string,mixed>> $submissions
 * @var array                     $flash
 * @var string                    $csrfToken
 */
?>
<section class="im-section im-section--tight">
  <div class="im-container">
    <?= render_view('front/partials/owner-nav', ['user' => $user, 'current' => 'biens', 'csrfToken' => $csrfToken]) ?>
    <?= render_view('front/partials/flash', ['flash' => $flash]) ?>

    <?php if (!$user->hasVerifiedEmail()): ?>
    <div class="im-note-band im-note-band--warning">
      <p><?= icon('mail') ?> <?= e(__('owner.verify.banner', ['email' => $user->email])) ?></p>
      <form method="post" action="<?= e(url('mon-espace/renvoyer-confirmation')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button class="im-btn im-btn--outline im-btn--sm" type="submit"><?= e(__('owner.verify.resend')) ?></button>
      </form>
    </div>
    <?php endif; ?>

    <div class="im-owner-section-head">
      <div>
        <h2 class="im-h3"><?= e(__('owner.dashboard.properties_title')) ?></h2>
        <p class="im-muted"><?= e(__('owner.dashboard.properties_lead', ['site' => site()->name ?? ''])) ?></p>
      </div>
      <?php if ($user->hasVerifiedEmail()): ?>
      <a class="im-btn" href="<?= e(url('mon-espace/biens/nouveau')) ?>"><?= icon('plus') ?> <?= e(__('owner.dashboard.new')) ?></a>
      <?php endif; ?>
    </div>

    <?php if ($submissions === []): ?>
    <div class="im-empty">
      <span class="im-empty__icon" aria-hidden="true"><?= icon('key') ?></span>
      <p class="im-h3"><?= e(__('owner.dashboard.empty_title')) ?></p>
      <p class="im-lead"><?= e(__('owner.dashboard.empty_text')) ?></p>
      <?php if ($user->hasVerifiedEmail()): ?>
      <a class="im-btn" href="<?= e(url('mon-espace/biens/nouveau')) ?>"><?= e(__('owner.landing.cta_submit')) ?></a>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <ul class="im-owner-list">
      <?php foreach ($submissions as $row): $status = App\Services\SubmissionRepository::ownerStatus($row); ?>
      <li>
        <a class="im-owner-item" href="<?= e(url('mon-espace/biens/' . $row['id'])) ?>">
          <span class="im-owner-item__media">
            <?php if ((int) $row['photos_count'] > 0): ?>
            <img src="<?= e(url('mon-espace/biens/' . $row['id'] . '/photos/' . $row['cover_id'])) ?>" alt="" width="160" height="120" loading="lazy">
            <?php else: ?>
            <?= icon('house') ?>
            <?php endif; ?>
          </span>
          <span class="im-owner-item__body">
            <span class="im-owner-item__title"><?= e($row['category_name'] . ' · ' . $row['transaction_name']) ?></span>
            <span class="im-owner-item__meta"><?= e(trim(implode(' · ', array_filter([$row['commune_name'], $row['city_name']])))) ?></span>
            <span class="im-owner-item__meta"><?= e(__('owner.dashboard.sent_on', ['date' => format_date((string) $row['created_at']), 'id' => $row['id']])) ?></span>
          </span>
          <span class="im-badge im-badge--<?= e($status['variant']) ?>"><?= e($status['label']) ?></span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</section>
