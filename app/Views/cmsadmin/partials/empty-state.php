<?php
/**
 * @var string      $title
 * @var string      $text
 * @var string      $icon
 * @var array|null  $action ['label', 'url']
 */
$icon ??= 'mdi-folder-outline';
$action ??= null;
?>
<div class="im-empty">
  <span class="im-empty__icon mdi <?= e($icon) ?>" aria-hidden="true"></span>
  <p class="im-empty__title"><?= e($title) ?></p>
  <p class="im-empty__text"><?= e($text) ?></p>
  <?php if ($action !== null): ?>
  <a class="btn btn-primary" href="<?= e(cmsadmin_url($action['url'])) ?>"><?= e($action['label']) ?></a>
  <?php endif; ?>
</div>
