<?php

use App\Services\SearchCriteria;

/**
 * Panneau de filtres de la page de résultats : un seul formulaire GET.
 *
 * Les choix de type de bien et de localisation sont renvoyés en paramètres (`type`, `ville`,
 * `commune`, `quartier`) ; `SearchController` les transforme en segments d'URL et redirige vers
 * l'adresse canonique. Le tri et la présentation suivent en champs cachés.
 *
 * @var SearchCriteria $criteria
 * @var array          $panel        cities, communes, districts, types, features, attributes
 * @var array          $transactions [['slug', 'label']]
 */
$currency = site()?->country->currencySymbol ?? '';
$counts = [1, 2, 3, 4, 5, 6];
?>
<form class="im-filters" id="im-filters" method="get" action="<?= e(url($criteria->transaction['slug'])) ?>" data-filters>
  <?php if ($criteria->sort !== SearchCriteria::DEFAULT_SORT): ?>
  <input type="hidden" name="tri" value="<?= e($criteria->sort) ?>">
  <?php endif; ?>
  <?php if ($criteria->view !== SearchCriteria::DEFAULT_VIEW): ?>
  <input type="hidden" name="vue" value="<?= e($criteria->view) ?>">
  <?php endif; ?>

  <div class="im-filters__head">
    <p class="im-h4" id="im-filters-title"><?= e(__('front.filters.title')) ?></p>
    <button class="im-filters__close" type="button" aria-label="<?= e(__('front.results.close_filters')) ?>" data-filters-close>
      <?= icon('close') ?>
    </button>
  </div>

  <div class="im-filters__body">
    <fieldset class="im-filters__group">
      <legend class="im-filters__legend"><?= e(__('front.filters.transaction')) ?></legend>
      <div class="im-filters__transactions">
        <?php foreach ($transactions as $transaction): $active = $transaction['slug'] === $criteria->transaction['slug']; ?>
        <a class="im-chip<?= $active ? ' is-active' : '' ?>" href="<?= e(url($transaction['slug'])) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($transaction['label']) ?></a>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <div class="im-filters__group">
      <label class="im-field__label" for="filter-type"><?= e(__('front.filters.type')) ?></label>
      <select class="im-control" id="filter-type" name="type" data-filters-auto>
        <option value=""><?= e(__('front.filters.all_types')) ?></option>
        <?php foreach ($panel['types'] as $family => $types): ?>
        <optgroup label="<?= e($family) ?>">
          <?php foreach ($types as $slug => $label): ?>
          <option value="<?= e($slug) ?>"<?= ($criteria->category['slug'] ?? '') === (string) $slug ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </optgroup>
        <?php endforeach; ?>
      </select>
    </div>

    <fieldset class="im-filters__group">
      <legend class="im-filters__legend"><?= e(__('front.filters.location')) ?></legend>

      <div class="im-field">
        <label class="im-field__label" for="filter-city"><?= e(__('front.filters.city')) ?></label>
        <select class="im-control" id="filter-city" name="ville" data-filters-auto>
          <option value=""><?= e(__('front.filters.all_cities')) ?></option>
          <?php foreach ($panel['cities'] as $city): ?>
          <option value="<?= e($city['slug']) ?>"<?= ($criteria->city['slug'] ?? '') === $city['slug'] ? ' selected' : '' ?>><?= e($city['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($panel['communes'] !== []): ?>
      <div class="im-field">
        <label class="im-field__label" for="filter-commune"><?= e(__('front.filters.commune')) ?></label>
        <select class="im-control" id="filter-commune" name="commune" data-filters-auto>
          <option value=""><?= e(__('front.filters.all_communes')) ?></option>
          <?php foreach ($panel['communes'] as $commune): ?>
          <option value="<?= e($commune['slug']) ?>"<?= ($criteria->commune['slug'] ?? '') === $commune['slug'] ? ' selected' : '' ?>><?= e($commune['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <?php if ($panel['districts'] !== []): ?>
      <div class="im-field">
        <label class="im-field__label" for="filter-district"><?= e(__('front.filters.district')) ?></label>
        <select class="im-control" id="filter-district" name="quartier" data-filters-auto>
          <option value=""><?= e(__('front.filters.all_districts')) ?></option>
          <?php foreach ($panel['districts'] as $district): ?>
          <option value="<?= e($district['slug']) ?>"<?= ($criteria->district['slug'] ?? '') === $district['slug'] ? ' selected' : '' ?>><?= e($district['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </fieldset>

    <fieldset class="im-filters__group">
      <legend class="im-filters__legend"><?= e(__('front.filters.budget')) ?> <span class="im-filters__unit"><?= e($currency) ?></span></legend>
      <div class="im-filters__pair">
        <div class="im-field">
          <label class="im-field__label" for="filter-price-min"><?= e(__('front.filters.price_min')) ?></label>
          <input class="im-control" id="filter-price-min" name="prix_min" type="number" inputmode="numeric" min="0" step="any" value="<?= e($criteria->priceMin !== null ? (string) (int) $criteria->priceMin : '') ?>">
        </div>
        <div class="im-field">
          <label class="im-field__label" for="filter-price-max"><?= e(__('front.filters.price_max')) ?></label>
          <input class="im-control" id="filter-price-max" name="prix_max" type="number" inputmode="numeric" min="0" step="any" value="<?= e($criteria->priceMax !== null ? (string) (int) $criteria->priceMax : '') ?>">
        </div>
      </div>
    </fieldset>

    <fieldset class="im-filters__group">
      <legend class="im-filters__legend"><?= e(__('front.filters.surfaces')) ?></legend>
      <div class="im-filters__pair">
        <div class="im-field">
          <label class="im-field__label" for="filter-area"><?= e(__('front.filters.area_min')) ?></label>
          <input class="im-control" id="filter-area" name="surface_min" type="number" inputmode="numeric" min="0" step="any" value="<?= e($criteria->areaMin !== null ? (string) (int) $criteria->areaMin : '') ?>">
        </div>
        <div class="im-field">
          <label class="im-field__label" for="filter-land"><?= e(__('front.filters.land_min')) ?></label>
          <input class="im-control" id="filter-land" name="terrain_min" type="number" inputmode="numeric" min="0" step="any" value="<?= e($criteria->landMin !== null ? (string) (int) $criteria->landMin : '') ?>">
        </div>
      </div>
    </fieldset>

    <fieldset class="im-filters__group">
      <legend class="im-filters__legend"><?= e(__('front.filters.rooms_title')) ?></legend>
      <?php foreach ([
          ['name' => 'pieces', 'label' => __('front.filters.rooms'), 'value' => $criteria->rooms],
          ['name' => 'chambres', 'label' => __('front.filters.bedrooms'), 'value' => $criteria->bedrooms],
          ['name' => 'sdb', 'label' => __('front.filters.bathrooms'), 'value' => $criteria->bathrooms],
      ] as $field): ?>
      <div class="im-field">
        <label class="im-field__label" for="filter-<?= e($field['name']) ?>"><?= e($field['label']) ?></label>
        <select class="im-control" id="filter-<?= e($field['name']) ?>" name="<?= e($field['name']) ?>">
          <option value=""><?= e(__('front.filters.any')) ?></option>
          <?php foreach ($counts as $count): ?>
          <option value="<?= e($count) ?>"<?= $field['value'] === $count ? ' selected' : '' ?>><?= e($count) ?>+</option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endforeach; ?>
    </fieldset>

    <?php foreach ($panel['attributes'] as $attribute): ?>
    <fieldset class="im-filters__group">
      <legend class="im-filters__legend"><?= e($attribute['label']) ?></legend>
      <?php if ($attribute['type'] === 'boolean'): ?>
      <label class="im-check">
        <input type="checkbox" name="c_<?= e($attribute['code']) ?>[]" value="1"<?= isset($criteria->attributes[$attribute['code']]) ? ' checked' : '' ?>>
        <span class="im-check__box" aria-hidden="true"><?= icon('check') ?></span>
        <span><?= e(__('front.filters.yes')) ?></span>
      </label>
      <?php else: ?>
      <div class="im-filters__options">
        <?php foreach ($attribute['options'] as $code => $label): ?>
        <label class="im-check">
          <input type="checkbox" name="c_<?= e($attribute['code']) ?>[]" value="<?= e($code) ?>"<?= isset($criteria->attributes[$attribute['code']]['values'][$code]) ? ' checked' : '' ?>>
          <span class="im-check__box" aria-hidden="true"><?= icon('check') ?></span>
          <span><?= e($label) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </fieldset>
    <?php endforeach; ?>

    <?php if ($panel['features'] !== []): ?>
    <fieldset class="im-filters__group">
      <legend class="im-filters__legend"><?= e(__('front.filters.features')) ?></legend>
      <?php foreach ($panel['features'] as $group => $features): ?>
      <p class="im-filters__subtitle"><?= e(__('front.filters.feature_group.' . $group)) ?></p>
      <div class="im-filters__options">
        <?php foreach ($features as $code => $label): ?>
        <label class="im-check">
          <input type="checkbox" name="equipements[]" value="<?= e($code) ?>"<?= isset($criteria->features[$code]) ? ' checked' : '' ?>>
          <span class="im-check__box" aria-hidden="true"><?= icon('check') ?></span>
          <span><?= e($label) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </fieldset>
    <?php endif; ?>

    <div class="im-filters__group">
      <label class="im-field__label" for="filter-keyword"><?= e(__('front.filters.keyword')) ?></label>
      <div class="im-control-icon">
        <?= icon('search') ?>
        <input class="im-control" id="filter-keyword" name="q" type="search" value="<?= e($criteria->keyword) ?>" placeholder="<?= e(__('front.filters.keyword_placeholder')) ?>" maxlength="80">
      </div>
    </div>
  </div>

  <div class="im-filters__footer">
    <button class="im-btn im-btn--block" type="submit"><?= e(__('front.filters.submit')) ?></button>
    <a class="im-link im-filters__reset" href="<?= e(url($criteria->transaction['slug'])) ?>"><?= e(__('front.filters.reset')) ?></a>
  </div>
</form>
