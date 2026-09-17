<?php
/**
 * Page d'erreur du back-office (401, 403, 404, 405, 419, 429, 500, 503).
 *
 * @var int $code
 */
$key = in_array($code, [401, 403, 404, 405, 419, 429, 500, 503], true) ? $code : 500;
?>
<main class="im-error">
  <a href="<?= e(cmsadmin_url()) ?>" class="im-error__logo">
    <?= logo_picture('cmsadmin/assets/images/logo-immobilier-abidjan-net.png', ['alt' => 'Abidjan.net Immobilier', 'width' => 178, 'height' => 40]) ?>
  </a>
  <p class="im-error__code" aria-hidden="true"><?= e($code) ?></p>
  <h1 class="im-error__title"><?= e(__("errors.{$key}.title")) ?></h1>
  <p class="im-error__text"><?= e(__("errors.{$key}.text")) ?></p>
  <?php if ($code === 419): ?>
  <a class="btn btn-primary" href="<?= e(url(app()->request()?->path() ?? 'cmsadmin')) ?>"><?= e(__('errors.reload')) ?></a>
  <?php else: ?>
  <a class="btn btn-primary" href="<?= e(cmsadmin_url()) ?>"><?= e(__('errors.back_dashboard')) ?></a>
  <?php endif; ?>
</main>
