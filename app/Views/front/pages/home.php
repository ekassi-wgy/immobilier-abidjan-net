<?php
/**
 * Accueil du site public (lot 1.8).
 * Chaque section n'est affichée que si elle a du contenu : au lancement, le site reste digne avec peu d'annonces.
 *
 * @var array                     $hero      Diaporama + recherche (voir partials/hero)
 * @var list<array<string,mixed>> $featured  Biens à la une (cartes déjà mises en forme)
 * @var list<array<string,mixed>> $latest    Dernières annonces
 * @var list<array<string,mixed>> $families  Familles de catégories + nombre d'annonces
 * @var list<array<string,mixed>> $communes  Communes les plus représentées
 * @var array                     $figures   ['listings','agencies','communes','cities']
 * @var list<array<string,mixed>> $agencies  Agences partenaires en vedette
 */
$hasListings = $featured !== [] || $latest !== [];
?>
<?= render_view('front/partials/hero', $hero) ?>

<?php if ($featured !== []): ?>
<section class="im-section" aria-labelledby="featured-title">
  <div class="im-container">
    <header class="im-section-head">
      <div class="im-section-head__text">
        <p class="im-eyebrow"><?= e(__('front.home.featured_eyebrow')) ?></p>
        <h2 class="im-h2 im-section-head__title" id="featured-title"><?= e(__('front.home.featured_title')) ?></h2>
        <p class="im-lead im-section-head__lead"><?= e(__('front.home.featured_lead')) ?></p>
      </div>
      <a class="im-link" href="<?= e(url('acheter')) ?>"><?= e(__('front.home.all_listings')) ?> <?= icon('arrow-right', 'im-icon--arrow') ?></a>
    </header>

    <div class="im-grid-cards">
      <?php foreach ($featured as $index => $property): ?>
        <?= render_view('front/partials/property-card', ['property' => $property, 'eager' => $index < 2]) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($families !== []): ?>
<section class="im-section im-section--mist" aria-labelledby="families-title">
  <div class="im-container">
    <header class="im-section-head">
      <div class="im-section-head__text">
        <p class="im-eyebrow"><?= e(__('front.home.families_eyebrow')) ?></p>
        <h2 class="im-h2 im-section-head__title" id="families-title"><?= e(__('front.home.families_title')) ?></h2>
        <p class="im-lead im-section-head__lead"><?= e(__('front.home.families_lead')) ?></p>
      </div>
    </header>

    <ul class="im-family-grid">
      <?php foreach ($families as $family): $count = (int) $family['listings']; ?>
      <li>
        <a class="im-family<?= $count === 0 ? ' is-empty' : '' ?>" href="<?= e(url('acheter/' . $family['slug'])) ?>">
          <span class="im-family__icon"><?= icon($family['icon'] ?: 'house') ?></span>
          <span class="im-family__body">
            <span class="im-family__name"><?= e($family['name_plural'] ?: $family['name']) ?></span>
            <span class="im-family__count"><?= e($count > 0 ? __n('front.home.families_count', $count) : __('front.home.families_empty')) ?></span>
          </span>
          <?= icon('arrow-right', 'im-family__arrow') ?>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($communes !== []): ?>
    <div class="im-chips-block">
      <p class="im-chips-block__title"><?= e(__('front.home.communes_title')) ?></p>
      <ul class="im-chips">
        <?php foreach ($communes as $commune): ?>
        <li><a class="im-chip" href="<?= e(url('acheter?lieu=' . rawurlencode((string) $commune['name']))) ?>"><?= e($commune['name']) ?> <span class="im-chip__count"><?= e(format_number((int) $commune['listings'])) ?></span></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($latest !== []): ?>
<section class="im-section" aria-labelledby="latest-title">
  <div class="im-container">
    <header class="im-section-head">
      <div class="im-section-head__text">
        <p class="im-eyebrow"><?= e(__('front.home.latest_eyebrow')) ?></p>
        <h2 class="im-h2 im-section-head__title" id="latest-title"><?= e(__('front.home.latest_title')) ?></h2>
        <p class="im-lead im-section-head__lead"><?= e(__('front.home.latest_lead')) ?></p>
      </div>
      <a class="im-link" href="<?= e(url('acheter')) ?>"><?= e(__('front.home.all_listings')) ?> <?= icon('arrow-right', 'im-icon--arrow') ?></a>
    </header>

    <div class="im-grid-cards">
      <?php foreach ($latest as $property): ?>
        <?= render_view('front/partials/property-card', ['property' => $property]) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!$hasListings): ?>
<section class="im-section" aria-labelledby="empty-title">
  <div class="im-container">
    <div class="im-empty-home">
      <?= icon('house', 'im-empty-home__icon') ?>
      <h2 class="im-h3" id="empty-title"><?= e(__('front.home.empty_title')) ?></h2>
      <p class="im-lead"><?= e(__('front.home.empty_text')) ?></p>
      <a class="im-btn" href="<?= e(url('devenir-partenaire')) ?>"><?= e(__('front.home.partner_cta')) ?></a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($figures['listings'] > 0 || $figures['agencies'] > 0): ?>
<section class="im-section im-section--figures" aria-labelledby="figures-title">
  <div class="im-container">
    <div class="im-figures">
      <div class="im-figures__text">
        <h2 class="im-h3" id="figures-title"><?= e(__('front.home.figures_title')) ?></h2>
        <p class="im-lead"><?= e(__('front.home.figures_lead')) ?></p>
      </div>
      <dl class="im-figures__list">
        <?php foreach (['listings', 'agencies', 'communes', 'cities'] as $key): $label = __n('front.home.figures_' . $key, $figures[$key]); ?>
        <div class="im-figure">
          <dt class="im-figure__value im-num"><?= e(format_number($figures[$key])) ?></dt>
          <dd class="im-figure__label"><?= e($label) ?></dd>
        </div>
        <?php endforeach; ?>
      </dl>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($agencies !== []): ?>
<section class="im-section" aria-labelledby="agencies-title">
  <div class="im-container">
    <header class="im-section-head">
      <div class="im-section-head__text">
        <p class="im-eyebrow"><?= e(__('front.home.agencies_eyebrow')) ?></p>
        <h2 class="im-h2 im-section-head__title" id="agencies-title"><?= e(__('front.home.agencies_title')) ?></h2>
        <p class="im-lead im-section-head__lead"><?= e(__('front.home.agencies_lead')) ?></p>
      </div>
      <a class="im-link" href="<?= e(url('agences')) ?>"><?= e(__('front.home.agencies_link')) ?> <?= icon('arrow-right', 'im-icon--arrow') ?></a>
    </header>

    <ul class="im-agency-grid">
      <?php foreach ($agencies as $agency): ?>
      <li>
        <a class="im-agency" href="<?= e(url('agences/' . $agency['slug'])) ?>">
          <span class="im-agency__logo">
            <?php if ($agency['logo_path']): ?>
            <img src="<?= e(url($agency['logo_path'])) ?>" alt="" width="64" height="64" loading="lazy">
            <?php else: ?>
            <span aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $agency['name'], 0, 1))) ?></span>
            <?php endif; ?>
          </span>
          <span class="im-agency__body">
            <span class="im-agency__name">
              <?= e($agency['name']) ?>
              <?php if ((int) $agency['is_verified'] === 1): ?><?= icon('verified', 'im-agency__check', __('front.card.verified_agency')) ?><?php endif; ?>
            </span>
            <span class="im-agency__meta"><?= e(implode(' · ', array_filter([
                trim((string) ($agency['commune_name'] ?? $agency['city_name'] ?? '')),
                (int) $agency['listings'] > 0 ? __n('front.home.agencies_count', (int) $agency['listings']) : null,
            ]))) ?></span>
          </span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<section class="im-section im-section--partner" aria-labelledby="partner-title">
  <div class="im-container">
    <div class="im-partner">
      <div class="im-partner__text">
        <p class="im-eyebrow im-eyebrow--light"><?= e(__('front.home.partner_eyebrow')) ?></p>
        <h2 class="im-h2" id="partner-title"><?= e(__('front.home.partner_title')) ?></h2>
        <p class="im-lead"><?= e(__('front.home.partner_text')) ?></p>
      </div>
      <div class="im-partner__actions">
        <a class="im-btn im-btn--lg" href="<?= e(url('devenir-partenaire')) ?>"><?= e(__('front.home.partner_cta')) ?></a>
        <p class="im-partner__secondary">
          <?= e(__('front.home.partner_secondary')) ?>
          <a href="<?= e(url('deposer-un-bien')) ?>"><?= e(__('front.home.partner_secondary_cta')) ?> <?= icon('arrow-right', 'im-icon--arrow') ?></a>
        </p>
      </div>
    </div>
  </div>
</section>
