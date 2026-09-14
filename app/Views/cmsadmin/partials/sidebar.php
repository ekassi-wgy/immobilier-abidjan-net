<?php
/**
 * Menu latéral, filtré selon le rôle de l'utilisateur connecté.
 *
 * @var array  $user
 * @var string $activeMenu
 * @var array  $counters
 */
$menu = require APP_ROOT . '/config/cmsadmin-menu.php';
$role = $user['role'];

$canSee = static fn (array $entry): bool => in_array($role, $entry['roles'] ?? [], true);
$labelOf = static fn (array $entry): string => is_array($entry['label'])
    ? ($entry['label'][$role] ?? $entry['label']['default'])
    : $entry['label'];
$isActive = static fn (string $key): bool => $activeMenu === $key || str_starts_with($activeMenu, $key . '.');
$badgeOf = static fn (array $entry): int => (int) ($counters[$entry['badge'] ?? ''] ?? 0);

// Retire les catégories qui n'ont plus aucune entrée visible après filtrage par rôle
$visible = [];
foreach ($menu as $entry) {
    if ($canSee($entry)) {
        $visible[] = $entry;
    }
}
$visible = array_values(array_filter($visible, static function (array $entry, int $index) use ($visible): bool {
    if (!isset($entry['category'])) {
        return true;
    }
    $next = $visible[$index + 1] ?? null;

    return $next !== null && !isset($next['category']);
}, ARRAY_FILTER_USE_BOTH));
?>
<nav class="sidebar sidebar-offcanvas im-sidebar" id="sidebar" aria-label="Menu principal">
  <ul class="nav">
    <?php foreach ($visible as $entry): ?>
      <?php if (isset($entry['category'])): ?>
      <li class="nav-item nav-category"><?= e($entry['category']) ?></li>
      <?php continue; endif; ?>

      <?php
      $active = $isActive($entry['key']);
      $children = array_values(array_filter($entry['children'] ?? [], $canSee));
      $badge = $badgeOf($entry);
      $collapseId = 'menu-' . str_replace('.', '-', $entry['key']);
      ?>
      <li class="nav-item<?= $active ? ' active' : '' ?>">
        <?php if ($children === []): ?>
        <a class="nav-link" href="<?= e(cmsadmin_url($entry['url'] ?? '')) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
          <i class="menu-icon mdi <?= e($entry['icon']) ?>" aria-hidden="true"></i>
          <span class="menu-title"><?= e($labelOf($entry)) ?></span>
          <?php if ($badge > 0): ?><span class="im-count"><?= e(format_number($badge)) ?></span><?php endif; ?>
        </a>
        <?php else: ?>
        <a class="nav-link<?= $active ? '' : ' collapsed' ?>" data-bs-toggle="collapse" href="#<?= e($collapseId) ?>" aria-expanded="<?= $active ? 'true' : 'false' ?>" aria-controls="<?= e($collapseId) ?>">
          <i class="menu-icon mdi <?= e($entry['icon']) ?>" aria-hidden="true"></i>
          <span class="menu-title"><?= e($labelOf($entry)) ?></span>
          <?php if ($badge > 0 && $role !== 'agency'): ?><span class="im-count"><?= e(format_number($badge)) ?></span><?php endif; ?>
          <i class="menu-arrow" aria-hidden="true"></i>
        </a>
        <div class="collapse<?= $active ? ' show' : '' ?>" id="<?= e($collapseId) ?>">
          <ul class="nav flex-column sub-menu">
            <?php foreach ($children as $child): $childActive = $activeMenu === $child['key']; ?>
            <li class="nav-item">
              <a class="nav-link<?= $childActive ? ' active' : '' ?>" href="<?= e(cmsadmin_url($child['url'])) ?>"<?= $childActive ? ' aria-current="page"' : '' ?>>
                <?= e($labelOf($child)) ?>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</nav>
