<?php
/**
 * Module de recherche rapide (accueil et page de résultats).
 *
 * @var array  $transactions        Liste de ['slug', 'label'] (transaction_types actives)
 * @var array  $propertyTypes       [famille => [slug => libellé]] (optgroups)
 * @var array  $budgets             [slug transaction => [montant => libellé]]
 * @var string $locationPlaceholder
 * @var array  $values              Valeurs pré-remplies : ['transaction','type','lieu','prix_max']
 */
$values ??= [];
$current = $values['transaction'] ?? $transactions[0]['slug'];
$selectedType = (string) ($values['type'] ?? '');
$selectedPrice = (string) ($values['prix_max'] ?? '');
?>
<form class="im-search" action="<?= e(url($current)) ?>" method="get" role="search" aria-label="<?= e(__('front.search.label')) ?>" data-search data-budgets="<?= e(json_encode($budgets, JSON_UNESCAPED_UNICODE)) ?>">
  <div class="im-tabs im-search__tabs" role="tablist" aria-label="<?= e(__('front.search.transaction_label')) ?>">
    <?php foreach ($transactions as $transaction): $active = $transaction['slug'] === $current; ?>
    <button class="im-tabs__tab" type="button" role="tab" aria-selected="<?= $active ? 'true' : 'false' ?>" data-search-transaction="<?= e($transaction['slug']) ?>" data-action="<?= e(url($transaction['slug'])) ?>">
      <?= e($transaction['label']) ?>
    </button>
    <?php endforeach; ?>
  </div>

  <div class="im-search__fields">
    <div class="im-search__field">
      <label class="im-search__label" for="search-type"><?= e(__('front.search.property_type')) ?></label>
      <select class="im-search__input" id="search-type" name="type">
        <option value=""><?= e(__('front.search.all_types')) ?></option>
        <?php foreach ($propertyTypes as $family => $types): ?>
        <optgroup label="<?= e($family) ?>">
          <?php foreach ($types as $slug => $label): ?>
          <option value="<?= e($slug) ?>"<?= $selectedType === (string) $slug ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </optgroup>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="im-search__field">
      <label class="im-search__label" for="search-location"><?= e(__('front.search.location')) ?></label>
      <input class="im-search__input" id="search-location" name="lieu" type="text" value="<?= e($values['lieu'] ?? '') ?>" placeholder="<?= e($locationPlaceholder ?? __('front.search.location_placeholder')) ?>" autocomplete="off">
    </div>
    <div class="im-search__field">
      <label class="im-search__label" for="search-budget"><?= e(__('front.search.budget')) ?></label>
      <select class="im-search__input" id="search-budget" name="prix_max" data-search-budget>
        <option value=""><?= e(__('front.search.any_budget')) ?></option>
        <?php foreach ($budgets[$current] ?? [] as $value => $label): ?>
        <option value="<?= e($value) ?>"<?= $selectedPrice === (string) $value ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="im-btn im-btn--lg im-search__submit" type="submit">
      <?= icon('search') ?> <?= e(__('front.search.submit')) ?>
    </button>
  </div>

  <div class="im-search__footer">
    <a href="<?= e(url($current . '?vue=carte')) ?>"><?= icon('view-map') ?> <?= e(__('front.search.map_link')) ?></a>
    <a href="<?= e(url('agences')) ?>"><?= icon('verified') ?> <?= e(__('front.search.agencies_link')) ?></a>
  </div>
</form>
