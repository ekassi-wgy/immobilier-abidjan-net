<?php
/**
 * En-tête de page : fil d'Ariane, titre, sous-titre, actions.
 *
 * @var string      $title
 * @var string|null $subtitle
 * @var array       $breadcrumb Liste de ['label' => string, 'url' => ?string]
 * @var array       $actions    Liste de ['label', 'url', 'icon'?, 'variant'? ('primary'|'ghost')]
 */
$subtitle ??= null;
$breadcrumb ??= [];
$actions ??= [];
?>
<header class="im-page-head">
  <div class="im-page-head__text">
    <?php if ($breadcrumb !== []): ?>
    <nav aria-label="Fil d'Ariane">
      <ol class="im-breadcrumb">
        <?php foreach ($breadcrumb as $crumb): ?>
        <li>
          <?php if (!empty($crumb['url'])): ?>
          <a href="<?= e(cmsadmin_url($crumb['url'])) ?>"><?= e($crumb['label']) ?></a>
          <?php else: ?>
          <span aria-current="page"><?= e($crumb['label']) ?></span>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ol>
    </nav>
    <?php endif; ?>
    <h1 class="im-page-head__title"><?= e($title) ?></h1>
    <?php if ($subtitle !== null): ?>
    <p class="im-page-head__subtitle"><?= e($subtitle) ?></p>
    <?php endif; ?>
  </div>

  <?php if ($actions !== []): ?>
  <div class="im-page-head__actions">
    <?php foreach ($actions as $action): ?>
    <a class="btn <?= ($action['variant'] ?? 'primary') === 'ghost' ? 'im-btn-ghost' : 'btn-primary' ?>" href="<?= e(cmsadmin_url($action['url'])) ?>">
      <?php if (!empty($action['icon'])): ?><span class="mdi <?= e($action['icon']) ?>" aria-hidden="true"></span><?php endif; ?>
      <?= e($action['label']) ?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</header>
