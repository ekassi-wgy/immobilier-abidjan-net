<?php
/**
 * Mise en page des écrans hors session : connexion, mot de passe oublié, erreurs.
 *
 * @var string $title
 * @var string $content
 * @var string $variant 'split' (connexion) | 'center' (erreurs)
 */
$variant ??= 'split';
?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<?= cmsadmin_partial('head', ['title' => $title, 'plugins' => []]) ?>
<body class="im-cmsadmin im-auth-body im-auth-body--<?= e($variant) ?>">
  <?= $content ?>
  <script src="<?= e(cmsadmin_asset('vendors/js/vendor.bundle.base.js')) ?>"></script>
  <script src="<?= e(cmsadmin_asset('js/cmsadmin.js')) ?>"></script>
</body>
</html>
