<?php
/**
 * Page d'erreur du site public (403, 404, 405, 419, 429, 500, 503).
 *
 * @var int $code
 */
$known = [403, 404, 405, 419, 429, 500, 503];
$key = in_array($code, $known, true) ? $code : 500;
?>
<section class="im-error-page" aria-labelledby="erreur-titre">
  <div class="im-container im-error-page__inner">
    <div class="im-error-page__text">
      <p class="im-eyebrow"><?= e(__('errors.eyebrow', ['code' => $code])) ?></p>
      <h1 class="im-h1 im-error-page__title" id="erreur-titre"><?= e(__("errors.{$key}.title")) ?></h1>
      <p class="im-lead im-error-page__lead"><?= e(__("errors.{$key}.text")) ?></p>

      <?php if ($code !== 503): ?>
      <div class="im-error-page__actions">
        <?php if ($code === 419): ?>
        <a class="im-btn" href="<?= e(url(app()->request()?->path() ?? '')) ?>"><?= e(__('errors.reload')) ?></a>
        <a class="im-btn im-btn--ghost" href="<?= e(url()) ?>"><?= icon('arrow-left') ?> <?= e(__('common.back_home')) ?></a>
        <?php else: ?>
        <a class="im-btn" href="<?= e(url()) ?>"><?= icon('arrow-left') ?> <?= e(__('common.back_home')) ?></a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <p class="im-error-page__code im-num" aria-hidden="true"><?= e($code) ?></p>
  </div>
</section>
