<?php
/**
 * En-tête du site public + menu mobile.
 *
 * @var bool $overlay Transparent au-dessus du hero
 */
$navigation = [
    ['label' => 'Acheter', 'url' => 'acheter'],
    ['label' => 'Louer', 'url' => 'louer'],
    ['label' => 'Location meublée', 'url' => 'location-meublee'],
    ['label' => 'Terrains', 'url' => 'acheter/terrains'],
    ['label' => 'Bureaux & commerces', 'url' => 'louer/commercial-bureaux'],
    ['label' => 'Agences', 'url' => 'agences'],
];
?>
<header class="im-header<?= $overlay ? ' im-header--overlay' : ' is-scrolled' ?>" data-header>
  <div class="im-container im-header__inner">
    <a class="im-header__brand" href="<?= e(url()) ?>" aria-label="immobilier.abidjan.net — accueil">
      <img class="im-header__logo-dark" src="<?= e(asset('img/brand/logo-immobilier-abidjan-net.png')) ?>" alt="" width="428" height="96">
      <img class="im-header__logo-light" src="<?= e(asset('img/brand/logo-immobilier-abidjan-net-blanc.png')) ?>" alt="" width="428" height="96">
    </a>

    <nav class="im-header__nav" aria-label="Navigation principale">
      <ul>
        <?php foreach ($navigation as $item): ?>
        <li><a class="im-header__link" href="<?= e(url($item['url'])) ?>"><?= e($item['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="im-header__actions">
      <a class="im-header__icon-btn" href="<?= e(url('favoris')) ?>" aria-label="Mes favoris">
        <?= icon('heart') ?>
        <span class="im-header__badge" data-favorites-count></span>
      </a>
      <a class="im-btn im-btn--dark im-btn--sm im-header__cta" href="<?= e(url('deposer-un-bien')) ?>">Déposer un bien</a>
      <button class="im-header__icon-btn im-header__burger" type="button" aria-label="Ouvrir le menu" aria-controls="im-menu" aria-expanded="false" data-menu-open>
        <?= icon('menu') ?>
      </button>
    </div>
  </div>
</header>

<div class="im-menu" id="im-menu" role="dialog" aria-modal="true" aria-label="Menu" data-menu hidden>
  <div class="im-container im-menu__top">
    <a class="im-header__brand" href="<?= e(url()) ?>" aria-label="immobilier.abidjan.net — accueil">
      <img src="<?= e(asset('img/brand/logo-immobilier-abidjan-net.png')) ?>" alt="" width="428" height="96">
    </a>
    <button class="im-header__icon-btn" type="button" aria-label="Fermer le menu" data-menu-close>
      <?= icon('close') ?>
    </button>
  </div>
  <div class="im-container im-menu__body">
    <ul class="im-menu__list">
      <?php foreach ($navigation as $item): ?>
      <li><a href="<?= e(url($item['url'])) ?>"><?= e($item['label']) ?> <?= icon('caret-right') ?></a></li>
      <?php endforeach; ?>
      <li><a href="<?= e(url('favoris')) ?>">Mes favoris <?= icon('caret-right') ?></a></li>
    </ul>
  </div>
  <div class="im-container im-menu__footer">
    <a class="im-btn im-btn--dark im-btn--block" href="<?= e(url('deposer-un-bien')) ?>">Déposer un bien</a>
    <a class="im-btn im-btn--outline im-btn--block" href="<?= e(url('devenir-partenaire')) ?>">Devenir agence partenaire</a>
  </div>
</div>
