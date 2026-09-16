<?php

use App\Support\Paginator;

/**
 * Annuaire des agences partenaires (lot 1.11).
 *
 * @var array     $agencies
 * @var int       $total
 * @var array     $filters   q, ville, commune, verifiee
 * @var array     $cities    [['slug','name']]
 * @var Paginator $paginator
 * @var string    $baseUrl
 * @var array     $query
 */
?>
<section class="im-section im-section--tight">
  <div class="im-container">
    <div class="im-section-head">
      <div class="im-section-head__text">
        <p class="im-eyebrow"><?= e(__('front.nav.agencies')) ?></p>
        <h1 class="im-h2 im-section-head__title"><?= e(__('front.agencies.title')) ?></h1>
        <p class="im-lead im-section-head__lead"><?= e(__('front.agencies.lead')) ?></p>
      </div>
    </div>

    <form class="im-agency-filters" method="get" action="<?= e(url('agences')) ?>" role="search">
      <div class="im-field im-agency-filters__search">
        <label class="visually-hidden" for="agency-q"><?= e(__('front.agencies.search_label')) ?></label>
        <div class="im-control-icon">
          <?= icon('search') ?>
          <input class="im-control" id="agency-q" name="q" type="search" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('front.agencies.search_placeholder')) ?>" maxlength="80">
        </div>
      </div>
      <div class="im-field">
        <label class="visually-hidden" for="agency-city"><?= e(__('front.filters.city')) ?></label>
        <select class="im-control" id="agency-city" name="ville">
          <option value=""><?= e(__('front.filters.all_cities')) ?></option>
          <?php foreach ($cities as $city): ?>
          <option value="<?= e($city['slug']) ?>"<?= $filters['ville'] === $city['slug'] ? ' selected' : '' ?>><?= e($city['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <label class="im-check im-agency-filters__verified">
        <input type="checkbox" name="verifiee" value="oui"<?= $filters['verifiee'] === 'oui' ? ' checked' : '' ?>>
        <span class="im-check__box" aria-hidden="true"><?= icon('check') ?></span>
        <span><?= e(__('front.agencies.verified_only')) ?></span>
      </label>
      <button class="im-btn" type="submit"><?= e(__('front.search.submit')) ?></button>
    </form>

    <p class="im-results__count im-num"><?= e(__n('front.agencies.count', $total)) ?></p>

    <?php if ($agencies === []): ?>
    <div class="im-empty">
      <span class="im-empty__icon" aria-hidden="true"><?= icon('buildings') ?></span>
      <p class="im-h3"><?= e(__('front.agencies.empty_title')) ?></p>
      <p class="im-lead"><?= e(__('front.agencies.empty_text')) ?></p>
      <a class="im-btn im-btn--outline" href="<?= e(url('devenir-partenaire')) ?>"><?= e(__('front.nav.become_partner')) ?></a>
    </div>
    <?php else: ?>
    <div class="im-agency-grid">
      <?php foreach ($agencies as $agency): $place = trim(implode(', ', array_filter([$agency['commune_name'], $agency['city_name']]))); ?>
      <a class="im-agency" href="<?= e(url('agences/' . $agency['slug'])) ?>">
        <span class="im-agency__logo">
          <?php if (!empty($agency['logo_path'])): ?>
          <img src="<?= e(url((string) $agency['logo_path'])) ?>" alt="" width="120" height="60" loading="lazy">
          <?php else: ?>
          <?= e(mb_strtoupper(mb_substr((string) $agency['name'], 0, 1))) ?>
          <?php endif; ?>
        </span>
        <span class="im-agency__body">
          <span class="im-agency__name">
            <?php if ((int) $agency['is_verified'] === 1): ?><?= icon('verified', 'im-agency__check', __('front.card.verified_agency')) ?><?php endif; ?>
            <?= e($agency['name']) ?>
          </span>
          <?php if ($place !== ''): ?><span class="im-agency__meta"><?= e($place) ?></span><?php endif; ?>
          <span class="im-agency__meta im-num"><?= e(__n('front.home.agencies_count', (int) $agency['listings'])) ?></span>
        </span>
      </a>
      <?php endforeach; ?>
    </div>

    <?= render_view('front/partials/pagination', ['paginator' => $paginator, 'baseUrl' => $baseUrl, 'query' => $query]) ?>
    <?php endif; ?>
  </div>
</section>
