<?php
/**
 * Mise en page du site public.
 *
 * @var string      $title          Titre de la page (sans le nom du site)
 * @var string      $description    Méta description
 * @var string      $content        HTML de la page
 * @var bool        $headerOverlay  En-tête transparent au-dessus d'un hero
 * @var string|array|null $preloadImage URL de l'image LCP, ou ['desktop' => URL, 'mobile' => URL]
 * @var string|null $canonical      URL canonique absolue
 * @var string|null $ogImage        URL absolue de l'image de partage
 * @var array|null  $schema         Données structurées Schema.org (JSON-LD)
 * @var array       $pageScripts    Scripts propres à la page (chemins relatifs à public/assets)
 * @var array       $pageStyles     Feuilles de style propres à la page (Leaflet sur la vue carte)
 * @var bool        $noindex
 */
$headerOverlay ??= false;
$preloadImage ??= null;
$canonical ??= null;
$ogImage ??= null;
$schema ??= null;
$pageScripts ??= [];
$pageStyles ??= [];
$noindex ??= false;
$siteName = site()->name ?? config('app.name');
?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($title) ?> · <?= e($siteName) ?></title>
  <meta name="description" content="<?= e($description) ?>">
  <?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
  <?php if ($canonical !== null): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif; ?>
  <meta name="theme-color" content="#0B2358">

  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= e($siteName) ?>">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($description) ?>">
  <meta property="og:locale" content="<?= e(str_replace('-', '_', locale())) ?>">
  <?php if ($canonical !== null): ?><meta property="og:url" content="<?= e($canonical) ?>"><?php endif; ?>
  <?php if ($ogImage !== null): ?>
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <?php else: ?>
  <meta name="twitter:card" content="summary">
  <?php endif; ?>

  <link rel="preload" href="<?= e(url('assets/fonts/plus-jakarta-sans/plus-jakarta-sans-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <?php if (is_array($preloadImage)): ?>
  <link rel="preload" as="image" type="image/webp" href="<?= e($preloadImage['mobile']) ?>" media="(max-width: 767px)" fetchpriority="high">
  <link rel="preload" as="image" type="image/webp" href="<?= e($preloadImage['desktop']) ?>" media="(min-width: 768px)" fetchpriority="high">
  <?php elseif ($preloadImage !== null): ?>
  <link rel="preload" as="image" type="image/webp" href="<?= e($preloadImage) ?>" fetchpriority="high">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <?php foreach ($pageStyles as $style): ?>
  <link rel="stylesheet" href="<?= e(asset($style)) ?>">
  <?php endforeach; ?>

  <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('img/brand/favicon-32.png')) ?>">
  <link rel="apple-touch-icon" href="<?= e(asset('img/brand/apple-touch-icon.png')) ?>">
  <?php if ($schema !== null): ?>
  <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <?php endif; ?>
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
