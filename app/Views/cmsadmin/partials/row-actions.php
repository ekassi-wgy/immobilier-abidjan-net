<?php
/**
 * Menu d'actions d'une ligne de liste.
 *
 * @var string $label  Nom de l'élément (libellé accessible du bouton)
 * @var array  $items  Liste de :
 *                     ['url' => …, 'label' => …, 'icon' => 'mdi-…']                        lien
 *                     ['post' => …, 'label' => …, 'icon' => …, 'confirm' => ?, 'danger' => ?] action POST (jeton CSRF)
 */
?>
<div class="dropdown">
  <button class="im-icon-btn im-icon-btn--sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= e(__('cmsadmin.actions_for', ['name' => $label])) ?>">
    <span class="mdi mdi-dots-horizontal" aria-hidden="true"></span>
  </button>
  <div class="dropdown-menu dropdown-menu-end im-dropdown">
    <?php foreach ($items as $item): ?>
      <?php if (isset($item['url'])): ?>
      <a class="dropdown-item im-dropdown__item" href="<?= e($item['url']) ?>"><span class="mdi <?= e($item['icon'] ?? 'mdi-chevron-right') ?>" aria-hidden="true"></span> <?= e($item['label']) ?></a>
      <?php else: ?>
      <form method="post" action="<?= e($item['post']) ?>">
        <?= csrf_field() ?>
        <button class="dropdown-item im-dropdown__item<?= !empty($item['danger']) ? ' im-dropdown__item--danger' : '' ?>" type="submit"<?= isset($item['confirm']) ? ' data-confirm="' . e($item['confirm']) . '"' : '' ?>>
          <span class="mdi <?= e($item['icon'] ?? 'mdi-chevron-right') ?>" aria-hidden="true"></span> <?= e($item['label']) ?>
        </button>
      </form>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
