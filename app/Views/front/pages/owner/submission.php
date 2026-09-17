<?php
/**
 * Suivi d'un bien confié à Weblogy, côté particulier.
 *
 * @var App\Models\User           $user
 * @var array<string,mixed>       $submission
 * @var list<array<string,mixed>> $photos
 * @var list<array<string,mixed>> $documents
 * @var array                     $flash
 * @var string                    $csrfToken
 */
$s = $submission;
$status = App\Services\SubmissionRepository::ownerStatus($s);
$steps = ['received', 'in_review', 'preparing', 'online'];
$reached = array_search($status['code'], $steps, true);
$withdrawable = in_array($s['status'], ['submitted', 'in_review'], true) && $s['property_id'] === null;
$publicUrl = $status['code'] === 'online' && $s['property_slug'] !== null ? url('annonces/' . $s['property_slug'] . '-ref' . $s['property_id']) : null;
?>
<section class="im-section im-section--tight">
  <div class="im-container">
    <?= render_view('front/partials/owner-nav', ['user' => $user, 'current' => 'biens', 'csrfToken' => $csrfToken]) ?>
    <?= render_view('front/partials/flash', ['flash' => $flash]) ?>

    <a class="im-link im-owner-back" href="<?= e(url('mon-espace')) ?>"><?= icon('arrow-left') ?> <?= e(__('owner.submission.back')) ?></a>

    <div class="im-owner-grid im-owner-grid--wide">
      <div>
        <div class="im-owner-panel">
          <div class="im-owner-panel__head">
            <div>
              <p class="im-eyebrow"><?= e(__('owner.submission.show_title', ['id' => $s['id']])) ?></p>
              <h2 class="im-h3"><?= e($s['category_name'] . ' · ' . $s['transaction_name']) ?></h2>
              <p class="im-muted"><?= e(trim(implode(' · ', array_filter([$s['commune_name'], $s['city_name']])))) ?></p>
            </div>
            <span class="im-badge im-badge--<?= e($status['variant']) ?>"><?= e($status['label']) ?></span>
          </div>

          <?php if ($reached !== false): ?>
          <ol class="im-progress-steps" aria-label="<?= e(__('owner.submission.progress')) ?>">
            <?php foreach ($steps as $index => $step): ?>
            <li class="<?= $index <= $reached ? 'is-done' : '' ?><?= $index === $reached ? ' is-current' : '' ?>"<?= $index === $reached ? ' aria-current="step"' : '' ?>>
              <span class="im-progress-steps__dot" aria-hidden="true"><?= $index < $reached ? icon('check') : '' ?></span>
              <span><?= e(__('owner.status.' . $step)) ?></span>
            </li>
            <?php endforeach; ?>
          </ol>
          <?php endif; ?>

          <p class="im-owner-explain"><?= e(__('owner.status_help.' . $status['code'], ['site' => site()->name ?? ''])) ?></p>
          <?php if ($s['status'] === 'rejected' && !empty($s['rejection_reason'])): ?>
          <blockquote class="im-owner-quote"><?= nl2br(e((string) $s['rejection_reason'])) ?></blockquote>
          <?php endif; ?>
          <?php if ($publicUrl !== null): ?>
          <a class="im-btn" href="<?= e($publicUrl) ?>"><?= e(__('owner.submission.view_listing')) ?> <?= icon('arrow-up-right') ?></a>
          <?php endif; ?>
        </div>

        <?php if ($photos !== []): ?>
        <div class="im-owner-panel">
          <h3 class="im-h4"><?= e(__n('owner.submission.photos_count', count($photos))) ?></h3>
          <ul class="im-owner-photos">
            <?php foreach ($photos as $photo): ?>
            <li><img src="<?= e(url('mon-espace/biens/' . $s['id'] . '/photos/' . $photo['id'])) ?>" alt="" loading="lazy" width="240" height="180"></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <div class="im-owner-panel">
          <h3 class="im-h4"><?= e(__('owner.submission.description')) ?></h3>
          <p class="im-owner-text"><?= nl2br(e((string) $s['description'])) ?></p>
        </div>
      </div>

      <aside>
        <div class="im-owner-panel">
          <h3 class="im-h4"><?= e(__('owner.submission.summary')) ?></h3>
          <dl class="im-owner-facts">
            <?php if ($s['price'] !== null): ?><div><dt><?= e(__('owner.submission.price_short')) ?></dt><dd class="im-num"><?= e(format_price($s['price'])) ?><?= $s['price_period'] !== 'total' ? ' ' . e(price_period_label((string) $s['price_period'])) : '' ?><?= (int) $s['is_negotiable'] === 1 ? ' · ' . e(__('owner.submission.negotiable_short')) : '' ?></dd></div><?php endif; ?>
            <?php if ($s['living_area'] !== null): ?><div><dt><?= e(__('owner.submission.living_area')) ?></dt><dd class="im-num"><?= e(format_decimal((float) $s['living_area'], 0)) ?> m²</dd></div><?php endif; ?>
            <?php if ($s['land_area'] !== null): ?><div><dt><?= e(__('owner.submission.land_area')) ?></dt><dd class="im-num"><?= e(format_decimal((float) $s['land_area'], 0)) ?> m²</dd></div><?php endif; ?>
            <?php if ($s['rooms'] !== null): ?><div><dt><?= e(__('owner.submission.rooms')) ?></dt><dd class="im-num"><?= e($s['rooms']) ?></dd></div><?php endif; ?>
            <?php if ($s['bedrooms'] !== null): ?><div><dt><?= e(__('owner.submission.bedrooms')) ?></dt><dd class="im-num"><?= e($s['bedrooms']) ?></dd></div><?php endif; ?>
            <div><dt><?= e(__('owner.submission.documents')) ?></dt><dd class="im-num"><?= e((string) count($documents)) ?></dd></div>
            <div><dt><?= e(__('owner.submission.sent_on')) ?></dt><dd><?= e(format_date((string) $s['created_at'])) ?></dd></div>
          </dl>
          <?php if ($s['conditions'] !== null): ?><p class="im-owner-text im-small"><?= nl2br(e((string) $s['conditions'])) ?></p><?php endif; ?>
        </div>

        <?php if ($withdrawable): ?>
        <form class="im-owner-panel im-owner-panel--quiet" method="post" action="<?= e(url('mon-espace/biens/' . $s['id'] . '/retirer')) ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
          <p class="im-small im-muted"><?= e(__('owner.submission.withdraw_help')) ?></p>
          <button class="im-btn im-btn--ghost im-btn--sm" type="submit"><?= e(__('owner.submission.withdraw')) ?></button>
        </form>
        <?php endif; ?>

        <div class="im-owner-panel im-owner-panel--quiet">
          <p class="im-small"><?= e(__('owner.submission.questions', ['site' => site()->name ?? ''])) ?></p>
          <?php if (site()?->contactPhone !== null): ?><a class="im-link" href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) site()->contactPhone)) ?>"><?= icon('phone') ?> <?= e((string) site()->contactPhone) ?></a><?php endif; ?>
        </div>
      </aside>
    </div>
  </div>
</section>
