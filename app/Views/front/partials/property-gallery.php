<?php

/**
 * Galerie en mosaïque de la fiche annonce : une grande photo et jusqu'à quatre vignettes.
 * Chaque photo ouvre la visionneuse (`property.js`) ; sans JavaScript, le lien mène à l'image.
 *
 * @var array  $gallery [['src','srcset','full','alt']]
 * @var string $title
 */
$count = count($gallery);
$tiles = array_slice($gallery, 1, 4);
?>
<?php if ($count === 0): ?>
<div class="im-gallery im-gallery--empty">
  <img src="<?= e(asset('img/no-photo.svg')) ?>" alt="" width="240" height="180">
  <p class="im-muted"><?= e(__('front.property.no_photo')) ?></p>
</div>
<?php else: ?>
<div class="im-gallery<?= $tiles === [] ? ' im-gallery--single' : '' ?>" data-gallery aria-label="<?= e(__('front.property.gallery_label')) ?>">
  <a class="im-gallery__main" href="<?= e($gallery[0]['full']) ?>" data-gallery-item="0" aria-label="<?= e(__('front.property.gallery_open', ['index' => 1])) ?>">
    <img src="<?= e($gallery[0]['src']) ?>" srcset="<?= e($gallery[0]['srcset']) ?>"
         sizes="(min-width: 992px) 60vw, 100vw" alt="<?= e($gallery[0]['alt']) ?>"
         width="1600" height="1067" fetchpriority="high" decoding="async">
  </a>

  <?php foreach ($tiles as $index => $image): ?>
  <a class="im-gallery__tile" href="<?= e($image['full']) ?>" data-gallery-item="<?= e($index + 1) ?>" aria-label="<?= e(__('front.property.gallery_open', ['index' => $index + 2])) ?>">
    <img src="<?= e($image['src']) ?>" srcset="<?= e($image['srcset']) ?>" sizes="(min-width: 992px) 20vw, 50vw"
         alt="<?= e($image['alt']) ?>" width="800" height="533" loading="lazy" decoding="async">
  </a>
  <?php endforeach; ?>

  <?php if ($count > 1): ?>
  <button class="im-btn im-btn--light im-btn--sm im-gallery__all" type="button" data-gallery-open>
    <?= icon('images') ?> <?= e(__('front.property.gallery_all', ['count' => $count])) ?>
  </button>
  <?php endif; ?>
</div>

<!-- Visionneuse : masquée et sans contenu tant que le script ne l'ouvre pas. -->
<div class="im-lightbox" data-lightbox role="dialog" aria-modal="true" aria-label="<?= e(__('front.property.gallery_label')) ?>" hidden
     data-lightbox-images="<?= e(json_encode(array_map(
         static fn (array $image): array => ['src' => $image['full'], 'alt' => $image['alt']],
         $gallery
     ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
     data-label-close="<?= e(__('front.property.gallery_close')) ?>"
     data-label-previous="<?= e(__('front.property.gallery_previous')) ?>"
     data-label-next="<?= e(__('front.property.gallery_next')) ?>"></div>
<?php endif; ?>
