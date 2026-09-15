<?php
/**
 * Pied de page du site public (version maquette, enrichie au lot 1.8).
 */
$columns = [
    'Rechercher' => [
        ['Acheter un bien', 'acheter'],
        ['Louer un bien', 'louer'],
        ['Location meublée', 'location-meublee'],
        ['Terrains à vendre', 'acheter/terrains'],
        ['Bureaux & commerces', 'louer/commercial-bureaux'],
    ],
    'Vendre ou louer' => [
        ['Déposer un bien', 'deposer-un-bien'],
        ['Trouver une agence', 'agences'],
        ['Devenir agence partenaire', 'devenir-partenaire'],
        ['Comment ça marche', 'comment-ca-marche'],
    ],
    'immobilier.abidjan.net' => [
        ['À propos', 'a-propos'],
        ['Actualités immobilières', 'actualites'],
        ['Contact', 'contact'],
    ],
];
?>
<footer class="im-footer">
  <div class="im-container">
    <div class="im-footer__top">
      <div>
        <a class="im-footer__brand" href="<?= e(url()) ?>" aria-label="immobilier.abidjan.net — accueil">
          <img src="<?= e(asset('img/brand/logo-immobilier-abidjan-net-blanc.png')) ?>" alt="" width="428" height="96" loading="lazy">
        </a>
        <p class="im-footer__pitch">Les annonces immobilières des agences partenaires en Côte d’Ivoire, vérifiées avant publication.</p>
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
      <span>© <?= e(date('Y')) ?> <?= e(site()->name ?? config('app.name')) ?></span>
      <span>
        <a href="<?= e(url('mentions-legales')) ?>">Mentions légales</a> ·
        <a href="<?= e(url('conditions-generales')) ?>">CGU</a> ·
        <a href="<?= e(url('politique-de-confidentialite')) ?>">Confidentialité</a>
      </span>
    </div>
  </div>
</footer>
