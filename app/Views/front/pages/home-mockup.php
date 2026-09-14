<?php
/**
 * Maquette de l'accueil (lot 0.3) : hero + recherche + biens à la une.
 * La page d'accueil complète est réalisée au lot 1.8.
 *
 * @var array $hero       Données du hero (voir partials/hero)
 * @var array $properties Annonces à la une
 */
?>
<?= render_view('front/partials/hero', $hero) ?>

<section class="im-section" aria-labelledby="featured-title">
  <div class="im-container">
    <header class="im-section-head">
      <div class="im-section-head__text">
        <p class="im-eyebrow">Sélection de la semaine</p>
        <h2 class="im-h2 im-section-head__title" id="featured-title">Biens à la une</h2>
        <p class="im-lead im-section-head__lead">Des annonces complètes, photographiées et vérifiées par notre équipe.</p>
      </div>
      <a class="im-link" href="<?= e(url('acheter')) ?>">Toutes les annonces <?= icon('arrow-right', 'im-icon--arrow') ?></a>
    </header>

    <div class="im-grid-cards">
      <?php foreach ($properties as $index => $property): ?>
        <?= render_view('front/partials/property-card', ['property' => $property, 'eager' => false]) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
