<?php
/**
 * Page d'erreur du back-office (404, 403, 500).
 *
 * @var int $code
 */
$messages = [
    403 => ['Accès refusé', 'Vous n’avez pas les droits nécessaires pour consulter cette page.'],
    404 => ['Page introuvable', 'L’adresse demandée n’existe pas ou a été déplacée.'],
    500 => ['Une erreur est survenue', 'Le problème a été enregistré. Réessayez dans un instant ou revenez au tableau de bord.'],
];
[$heading, $text] = $messages[$code] ?? $messages[500];
?>
<main class="im-error">
  <a href="<?= e(cmsadmin_url()) ?>" class="im-error__logo">
    <img src="<?= e(cmsadmin_asset('images/logo-immobilier-abidjan-net.png')) ?>" alt="immobilier.abidjan.net" width="178" height="40">
  </a>
  <p class="im-error__code" aria-hidden="true"><?= e($code) ?></p>
  <h1 class="im-error__title"><?= e($heading) ?></h1>
  <p class="im-error__text"><?= e($text) ?></p>
  <a class="btn btn-primary" href="<?= e(cmsadmin_url()) ?>">Retour au tableau de bord</a>
</main>
