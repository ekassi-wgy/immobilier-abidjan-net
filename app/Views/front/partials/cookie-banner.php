<?php

/**
 * Bandeau cookies (lot 1.11).
 *
 * Le site ne dépose que des cookies nécessaires à son fonctionnement : session (uniquement sur
 * les pages à formulaire), favoris du visiteur, dédoublonnage des consultations. Aucun traceur,
 * aucune mesure d'audience tierce — le bandeau informe donc, il ne conditionne rien. Le jour où
 * une mesure d'audience sera ajoutée, il faudra le transformer en véritable demande de consentement.
 *
 * @var array $pages Pages publiées par code (cookies, privacy)
 */
$policy = $pages['cookies'] ?? $pages['privacy'] ?? null;
?>
<div class="im-cookies" data-cookies hidden>
  <p class="im-cookies__text">
    <?= e(__('front.cookies.text')) ?>
    <?php if ($policy !== null): ?>
    <a class="im-link" href="<?= e(url($policy['slug'])) ?>"><?= e($policy['title']) ?></a>
    <?php endif; ?>
  </p>
  <button class="im-btn im-btn--sm" type="button" data-cookies-accept><?= e(__('front.cookies.accept')) ?></button>
</div>
