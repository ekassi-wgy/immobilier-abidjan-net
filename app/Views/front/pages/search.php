<?php

use App\Services\SearchCriteria;
use App\Support\Paginator;

/**
 * Page de résultats (lot 1.9) : /acheter/…, /louer/…
 *
 * @var SearchCriteria $criteria
 * @var string         $heading    Titre H1 (« Appartements à vendre à Cocody »)
 * @var int            $total
 * @var array          $results    Cartes annonce de la page courante
 * @var array          $points     Points de la vue carte
 * @var Paginator      $paginator
 * @var array          $panel      Données du panneau de filtres
 * @var array          $breadcrumb [['label', 'path', 'current']]
 * @var array          $sorts      [clé => libellé]
 * @var array          $transactions
 * @var string|null    $seoIntro   Texte d'introduction saisi dans « Balises méta » (lot 2.1)
 */
$seoIntro ??= null;
$chips = $criteria->chips();
$filterCount = $criteria->filterCount();
$isMap = $criteria->view === 'carte';
$cardVariant = $criteria->view === 'liste' ? 'row' : 'grid';
?>
<div class="im-results<?= $isMap ? ' im-results--map' : '' ?>">

  <div class="im-results__top">
    <div class="im-container">
      <nav class="im-breadcrumb" aria-label="<?= e(__('front.results.breadcrumb_label')) ?>">
        <ol>
          <?php foreach ($breadcrumb as $item): ?>
          <li>
            <?php if ($item['current']): ?>
            <span aria-current="page"><?= e($item['label']) ?></span>
            <?php else: ?>
            <a href="<?= e(url($item['path'])) ?>"><?= e($item['label']) ?></a>
            <?= icon('caret-right') ?>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ol>
      </nav>

      <h1 class="im-h2 im-results__title"><?= e($heading) ?></h1>
      <p class="im-results__count im-num"><?= e(__n('front.results.count', $total)) ?></p>
      <?php if ($seoIntro !== null): ?>
      <p class="im-results__intro"><?= e($seoIntro) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="im-container im-results__layout">
    <!-- Sans JavaScript, le panneau reste affiché au fil du contenu : c'est `search.js` qui
         le transforme en tiroir sous 992 px et qui révèle le bouton « Filtres ». -->
    <aside class="im-results__aside" id="im-filters-panel" data-filters-panel>
      <?= render_view('front/partials/search-filters', [
          'criteria' => $criteria,
          'panel' => $panel,
          'transactions' => $transactions,
      ]) ?>
    </aside>

    <div class="im-results__main">
      <div class="im-toolbar">
        <button class="im-chip im-toolbar__filters" type="button" aria-controls="im-filters-panel" aria-expanded="false" data-filters-open hidden>
          <?= icon('filters') ?>
          <span><?= e(__('front.results.open_filters')) ?></span>
          <?php if ($filterCount > 0): ?><span class="im-chip__count"><?= e($filterCount) ?></span><?php endif; ?>
        </button>

        <div class="im-toolbar__spacer"></div>

        <div class="im-field im-toolbar__sort">
          <label class="visually-hidden" for="results-sort"><?= e(__('front.results.sort_label')) ?></label>
          <select class="im-control im-control--sm" id="results-sort" data-sort>
            <?php foreach ($sorts as $key => $label): ?>
            <option value="<?= e($criteria->sortUrl($key)) ?>"<?= $criteria->sort === $key ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="im-toolbar__views" role="group" aria-label="<?= e(__('front.results.view_label')) ?>">
          <?php foreach (['grille' => 'view-grid', 'liste' => 'view-list', 'carte' => 'view-map'] as $view => $iconName): ?>
          <a class="im-toolbar__view<?= $criteria->view === $view ? ' is-active' : '' ?>"
             href="<?= e($criteria->viewUrl($view)) ?>"
             title="<?= e(__('front.results.view.' . $view)) ?>"<?= $criteria->view === $view ? ' aria-current="true"' : '' ?>>
            <?= icon($iconName) ?>
            <span class="visually-hidden"><?= e(__('front.results.view.' . $view)) ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($chips !== []): ?>
      <div class="im-results__chips" aria-label="<?= e(__('front.results.active_filters')) ?>">
        <?php foreach ($chips as $chip): ?>
        <a class="im-chip im-chip--removable" href="<?= e($chip['url']) ?>" aria-label="<?= e(__('front.results.remove_filter', ['label' => $chip['label']])) ?>">
          <span><?= e($chip['label']) ?></span>
          <?= icon('close') ?>
        </a>
        <?php endforeach; ?>
        <a class="im-link im-results__reset" href="<?= e($criteria->resetUrl()) ?>"><?= e(__('front.results.reset')) ?></a>
      </div>
      <?php endif; ?>

      <?php if ($isMap): ?>
      <div class="im-results__map">
        <?php if ($points !== []): ?>
        <div class="im-map" id="im-map" role="application" aria-label="<?= e(__('front.results.map_label')) ?>"
             data-map="<?= e(json_encode(array_map(static fn (array $point): array => [
                 'lat' => $point['lat'],
                 'lng' => $point['lng'],
                 'title' => $point['card']['title'],
                 'url' => url($point['card']['url']),
                 'price' => $point['card']['price'] === null
                     ? __('common.price_on_request')
                     : format_price($point['card']['price']) . price_period_label($point['card']['period']),
                 'location' => $point['card']['location'],
                 'image' => $point['card']['image']['src'],
             ], $points), JSON_UNESCAPED_UNICODE)) ?>"
             data-map-images="<?= e(asset('vendors/leaflet/images/')) ?>"></div>
        <p class="im-results__map-note im-small im-muted"><?= icon('info') ?> <?= e(__('front.results.map_approximate')) ?></p>
        <?php else: ?>
        <p class="im-results__map-empty"><?= e(__('front.results.map_empty')) ?></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($results === []): ?>
      <div class="im-empty">
        <span class="im-empty__icon" aria-hidden="true"><?= icon('search') ?></span>
        <p class="im-h3"><?= e(__($criteria->hasFilters() ? 'front.results.empty_title' : 'front.results.empty_none_title')) ?></p>
        <p class="im-lead"><?= e(__($criteria->hasFilters() ? 'front.results.empty_text' : 'front.results.empty_none_text')) ?></p>
        <?php if ($chips !== []): ?>
        <a class="im-btn im-btn--outline" href="<?= e($criteria->resetUrl()) ?>"><?= e(__('front.results.empty_reset')) ?></a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="<?= $cardVariant === 'row' ? 'im-stack-cards' : 'im-grid-cards' ?> im-results__list">
        <?php foreach ($results as $index => $property): ?>
        <?= render_view('front/partials/property-card', [
            'property' => $property,
            'variant' => $cardVariant,
            'eager' => $index < 3 && !$isMap,
        ]) ?>
        <?php endforeach; ?>
      </div>

      <?= render_view('front/partials/pagination', ['paginator' => $paginator, 'criteria' => $criteria]) ?>
      <?php endif; ?>
    </div>
  </div>
</div>
