<?php

/**
 * Favoris du visiteur : annonces mises de côté, sans compte (cookie + localStorage).
 *
 * @var array $favorites Cartes annonce
 * @var int   $missing   Références du cookie qui ne sont plus en ligne
 */
?>
<section class="im-section im-section--tight">
  <div class="im-container">
    <div class="im-section-head">
      <div class="im-section-head__text">
        <p class="im-eyebrow"><?= e(__('front.nav.favorites')) ?></p>
        <h1 class="im-h2 im-section-head__title"><?= e(__('front.favorites.title')) ?></h1>
        <p class="im-lead im-section-head__lead"><?= e(__('front.favorites.lead')) ?></p>
      </div>
    </div>

    <?php if ($favorites === []): ?>
    <div class="im-empty">
      <span class="im-empty__icon" aria-hidden="true"><?= icon('heart') ?></span>
      <p class="im-h3"><?= e(__('front.favorites.empty_title')) ?></p>
      <p class="im-lead"><?= e(__('front.favorites.empty_text')) ?></p>
      <a class="im-btn" href="<?= e(url('acheter')) ?>"><?= e(__('front.favorites.empty_cta')) ?></a>
    </div>
    <?php else: ?>
    <p class="im-results__count im-num"><?= e(__n('front.favorites.count', count($favorites))) ?></p>
    <?php if ($missing > 0): ?>
    <p class="im-small im-muted"><?= icon('info') ?> <?= e(__n('front.favorites.missing', $missing)) ?></p>
    <?php endif; ?>

    <div class="im-grid-cards">
      <?php foreach ($favorites as $index => $property): ?>
      <?= render_view('front/partials/property-card', ['property' => $property, 'eager' => $index < 3]) ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
