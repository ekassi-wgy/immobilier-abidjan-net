<?php
/**
 * Colonne éditoriale des écrans hors session (connexion, mot de passe oublié…). Masquée sous 992 px.
 */
?>
<section class="im-auth__aside" aria-hidden="true">
  <?= logo_picture('cmsadmin/assets/images/logo-immobilier-abidjan-net-blanc.png', ['class' => 'im-auth__logo', 'width' => 214, 'height' => 48]) ?>

  <div class="im-auth__statement">
    <p class="im-eyebrow im-eyebrow--light"><?= e(__('auth.aside.eyebrow')) ?></p>
    <p class="im-auth__headline"><?= e(__('auth.aside.headline')) ?></p>
  </div>

  <ul class="im-auth__facts">
    <li><span>01</span><?= e(__('auth.aside.fact_1')) ?></li>
    <li><span>02</span><?= e(__('auth.aside.fact_2')) ?></li>
    <li><span>03</span><?= e(__('auth.aside.fact_3')) ?></li>
  </ul>
</section>
