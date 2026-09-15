<?php
/**
 * @var string $title
 * @var array  $plugins
 */
$plugins ??= [];
?>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#143D8A">
  <title><?= e($title) ?> · cmsadmin · <?= e(site()->name ?? config('app.name')) ?></title>

  <link rel="preload" href="<?= e(url('assets/fonts/plus-jakarta-sans/plus-jakarta-sans-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= e(cmsadmin_asset('vendors/mdi/css/materialdesignicons.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(cmsadmin_asset('vendors/css/vendor.bundle.base.css')) ?>">
  <?php if (in_array('leaflet', $plugins, true)): ?>
  <link rel="stylesheet" href="<?= e(cmsadmin_asset('vendors/leaflet/leaflet.css')) ?>">
  <?php endif; ?>
  <?php if (in_array('select2', $plugins, true)): ?>
  <link rel="stylesheet" href="<?= e(cmsadmin_asset('vendors/select2/select2.min.css')) ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= e(cmsadmin_asset('css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(cmsadmin_asset('css/cmsadmin.css')) ?>">

  <link rel="icon" type="image/png" sizes="32x32" href="<?= e(cmsadmin_asset('images/favicon-32.png')) ?>">
  <link rel="apple-touch-icon" href="<?= e(cmsadmin_asset('images/apple-touch-icon.png')) ?>">
</head>
