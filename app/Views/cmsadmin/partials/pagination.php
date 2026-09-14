<?php
/**
 * Pagination serveur (conserve les filtres de la requête).
 *
 * @var int    $page    Page courante (1..n)
 * @var int    $pages   Nombre total de pages
 * @var int    $total   Nombre total d'éléments
 * @var int    $perPage
 * @var string $path    Chemin cmsadmin de la liste (ex. "annonces")
 * @var array  $query   Paramètres GET courants
 */
$query ??= [];
$link = static function (int $target) use ($path, $query): string {
    return cmsadmin_url($path) . '?' . http_build_query(array_merge($query, ['page' => $target]));
};
$from = $total === 0 ? 0 : ($page - 1) * $perPage + 1;
$to = min($total, $page * $perPage);

// Fenêtre de pages : 1 … 4 5 [6] 7 8 … 20
$window = [];
for ($i = 1; $i <= $pages; $i++) {
    if ($i === 1 || $i === $pages || abs($i - $page) <= 2) {
        $window[] = $i;
    } elseif (end($window) !== '…') {
        $window[] = '…';
    }
}
?>
<div class="im-pagination">
  <p class="im-pagination__info"><?= e(format_number($from)) ?>–<?= e(format_number($to)) ?> sur <?= e(format_number($total)) ?></p>
  <?php if ($pages > 1): ?>
  <nav aria-label="Pagination">
    <ul>
      <li><a class="im-pagination__step<?= $page <= 1 ? ' is-disabled' : '' ?>" href="<?= e($link(max(1, $page - 1))) ?>" aria-label="Page précédente"<?= $page <= 1 ? ' aria-disabled="true" tabindex="-1"' : '' ?>><span class="mdi mdi-chevron-left" aria-hidden="true"></span></a></li>
      <?php foreach ($window as $item): ?>
        <?php if ($item === '…'): ?>
        <li><span class="im-pagination__gap">…</span></li>
        <?php else: ?>
        <li><a href="<?= e($link($item)) ?>"<?= $item === $page ? ' class="is-current" aria-current="page"' : '' ?>><?= e($item) ?></a></li>
        <?php endif; ?>
      <?php endforeach; ?>
      <li><a class="im-pagination__step<?= $page >= $pages ? ' is-disabled' : '' ?>" href="<?= e($link(min($pages, $page + 1))) ?>" aria-label="Page suivante"<?= $page >= $pages ? ' aria-disabled="true" tabindex="-1"' : '' ?>><span class="mdi mdi-chevron-right" aria-hidden="true"></span></a></li>
    </ul>
  </nav>
  <?php endif; ?>
</div>
