<?php

/**
 * Bandeau cookies.
 *
 * Sans mesure d'audience (aucun tag Google pour le site, ou domaine hors production) : bandeau
 * informatif, le site ne dépose que des cookies nécessaires (session, favoris, consultations).
 *
 * Avec un tag Google : véritable recueil du consentement. `site.js` ne charge le script Google
 * qu'après « Accepter » ; « Refuser » n'en charge aucun et supprime les cookies _ga éventuels.
 * Le choix est conservé six mois et se modifie par le lien « Gestion des cookies » du pied de page.
 *
 * @var array       $pages       Pages publiées par code (cookies, privacy)
 * @var string|null $analyticsId Tag Google à proposer (null = aucune mesure)
 */
$analyticsId ??= null;
$policy = $pages['cookies'] ?? $pages['privacy'] ?? null;
?>
<div class="im-cookies" data-cookies<?= $analyticsId !== null ? ' data-analytics-id="' . e($analyticsId) . '" role="region" aria-label="' . e(__('front.cookies.label')) . '"' : '' ?> hidden>
  <p class="im-cookies__text">
    <?= e(__($analyticsId !== null ? 'front.cookies.consent_text' : 'front.cookies.text')) ?>
    <?php if ($policy !== null): ?>
    <a class="im-link" href="<?= e(url($policy['slug'])) ?>"><?= e($policy['title']) ?></a>
    <?php endif; ?>
  </p>
  <?php if ($analyticsId !== null): ?>
  <div class="im-cookies__actions">
    <button class="im-btn im-btn--outline im-btn--sm" type="button" data-cookies-refuse><?= e(__('front.cookies.consent_refuse')) ?></button>
    <button class="im-btn im-btn--sm" type="button" data-cookies-accept><?= e(__('front.cookies.consent_accept')) ?></button>
  </div>
  <?php else: ?>
  <button class="im-btn im-btn--sm" type="button" data-cookies-accept><?= e(__('front.cookies.accept')) ?></button>
  <?php endif; ?>
</div>
