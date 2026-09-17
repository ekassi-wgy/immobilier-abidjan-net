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
        [__('front.nav.entrust_property'), 'confiez-nous-votre-bien'],
        [__('front.nav.owner_space'), 'mon-espace'],
        [__('front.nav.become_partner'), 'devenir-partenaire'],
        [__('front.nav.partners'), 'partenaires'],
        $pageLink('how_it_works'),
        $pageLink('faq'),
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
// Adresse courte : la boîte postale reste sur la page Contact et dans les mentions légales.
$address = $site?->shortAddress();
$contacts = array_values(array_filter([
    $phone !== null ? ['icon' => 'phone', 'label' => __('front.footer.phone'), 'value' => $phone, 'href' => 'tel:' . preg_replace('/[^\d+]/', '', $phone)] : null,
    $email !== null ? ['icon' => 'mail', 'label' => __('front.footer.email'), 'value' => $email, 'href' => 'mailto:' . $email] : null,
    $address !== null ? ['icon' => 'pin', 'label' => __('front.footer.office'), 'value' => $address, 'href' => url('contact')] : null,
]));
// Seuls les réseaux renseignés dans Pays & sites ont un bouton : jamais de lien mort.
$social = $site->socialLinks ?? [];
$socialIcons = ['facebook' => 'facebook', 'instagram' => 'instagram', 'linkedin' => 'linkedin', 'x' => 'x-twitter', 'youtube' => 'youtube', 'tiktok' => 'tiktok'];
?>
<footer class="im-footer">
  <div class="im-container">
    <div class="im-footer__top">
      <div class="im-footer__about">
        <a class="im-footer__brand" href="<?= e(url()) ?>" aria-label="<?= e(__('front.nav.home_label', ['site' => $siteName])) ?>">
          <?= logo_picture('assets/img/brand/logo-immobilier-abidjan-net-blanc.png', ['width' => 428, 'height' => 96, 'loading' => 'lazy']) ?>
        </a>
        <p class="im-footer__pitch"><?= e(__('front.footer.pitch', ['country' => $country])) ?></p>
        <?php if ($social !== []): ?>
        <ul class="im-footer__social" aria-label="<?= e(__('front.footer.social')) ?>">
          <?php foreach ($social as $network => $link): ?>
          <li>
            <a href="<?= e($link) ?>" target="_blank" rel="noopener me" aria-label="<?= e(__('front.footer.social_link', ['network' => __('front.footer.networks.' . $network)])) ?>">
              <?= icon($socialIcons[$network]) ?>
            </a>
          </li>
          <?php endforeach; ?>
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

    <?php if ($contacts !== [] || $whatsapp !== null): ?>
    <div class="im-footer__contact">
      <?php foreach ($contacts as $contact): ?>
      <a class="im-footer__contact-item" href="<?= e($contact['href']) ?>">
        <span class="im-footer__contact-icon"><?= icon($contact['icon']) ?></span>
        <span class="im-footer__contact-text">
          <span class="im-footer__contact-label"><?= e($contact['label']) ?></span>
          <span class="im-footer__contact-value"><?= e($contact['value']) ?></span>
        </span>
      </a>
      <?php endforeach; ?>
      <?php if ($whatsapp !== null): ?>
      <a class="im-footer__whatsapp" href="https://wa.me/<?= e(preg_replace('/\D+/', '', $whatsapp)) ?>" target="_blank" rel="noopener">
        <?= icon('whatsapp') ?> <?= e(__('front.footer.whatsapp')) ?> <?= icon('arrow-up-right') ?>
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

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
