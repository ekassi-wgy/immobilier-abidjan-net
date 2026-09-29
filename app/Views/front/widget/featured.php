<?php
/**
 * Fragment « Biens à la une » inclus sur Abidjan.net (WidgetController, lot 2.6).
 *
 * Racine fantôme déclarative : tout ce qui est dans <template shadowrootmode> est isolé de la page
 * hôte. Le lien placé à côté n'est visible que si le navigateur ne connaît pas les racines fantômes
 * déclaratives (il n'y a pas de <slot> pour l'afficher sinon).
 *
 * @var list<array<string,mixed>> $cards           Cartes aux URL déjà absolues
 * @var string                    $allListingsUrl
 * @var string                    $homeUrl
 * @var string                    $css             public/assets/css/widget.css
 * @var array{latin:string,latinExt:string} $fonts
 */
$name = site()->name;
?>
<!-- <?= e($name) ?> · <?= e(__('front.widget.title')) ?> -->
<style>
@font-face{font-family:"IAN Plus Jakarta Sans";font-style:normal;font-weight:200 800;font-display:swap;src:url("<?= e($fonts['latin']) ?>") format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
@font-face{font-family:"IAN Plus Jakarta Sans";font-style:normal;font-weight:200 800;font-display:swap;src:url("<?= e($fonts['latinExt']) ?>") format("woff2");unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF}
</style>
<div class="ian-widget" data-ian-widget-rendered>
<template shadowrootmode="open">
<style><?= $css ?></style>
<section class="im-widget" aria-labelledby="ian-widget-title">
  <header class="im-section-head">
    <div class="im-section-head__text">
      <h2 class="im-h2" id="ian-widget-title"><?= e(__('front.widget.title')) ?></h2>
      <p class="im-lead im-section-head__lead"><?= e(__('front.widget.lead', ['name' => $name])) ?></p>
    </div>
    <a class="im-link" href="<?= e($allListingsUrl) ?>"><?= e(__('front.widget.all_listings')) ?> <?= icon_inline('arrow-right', 'im-icon--arrow') ?></a>
  </header>

  <div class="im-widget__cards">
    <?php foreach ($cards as $card): ?>
      <?= render_view('front/partials/property-card', ['property' => $card, 'embed' => true]) ?>
    <?php endforeach; ?>
  </div>
</section>
</template>
<a href="<?= e($homeUrl) ?>"><?= e(__('front.widget.fallback', ['name' => $name])) ?></a>
</div>
