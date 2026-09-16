<?php

use App\Services\SearchCriteria;
use App\Support\Paginator;

/**
 * Pagination du site public : précédent / suivant et fenêtre de pages autour de la page courante.
 *
 * @var Paginator      $paginator
 * @var SearchCriteria $criteria
 */
if ($paginator->pages < 2) {
    return;
}

$current = $paginator->page;
$last = $paginator->pages;
$window = range(max(1, $current - 2), min($last, $current + 2));
$numbers = array_values(array_unique(array_merge([1], $window, [$last])));
sort($numbers);
?>
<nav class="im-pagination" aria-label="<?= e(__('front.results.pagination_label')) ?>">
  <?php if ($current > 1): ?>
  <a class="im-pagination__step" href="<?= e($criteria->pageUrl($current - 1)) ?>" rel="prev">
    <?= icon('arrow-left') ?> <span><?= e(__('front.results.previous')) ?></span>
  </a>
  <?php endif; ?>

  <ol class="im-pagination__list">
    <?php $previous = 0; foreach ($numbers as $number): ?>
    <?php if ($number - $previous > 1): ?><li class="im-pagination__gap" aria-hidden="true">…</li><?php endif; ?>
    <li>
      <?php if ($number === $current): ?>
      <span class="im-pagination__page is-current" aria-current="page"><span class="visually-hidden"><?= e(__('front.results.page_of', ['page' => $number, 'pages' => $last])) ?></span><span aria-hidden="true"><?= e($number) ?></span></span>
      <?php else: ?>
      <a class="im-pagination__page" href="<?= e($criteria->pageUrl($number)) ?>" aria-label="<?= e(__('front.results.page', ['page' => $number])) ?>"><?= e($number) ?></a>
      <?php endif; ?>
    </li>
    <?php $previous = $number; endforeach; ?>
  </ol>

  <?php if ($current < $last): ?>
  <a class="im-pagination__step" href="<?= e($criteria->pageUrl($current + 1)) ?>" rel="next">
    <span><?= e(__('front.results.next')) ?></span> <?= icon('arrow-right') ?>
  </a>
  <?php endif; ?>
</nav>
