<?php
/**
 * Carte annonce (grille et liste).
 *
 * @var array  $property ['reference','url','title','category','transaction','price','currency','period',
 *                        'location','image' => ['src','srcset','alt','placeholder'],'photos',
 *                        'badges' => [['label','variant']],'specs' => [['icon','label']],
 *                        'agency','agency_url'?,'verified','phone','whatsapp','description'?]
 * @var string $variant  'grid' | 'row'
 * @var bool   $eager    Image chargée immédiatement (au-dessus de la ligne de flottaison)
 */
$variant ??= 'grid';
$eager ??= false;
$p = $property;
$image = $p['image'];
$sizes = $variant === 'row' ? '(min-width: 768px) 380px, 100vw' : '(min-width: 1200px) 400px, (min-width: 576px) 50vw, 100vw';
$whatsappUrl = !empty($p['whatsapp'])
    ? 'https://wa.me/' . preg_replace('/\D+/', '', $p['whatsapp'])
        . '?text=' . rawurlencode(__('front.card.whatsapp_message', ['reference' => $p['reference'], 'title' => $p['title']]))
    : null;
?>
<article class="im-card<?= $variant === 'row' ? ' im-card--row' : '' ?>">
  <div class="im-card__media">
    <a class="im-card__media-link" href="<?= e(url($p['url'])) ?>" tabindex="-1" aria-hidden="true">
      <img class="im-card__image<?= !empty($image['placeholder']) ? ' im-card__image--placeholder' : '' ?>"
           src="<?= e($image['src']) ?>"
           <?= $image['srcset'] !== '' ? 'srcset="' . e($image['srcset']) . '" sizes="' . e($sizes) . '"' : '' ?>
           alt="<?= e($image['alt']) ?>" width="800" height="600" decoding="async"<?= $eager ? '' : ' loading="lazy"' ?>>
    </a>

    <?php if (!empty($p['badges'])): ?>
    <div class="im-card__badges">
      <?php foreach ($p['badges'] as $badge): ?>
      <span class="im-badge im-badge--<?= e($badge['variant']) ?>"><?= e($badge['label']) ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <button class="im-card__fav" type="button" aria-pressed="false" aria-label="<?= e(__('front.card.add_favorite', ['title' => $p['title']])) ?>" data-favorite="<?= e($p['reference']) ?>">
      <?= icon('heart') ?>
    </button>

    <?php if (!empty($p['photos'])): ?>
    <span class="im-card__count"><?= icon('camera') ?> <?= e($p['photos']) ?><span class="visually-hidden"> <?= e(__('front.card.photos')) ?></span></span>
    <?php endif; ?>
  </div>

  <div class="im-card__body">
    <p class="im-card__eyebrow"><span><?= e($p['category']) ?> · <?= e($p['transaction']) ?></span></p>

    <p class="im-card__price">
      <?php if ($p['price'] === null): ?>
      <?= e(__('common.price_on_request')) ?>
      <?php else: ?>
      <?= e(format_number($p['price'])) ?> <span class="im-card__currency"><?= e($p['currency']) ?></span>
      <?php if (price_period_label($p['period'] ?? 'total') !== ''): ?><span class="im-card__period"><?= e(price_period_label($p['period'])) ?></span><?php endif; ?>
      <?php endif; ?>
    </p>

    <h3 class="im-card__title">
      <a class="im-card__link" href="<?= e(url($p['url'])) ?>"><?= e($p['title']) ?></a>
    </h3>

    <p class="im-card__location"><?= icon('pin') ?> <?= e($p['location']) ?></p>

    <?php if ($variant === 'row' && !empty($p['description'])): ?>
    <p class="im-card__description"><?= e($p['description']) ?></p>
    <?php endif; ?>

    <?php if (!empty($p['specs'])): ?>
    <ul class="im-card__specs">
      <?php foreach ($p['specs'] as $spec): ?>
      <li><?= icon($spec['icon']) ?> <?= e($spec['label']) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <div class="im-card__footer">
      <span class="im-card__agency">
        <?php if (!empty($p['verified'])): ?><?= icon('verified', '', __('front.card.verified_agency')) ?><?php endif; ?>
        <span><?= e($p['agency']) ?></span>
      </span>
      <div class="im-card__actions">
        <?php if ($whatsappUrl !== null): ?>
        <a class="im-card__action im-card__action--whatsapp" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener" aria-label="<?= e(__('front.card.whatsapp', ['reference' => $p['reference']])) ?>"><?= icon('whatsapp') ?></a>
        <?php endif; ?>
        <?php if (!empty($p['phone'])): ?>
        <a class="im-card__action" href="tel:<?= e(preg_replace('/[^\d+]/', '', $p['phone'])) ?>" aria-label="<?= e(__('front.card.call', ['reference' => $p['reference']])) ?>"><?= icon('phone') ?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</article>
