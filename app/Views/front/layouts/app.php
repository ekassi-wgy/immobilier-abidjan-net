<?php
/**
 * Mise en page du site public.
 *
 * @var string      $title          Titre de la page (sans le nom du site)
 * @var string      $description    Méta description
 * @var string      $content        HTML de la page
 * @var bool        $headerOverlay  En-tête transparent au-dessus d'un hero
 * @var string|null $preloadImage   Image LCP à précharger (chemin relatif à public/assets)
 * @var array       $pageScripts    Scripts propres à la page (chemins relatifs à public/assets)
 * @var bool        $noindex
 */
$headerOverlay ??= false;
$preloadImage ??= null;
$pageScripts ??= [];
$noindex ??= false;
?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($title) ?> · immobilier.abidjan.net</title>
  <meta name="description" content="<?= e($description) ?>">
  <?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
  <meta name="theme-color" content="#0B2358">

  <link rel="preload" href="<?= e(url('assets/fonts/plus-jakarta-sans/plus-jakarta-sans-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <?php if ($preloadImage !== null): ?>
  <link rel="preload" as="image" type="image/webp" href="<?= e(url('assets/' . $preloadImage)) ?>" fetchpriority="high">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">

  <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('img/brand/favicon-32.png')) ?>">
  <link rel="apple-touch-icon" href="<?= e(asset('img/brand/apple-touch-icon.png')) ?>">
</head>
<body class="<?= $headerOverlay ? '' : 'im-has-header' ?>">
  <a class="im-skip-link" href="#contenu"><?= e(__('common.skip_to_content')) ?></a>

  <?= render_view('front/partials/header', ['overlay' => $headerOverlay]) ?>

  <main id="contenu" tabindex="-1">
    <?= $content ?>
  </main>

  <?= render_view('front/partials/footer') ?>

  <script src="<?= e(asset('js/site.js')) ?>" defer></script>
  <?php foreach ($pageScripts as $script): ?>
  <script src="<?= e(asset($script)) ?>" defer></script>
  <?php endforeach; ?>
</body>
</html>
