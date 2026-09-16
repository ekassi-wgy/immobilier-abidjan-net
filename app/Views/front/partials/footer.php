<?php
/**
 * Pied de page du site public : navigation secondaire, coordonnées du site et mentions.
 * Les coordonnées affichées sont celles du site courant (Pays & sites), jamais des valeurs en dur.
 *
 * Les pages éditoriales et légales ne sont listées que si elles sont publiées (table `pages`) :
 * tant que le client n'a pas fourni ses textes, aucun lien mort n'apparaît.
 *
 * @var array $pages Pages publiées par code
 */
$pages ??= [];
// Une page publiée devient un lien portant son propre titre ; sinon rien.
$pageLink = static fn (string $code): ?array => isset($pages[$code])
    ? [$pages[$code]['title'], $pages[$code]['slug']]
    : null;
$site = site();
$siteName = $site->name ?? config('app.name');
$country = $site?->country->localizedName(locale()) ?? '';
$columns = [
    __('front.footer.search') => [
        [__('front.footer.buy'), 'acheter'],
        [__('front.footer.rent'), 'louer'],
        [__('front.footer.furnished'), 'location-meublee'],
        [__('front.footer.land'), 'acheter/terrains'],
        [__('front.footer.commercial'), 'louer/commercial-bureaux'],
    ],
    __('front.footer.sell') => array_values(array_filter([
        [__('front.footer.submit_property'), 'deposer-un-bien'],
        [__('front.footer.find_agency'), 'agences'],
        [__('front.footer.become_partner'), 'devenir-partenaire'],
        $pageLink('how_it_works'),
    ])),
    $siteName => array_values(array_filter([
        $pageLink('about'),
        [__('front.footer.news'), 'actualites'],
        [__('front.footer.contact'), 'contact'],
    ])),
];
$legal = array_values(array_filter([
    $pageLink('legal_notice'),
    $pageLink('terms'),
    $pageLink('privacy'),
]));
$phone = $site->contactPhone ?? null;
$email = $site->contactEmail ?? null;
$whatsapp = $site->contactWhatsapp ?? null;
?>
<footer class="im-footer">
  <div class="im-container">
    <div class="im-footer__top">
      <div>
        <a class="im-footer__brand" href="<?= e(url()) ?>" aria-label="<?= e(__('front.nav.home_label', ['site' => $siteName])) ?>">
          <img src="<?= e(asset('img/brand/logo-immobilier-abidjan-net-blanc.png')) ?>" alt="" width="428" height="96" loading="lazy">
        </a>
        <p class="im-footer__pitch"><?= e(__('front.footer.pitch', ['country' => $country])) ?></p>
        <?php if ($phone !== null || $email !== null): ?>
        <ul class="im-footer__contact">
          <?php if ($phone !== null): ?>
          <li><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= icon('phone') ?> <?= e($phone) ?></a></li>
          <?php endif; ?>
          <?php if ($whatsapp !== null): ?>
          <li><a href="https://wa.me/<?= e(preg_replace('/\D+/', '', $whatsapp)) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?> WhatsApp</a></li>
          <?php endif; ?>
          <?php if ($email !== null): ?>
          <li><a href="mailto:<?= e($email) ?>"><?= icon('mail') ?> <?= e($email) ?></a></li>
          <?php endif; ?>
        </ul>
        <?php endif; ?>
      </div>
      <?php foreach ($columns as $title => $links): ?>
      <nav aria-label="<?= e($title) ?>">
        <p class="im-footer__title"><?= e($title) ?></p>
        <ul class="im-footer__links">
          <?php foreach ($links as [$label, $href]): ?>
          <li><a href="<?= e(url($href)) ?>"><?= e($label) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </nav>
      <?php endforeach; ?>
    </div>
    <div class="im-footer__bottom">
      <span>© <?= e(date('Y')) ?> <?= e($siteName) ?></span>
      <?php if ($legal !== []): ?>
      <span class="im-footer__legal">
        <?php foreach ($legal as $index => [$label, $href]): ?>
        <?= $index > 0 ? ' · ' : '' ?><a href="<?= e(url($href)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </span>
      <?php endif; ?>
    </div>
  </div>
</footer>
