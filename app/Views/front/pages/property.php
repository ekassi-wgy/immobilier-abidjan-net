<?php

/**
 * Fiche annonce publique (lot 1.10) : /annonces/{slug}-ref{id}
 *
 * @var array  $property   ListingPresenter::detail()
 * @var array  $similar    Cartes annonce
 * @var array  $breadcrumb [['label', 'path', 'current']]
 * @var array  $errors
 * @var array  $old
 * @var array  $flash
 * @var string $csrfToken
 * @var string $shareUrl
 */
$p = $property;
$availability = $p['availability'];
?>
<article class="im-property">

  <div class="im-property__top">
    <div class="im-container">
      <nav class="im-breadcrumb" aria-label="<?= e(__('front.results.breadcrumb_label')) ?>">
        <ol>
          <?php foreach ($breadcrumb as $item): ?>
          <li>
            <?php if ($item['current']): ?>
            <span aria-current="page"><?= e($item['label']) ?></span>
            <?php else: ?>
            <a href="<?= e(url($item['path'])) ?>"><?= e($item['label']) ?></a>
            <?= icon('caret-right') ?>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ol>
      </nav>
    </div>
  </div>

  <div class="im-container">
    <?= render_view('front/partials/property-gallery', ['gallery' => $p['gallery'], 'title' => $p['title']]) ?>
  </div>

  <div class="im-container im-property__layout">
    <div class="im-property__main">

      <header class="im-property__header">
        <p class="im-property__eyebrow">
          <span><?= e($p['category']) ?> · <?= e($p['transaction']) ?></span>
          <?php foreach ($p['badges'] as $badge): ?>
          <span class="im-badge im-badge--<?= e($badge['variant']) ?>"><?= e($badge['label']) ?></span>
          <?php endforeach; ?>
          <?php if ($availability !== 'available'): ?>
          <span class="im-badge im-badge--neutral"><?= e(__('front.property.availability.' . $availability)) ?></span>
          <?php endif; ?>
        </p>

        <h1 class="im-h2 im-property__title"><?= e($p['title']) ?></h1>

        <p class="im-property__location"><?= icon('pin') ?> <?= e($p['location']) ?><?= $p['address'] !== null ? ' · ' . e($p['address']) : '' ?></p>

        <div class="im-property__pricing">
          <p class="im-property__price im-num">
            <?= e($p['price_label']) ?><?php if (price_period_label($p['period']) !== ''): ?><span class="im-property__period"><?= e(price_period_label($p['period'])) ?></span><?php endif; ?>
          </p>
          <ul class="im-property__price-notes">
            <?php if ($p['negotiable']): ?><li><?= e(__('front.property.negotiable')) ?></li><?php endif; ?>
            <?php if ($p['charges'] !== null): ?><li><?= e(__('front.property.charges', ['amount' => format_price($p['charges'])])) ?></li><?php endif; ?>
            <?php if ($p['fee_percent'] !== null): ?><li><?= e(__('front.property.fee', ['percent' => format_decimal($p['fee_percent'])])) ?></li><?php endif; ?>
          </ul>
        </div>

        <div class="im-property__meta">
          <span class="im-property__reference"><?= e(__('front.property.reference', ['reference' => $p['reference']])) ?></span>
          <?php if ($p['published_at'] !== null): ?>
          <span><?= e(__('front.property.published_at', ['date' => format_date($p['published_at'])])) ?></span>
          <?php endif; ?>
          <button class="im-chip im-property__fav" type="button" aria-pressed="false" data-favorite="<?= e($p['reference']) ?>">
            <?= icon('heart') ?> <span><?= e(__('front.nav.favorites')) ?></span>
          </button>
        </div>
      </header>

      <?php if ($p['specs'] !== []): ?>
      <ul class="im-property__specs">
        <?php foreach ($p['specs'] as $spec): ?>
        <li><?= icon($spec['icon']) ?> <span><?= e($spec['label']) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <section class="im-property__block">
        <h2 class="im-h3"><?= e(__('front.property.description_title')) ?></h2>
        <div class="im-property__text"><?= nl2br(e($p['description'])) ?></div>
        <?php if ($p['available_from'] !== null): ?>
        <p class="im-small im-muted"><?= icon('calendar') ?> <?= e(__('front.property.available_from', ['date' => format_date($p['available_from'])])) ?></p>
        <?php endif; ?>
      </section>

      <?php if ($p['criteria'] !== []): ?>
      <section class="im-property__block">
        <h2 class="im-h3"><?= e(__('front.property.criteria_title')) ?></h2>
        <?php foreach ($p['criteria'] as $group): ?>
        <h3 class="im-property__subtitle"><?= e($group['label']) ?></h3>
        <dl class="im-specs-list">
          <?php foreach ($group['items'] as $item): ?>
          <div><dt><?= e($item['label']) ?></dt><dd><?= e($item['value']) ?></dd></div>
          <?php endforeach; ?>
        </dl>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>

      <?php if ($p['legal'] !== null): ?>
      <section class="im-property__block im-legal">
        <h2 class="im-h3"><?= icon('title-deed') ?> <?= e(__('front.property.legal_title')) ?></h2>
        <dl class="im-specs-list">
          <?php foreach ($p['legal']['items'] as $item): ?>
          <div><dt><?= e($item['label']) ?></dt><dd><?= e($item['value']) ?></dd></div>
          <?php endforeach; ?>
        </dl>
        <p class="im-small im-muted im-legal__note"><?= icon('info') ?> <?= e(__('front.property.legal_note')) ?></p>
      </section>
      <?php endif; ?>

      <?php if ($p['features'] !== []): ?>
      <section class="im-property__block">
        <h2 class="im-h3"><?= e(__('front.property.features_title')) ?></h2>
        <?php foreach ($p['features'] as $group => $items): ?>
        <h3 class="im-property__subtitle"><?= e(__('front.filters.feature_group.' . $group)) ?></h3>
        <ul class="im-features">
          <?php foreach ($items as $feature): ?>
          <li><?= icon($feature['icon'] ?? 'check') ?> <span><?= e($feature['label']) ?></span></li>
          <?php endforeach; ?>
        </ul>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>

      <?php if ($p['video'] !== null || $p['tour'] !== null || $p['document'] !== null): ?>
      <section class="im-property__block">
        <h2 class="im-h3"><?= e(__('front.property.media_title')) ?></h2>
        <div class="im-property__media">
          <?php if ($p['video'] !== null): ?>
          <a class="im-btn im-btn--outline" href="<?= e($p['video']) ?>" target="_blank" rel="noopener"><?= icon('video') ?> <?= e(__('front.property.video')) ?></a>
          <?php endif; ?>
          <?php if ($p['tour'] !== null): ?>
          <a class="im-btn im-btn--outline" href="<?= e($p['tour']) ?>" target="_blank" rel="noopener"><?= icon('tour-360') ?> <?= e(__('front.property.tour')) ?></a>
          <?php endif; ?>
          <?php if ($p['document'] !== null): ?>
          <a class="im-btn im-btn--outline" href="<?= e($p['document']) ?>" target="_blank" rel="noopener"><?= icon('document') ?> <?= e(__('front.property.document')) ?></a>
          <?php endif; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php if ($p['map'] !== null): ?>
      <section class="im-property__block">
        <h2 class="im-h3"><?= e(__('front.property.location_title')) ?></h2>
        <div class="im-map im-map--single" id="im-map" role="application" aria-label="<?= e(__('front.property.location_title')) ?>"
             data-point="<?= e(json_encode($p['map'], JSON_UNESCAPED_UNICODE)) ?>"
             data-map-images="<?= e(asset('vendors/leaflet/images/')) ?>"></div>
        <p class="im-small im-muted im-property__map-note">
          <?= icon('info') ?> <?= e(__($p['map']['exact'] ? 'front.property.map_exact' : 'front.property.map_approximate')) ?>
        </p>
      </section>
      <?php endif; ?>
    </div>

    <aside class="im-property__aside">
      <div class="im-property__sticky">
        <?php /* Weblogy est l'interlocuteur unique : le partenaire qui gère le bien n'est jamais présenté. */ ?>
        <section class="im-seller">
          <p class="im-eyebrow"><?= e(__('front.property.seller_title')) ?></p>
          <div class="im-seller__identity">
            <img class="im-seller__logo" src="<?= e(asset('img/brand/logo-symbole.png')) ?>" alt="" width="64" height="64" loading="lazy">
            <div>
              <p class="im-seller__name"><?= e(site()->name ?? '') ?> <?= icon('verified', '', __('front.property.seller_verified')) ?></p>
              <p class="im-small im-muted"><?= e(__('front.property.seller_lead')) ?></p>
            </div>
          </div>
          <p class="im-seller__agent"><?= icon('info') ?> <?= e(__('front.property.seller_reference', ['reference' => $p['reference']])) ?></p>
        </section>

        <?= render_view('front/partials/property-contact', [
            'property' => $p,
            'csrfToken' => $csrfToken,
            'errors' => $errors,
            'old' => $old,
            'flash' => $flash,
        ]) ?>

        <section class="im-share" data-share data-url="<?= e($shareUrl) ?>" data-copied="<?= e(__('front.property.share_copied')) ?>">
          <p class="im-share__title"><?= icon('share') ?> <?= e(__('front.property.share_title')) ?></p>
          <div class="im-share__links">
            <a class="im-share__link" href="https://wa.me/?text=<?= e(rawurlencode($p['title'] . ' — ' . $shareUrl)) ?>" target="_blank" rel="noopener" aria-label="<?= e(__('front.property.share_whatsapp')) ?>"><?= icon('whatsapp') ?></a>
            <a class="im-share__link" href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($shareUrl)) ?>" target="_blank" rel="noopener" aria-label="<?= e(__('front.property.share_facebook')) ?>"><?= icon('facebook') ?></a>
            <a class="im-share__link" href="mailto:?subject=<?= e(rawurlencode($p['title'])) ?>&amp;body=<?= e(rawurlencode($shareUrl)) ?>" aria-label="<?= e(__('front.property.share_email')) ?>"><?= icon('mail') ?></a>
            <button class="im-share__link" type="button" data-share-copy aria-label="<?= e(__('front.property.share_copy')) ?>"><?= icon('link') ?></button>
          </div>
        </section>
      </div>
    </aside>
  </div>

  <?php if ($similar !== []): ?>
  <section class="im-section im-section--snow im-section--tight">
    <div class="im-container">
      <div class="im-section-head">
        <div class="im-section-head__text">
          <h2 class="im-h3"><?= e(__('front.property.similar_title')) ?></h2>
          <p class="im-lead im-section-head__lead"><?= e(__('front.property.similar_lead')) ?></p>
        </div>
      </div>
      <div class="im-grid-cards">
        <?php foreach ($similar as $card): ?>
        <?= render_view('front/partials/property-card', ['property' => $card]) ?>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>
</article>
