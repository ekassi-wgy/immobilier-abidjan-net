<?php
/**
 * En-tête du site public + menu mobile.
 *
 * Deux parcours, et deux seulement, pour qui propose un bien (Weblogy est l'intermédiaire exclusif) :
 *  - professionnel de l'immobilier → « Devenir partenaire » (bouton principal) ;
 *  - particulier propriétaire     → « Confiez-nous votre bien » (espace propriétaire, compte requis).
 * Aucun libellé ne laisse croire qu'un visiteur publie lui-même une annonce.
 *
 * @var bool $overlay Transparent au-dessus du hero
 */
$siteName = site()->name ?? config('app.name');
$navigation = [
    ['label' => __('front.nav.buy'), 'url' => 'acheter'],
    ['label' => __('front.nav.rent'), 'url' => 'louer'],
    ['label' => __('front.nav.furnished'), 'url' => 'location-meublee'],
    ['label' => __('front.nav.land'), 'url' => 'acheter/terrains'],
    // Raccourci secondaire : masqué dans la barre sous 1400 px pour laisser la place aux deux parcours « proposer un bien »
    ['label' => __('front.nav.commercial'), 'url' => 'louer/commercial-bureaux', 'secondary' => true],
];
?>
<header class="im-header<?= $overlay ? ' im-header--overlay' : ' is-scrolled' ?>" data-header>
  <div class="im-container im-header__inner">
    <a class="im-header__brand" href="<?= e(url()) ?>" aria-label="<?= e(__('front.nav.home_label', ['site' => $siteName])) ?>">
      <?= logo_picture('assets/img/brand/logo-immobilier-abidjan-net.png', ['class' => 'im-header__logo-dark', 'width' => 428, 'height' => 96]) ?>
      <?= logo_picture('assets/img/brand/logo-immobilier-abidjan-net-blanc.png', ['class' => 'im-header__logo-light', 'width' => 428, 'height' => 96]) ?>
    </a>

    <nav class="im-header__nav" aria-label="<?= e(__('front.nav.main_label')) ?>">
      <ul>
        <?php foreach ($navigation as $item): ?>
        <li<?= !empty($item['secondary']) ? ' class="im-header__nav-secondary"' : '' ?>><a class="im-header__link" href="<?= e(url($item['url'])) ?>"><?= e($item['label']) ?></a></li>
        <?php endforeach; ?>
        <li class="im-header__nav-owner"><a class="im-header__link im-header__link--accent" href="<?= e(url('confiez-nous-votre-bien')) ?>"><?= icon('key') ?> <?= e(__('front.nav.entrust_property')) ?></a></li>
      </ul>
    </nav>

    <div class="im-header__actions">
      <a class="im-header__icon-btn" href="<?= e(url('favoris')) ?>" aria-label="<?= e(__('front.nav.favorites')) ?>">
        <?= icon('heart') ?>
        <span class="im-header__badge" data-favorites-count></span>
      </a>
      <a class="im-header__icon-btn" href="<?= e(url('mon-espace')) ?>" aria-label="<?= e(__('front.nav.owner_space')) ?>" title="<?= e(__('front.nav.owner_space')) ?>">
        <?= icon('user') ?>
      </a>
      <a class="im-btn im-btn--dark im-btn--sm im-header__cta" href="<?= e(url('devenir-partenaire')) ?>"><?= e(__('front.nav.become_partner')) ?></a>
      <button class="im-header__icon-btn im-header__burger" type="button" aria-label="<?= e(__('front.nav.open_menu')) ?>" aria-controls="im-menu" aria-expanded="false" data-menu-open>
        <?= icon('menu') ?>
      </button>
    </div>
  </div>
</header>

<div class="im-menu" id="im-menu" role="dialog" aria-modal="true" aria-label="<?= e(__('front.nav.menu_label')) ?>" data-menu hidden>
  <div class="im-container im-menu__top">
    <a class="im-header__brand" href="<?= e(url()) ?>" aria-label="<?= e(__('front.nav.home_label', ['site' => $siteName])) ?>">
      <?= logo_picture('assets/img/brand/logo-immobilier-abidjan-net.png', ['width' => 428, 'height' => 96, 'loading' => 'lazy']) ?>
    </a>
    <button class="im-header__icon-btn" type="button" aria-label="<?= e(__('front.nav.close_menu')) ?>" data-menu-close>
      <?= icon('close') ?>
    </button>
  </div>
  <div class="im-container im-menu__body">
    <ul class="im-menu__list">
      <?php foreach ($navigation as $item): ?>
      <li><a href="<?= e(url($item['url'])) ?>"><?= e($item['label']) ?> <?= icon('caret-right') ?></a></li>
      <?php endforeach; ?>
      <li><a href="<?= e(url('favoris')) ?>"><?= e(__('front.nav.favorites')) ?> <?= icon('caret-right') ?></a></li>
      <li><a href="<?= e(url('mon-espace')) ?>"><?= e(__('front.nav.owner_space')) ?> <?= icon('caret-right') ?></a></li>
    </ul>
  </div>
  <div class="im-container im-menu__footer">
    <a class="im-btn im-btn--dark im-btn--block" href="<?= e(url('confiez-nous-votre-bien')) ?>"><?= icon('key') ?> <?= e(__('front.nav.entrust_property')) ?></a>
    <a class="im-btn im-btn--outline im-btn--block" href="<?= e(url('devenir-partenaire')) ?>"><?= e(__('front.nav.become_partner')) ?></a>
  </div>
</div>
