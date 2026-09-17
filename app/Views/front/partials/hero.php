<?php
/**
 * Hero de l'accueil : diaporama + recherche flottante.
 *
 * @var array $slides        Liste de ['desktop','mobile','fallback' => URL, 'caption' => ?string, 'origin' => '50% 50%']
 * @var int   $listingsCount Nombre d'annonces en ligne
 * @var array $search        Données du module de recherche (voir partials/search)
 * @var int   $interval      Durée d'une diapositive (ms)
 * @var string $city         Ville principale du pays (titre éditorial)
 */
$interval ??= 7000;
$total = count($slides);
$country = site()?->country->localizedName(locale()) ?? '';
?>
<section class="im-hero" aria-roledescription="carrousel" aria-label="<?= e(__('front.hero.carousel_label', ['country' => $country])) ?>" data-hero style="--im-hero-interval: <?= (int) $interval ?>ms">
  <div class="im-hero__slides">
    <?php foreach ($slides as $index => $slide): ?>
    <figure class="im-hero__slide<?= $index === 0 ? ' is-active' : '' ?>"
            role="group" aria-roledescription="diapositive" aria-label="<?= e(__('front.hero.slide_label', ['index' => $index + 1, 'total' => $total])) ?>"
            data-caption="<?= e($slide['caption'] ?? '') ?>"<?= $index === 0 ? '' : ' aria-hidden="true"' ?>>
      <?php
      // Diapositives suivantes : adresses en data-* (les images empilées sont toutes « dans la
      // fenêtre », loading="lazy" ne retarderait rien). hero.js ne charge que la suivante.
      $deferred = $index > 0 ? 'data-' : '';
      ?>
      <picture>
        <source media="(max-width: 767px)" type="image/webp" <?= $deferred ?>srcset="<?= e($slide['mobile']) ?>">
        <source type="image/webp" <?= $deferred ?>srcset="<?= e($slide['desktop']) ?>">
        <img class="im-hero__image" <?= $index > 0 ? 'src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" ' : '' ?><?= $deferred ?>src="<?= e($slide['fallback']) ?>" alt=""
             width="1920" height="1080" decoding="async"
             <?= $index === 0 ? 'fetchpriority="high"' : '' ?>
             style="--im-hero-origin: <?= e($slide['origin'] ?? '50% 50%') ?>">
      </picture>
    </figure>
    <?php endforeach; ?>
  </div>
  <div class="im-hero__shade" aria-hidden="true"></div>

  <div class="im-container im-hero__inner">
    <div class="im-hero__content">
      <p class="im-hero__eyebrow">
        <?= icon('verified') ?>
        <span><?= $listingsCount > 0
            ? '<span class="im-num">' . e(format_number($listingsCount)) . '</span> ' . e(__n('front.hero.eyebrow_suffix', $listingsCount, ['country' => $country]))
            : e(__('front.hero.eyebrow_empty', ['country' => $country])) ?></span>
      </p>
      <h1 class="im-display im-hero__title">
        <?= e(__('front.hero.title')) ?>
        <span class="im-hero__title-soft"><?= e(__('front.hero.title_soft', ['city' => $city])) ?></span>
      </h1>
      <p class="im-hero__text"><?= e(__('front.hero.text')) ?></p>
    </div>

    <?php if ($total > 1): ?>
    <div class="im-hero__controls">
      <p class="im-hero__caption" data-hero-caption-wrap<?= empty($slides[0]['caption']) ? ' hidden' : '' ?>>
        <?= icon('pin') ?> <span data-hero-caption><?= e($slides[0]['caption'] ?? '') ?></span>
      </p>
      <div class="im-hero__nav">
        <span class="im-hero__counter" aria-hidden="true"><strong data-hero-current>01</strong> / <?= e(str_pad((string) $total, 2, '0', STR_PAD_LEFT)) ?></span>
        <div class="im-hero__progress">
          <?php foreach ($slides as $index => $slide): ?>
          <button class="im-hero__step<?= $index === 0 ? ' is-active' : '' ?>" type="button" aria-label="<?= e(__('front.hero.show_photo', ['index' => $index + 1])) ?>"<?= $index === 0 ? ' aria-current="true"' : '' ?> data-hero-step="<?= $index ?>"></button>
          <?php endforeach; ?>
        </div>
        <button class="im-hero__toggle" type="button" aria-label="<?= e(__('front.hero.pause')) ?>" data-hero-label-pause="<?= e(__('front.hero.pause')) ?>" data-hero-label-play="<?= e(__('front.hero.play')) ?>" data-hero-toggle>
          <span data-hero-icon-pause><?= icon('pause') ?></span>
          <span data-hero-icon-play hidden><?= icon('play') ?></span>
        </button>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<div class="im-container im-hero-search">
  <?= render_view('front/partials/search', $search) ?>
</div>
