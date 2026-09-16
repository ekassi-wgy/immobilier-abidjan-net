<?php

use App\Support\Paginator;

/**
 * Profil public d'une agence partenaire (lot 1.11).
 *
 * @var array     $agency    Profil (sans RCCM ni email de gestion : ils restent internes)
 * @var array     $zones     Communes couvertes
 * @var array     $listings  Cartes annonce de la page courante
 * @var Paginator $paginator
 * @var string    $baseUrl
 * @var array     $query
 * @var array     $errors
 * @var array     $old
 * @var array     $flash
 * @var string    $csrfToken
 */
$field = static fn (array $data): string => render_view('front/partials/field', $data);
$common = ['errors' => $errors, 'old' => $old];
$whatsappUrl = !empty($agency['whatsapp'])
    ? 'https://wa.me/' . preg_replace('/\D+/', '', (string) $agency['whatsapp'])
    : null;

$fields = $field($common + ['name' => 'name', 'label' => __('front.contact.name'), 'required' => true, 'maxlength' => 150, 'autocomplete' => 'name'])
    . '<div class="im-form__pair">'
    . $field($common + ['name' => 'email', 'label' => __('front.contact.email'), 'type' => 'email', 'autocomplete' => 'email'])
    . $field($common + ['name' => 'phone', 'label' => __('front.contact.phone'), 'type' => 'tel', 'maxlength' => 30, 'autocomplete' => 'tel'])
    . '</div>'
    . '<p class="im-field__help">' . e(__('front.contact.contact_hint')) . '</p>'
    . $field($common + ['name' => 'message', 'label' => __('front.contact.message'), 'type' => 'textarea', 'required' => true, 'maxlength' => 2000]);
?>
<article class="im-agency-profile">

  <header class="im-agency-hero">
    <div class="im-container im-agency-hero__inner">
      <?php if (!empty($agency['logo_path'])): ?>
      <img class="im-agency-hero__logo" src="<?= e(url((string) $agency['logo_path'])) ?>" alt="" width="160" height="80">
      <?php else: ?>
      <span class="im-agency-hero__logo im-agency-hero__logo--initial" aria-hidden="true"><?= e(mb_substr((string) $agency['name'], 0, 1)) ?></span>
      <?php endif; ?>

      <div class="im-agency-hero__identity">
        <p class="im-eyebrow im-eyebrow--light"><?= e(__('front.agencies.partner')) ?></p>
        <h1 class="im-h2"><?= e($agency['name']) ?></h1>
        <p class="im-agency-hero__meta">
          <?php if ((int) $agency['is_verified'] === 1): ?>
          <span class="im-badge im-badge--light"><?= icon('verified') ?> <?= e(__('front.card.verified_agency')) ?></span>
          <?php endif; ?>
          <?php $place = trim(implode(', ', array_filter([$agency['commune_name'], $agency['city_name']]))); ?>
          <?php if ($place !== ''): ?><span><?= icon('pin') ?> <?= e($place) ?></span><?php endif; ?>
          <span class="im-num"><?= e(__n('front.home.agencies_count', (int) $agency['listings'])) ?></span>
        </p>
      </div>

      <div class="im-agency-hero__actions">
        <?php if (!empty($agency['phone'])): ?>
        <a class="im-btn im-btn--light" href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $agency['phone'])) ?>"><?= icon('phone') ?> <?= e($agency['phone']) ?></a>
        <?php endif; ?>
        <?php if ($whatsappUrl !== null): ?>
        <a class="im-btn im-btn--whatsapp" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?> WhatsApp</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <div class="im-container im-agency-profile__layout">
    <div class="im-agency-profile__main">
      <?php if (!empty($agency['description'])): ?>
      <section class="im-property__block">
        <h2 class="im-h3"><?= e(__('front.agencies.about')) ?></h2>
        <div class="im-property__text"><?= nl2br(e((string) $agency['description'])) ?></div>
      </section>
      <?php endif; ?>

      <?php if ($zones !== []): ?>
      <section class="im-property__block">
        <h2 class="im-h3"><?= e(__('front.agencies.zones')) ?></h2>
        <div class="im-chips">
          <?php foreach ($zones as $zone): ?>
          <a class="im-chip" href="<?= e(url('acheter/' . $zone['city_slug'] . '/' . $zone['slug'])) ?>"><?= e($zone['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <section class="im-property__block" id="annonces">
        <h2 class="im-h3"><?= e(__('front.agencies.listings_title')) ?></h2>
        <?php if ($listings === []): ?>
        <div class="im-empty">
          <span class="im-empty__icon" aria-hidden="true"><?= icon('house') ?></span>
          <p class="im-h3"><?= e(__('front.agencies.no_listing_title')) ?></p>
          <p class="im-lead"><?= e(__('front.agencies.no_listing_text')) ?></p>
        </div>
        <?php else: ?>
        <div class="im-grid-cards im-grid-cards--compact">
          <?php foreach ($listings as $card): ?>
          <?= render_view('front/partials/property-card', ['property' => $card]) ?>
          <?php endforeach; ?>
        </div>
        <?= render_view('front/partials/pagination', ['paginator' => $paginator, 'baseUrl' => $baseUrl, 'query' => $query]) ?>
        <?php endif; ?>
      </section>
    </div>

    <aside class="im-agency-profile__aside">
      <section class="im-contact" id="contact">
        <div class="im-contact__head">
          <h2 class="im-h3"><?= e(__('front.agencies.contact_title')) ?></h2>
          <p class="im-small im-muted"><?= e(__('front.agencies.contact_lead', ['agency' => $agency['name']])) ?></p>
        </div>

        <?= render_view('front/partials/form-shell', [
            'action' => 'agences/' . $agency['slug'] . '/contact',
            'fields' => $fields,
            'submit' => __('front.contact.submit'),
            'consent' => __('front.contact.consent'),
            'errors' => $errors,
            'old' => $old,
            'flash' => $flash,
            'csrfToken' => $csrfToken,
        ]) ?>
      </section>

      <?php if (!empty($agency['website'])): ?>
      <p class="im-small"><a class="im-link" href="<?= e($agency['website']) ?>" target="_blank" rel="noopener nofollow"><?= icon('arrow-up-right') ?> <?= e(__('front.agencies.website')) ?></a></p>
      <?php endif; ?>
    </aside>
  </div>
</article>
