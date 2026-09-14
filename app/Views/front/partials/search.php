<?php
/**
 * Module de recherche rapide.
 *
 * @var array $transactions  Liste de ['slug', 'label']
 * @var array $propertyTypes Liste de ['slug', 'label']
 * @var array $budgets       [slug transaction => [valeur => libellé]]
 * @var string $locationPlaceholder
 */
$current = $transactions[0]['slug'];
?>
<form class="im-search" action="<?= e(url($current)) ?>" method="get" role="search" data-search data-budgets="<?= e(json_encode($budgets, JSON_UNESCAPED_UNICODE)) ?>">
  <div class="im-tabs im-search__tabs" role="tablist" aria-label="Type de transaction">
    <?php foreach ($transactions as $index => $transaction): ?>
    <button class="im-tabs__tab" type="button" role="tab" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>" data-search-transaction="<?= e($transaction['slug']) ?>" data-action="<?= e(url($transaction['slug'])) ?>">
      <?= e($transaction['label']) ?>
    </button>
    <?php endforeach; ?>
  </div>

  <div class="im-search__fields">
    <div class="im-search__field">
      <label class="im-search__label" for="search-type">Type de bien</label>
      <select class="im-search__input" id="search-type" name="type">
        <option value="">Tous les biens</option>
        <?php foreach ($propertyTypes as $type): ?>
        <option value="<?= e($type['slug']) ?>"><?= e($type['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="im-search__field">
      <label class="im-search__label" for="search-location">Localisation</label>
      <input class="im-search__input" id="search-location" name="lieu" type="text" placeholder="<?= e($locationPlaceholder ?? 'Ville, commune ou quartier') ?>" autocomplete="off">
    </div>
    <div class="im-search__field">
      <label class="im-search__label" for="search-budget">Budget maximum</label>
      <select class="im-search__input" id="search-budget" name="prix_max" data-search-budget>
        <option value="">Indifférent</option>
        <?php foreach ($budgets[$current] as $value => $label): ?>
        <option value="<?= e($value) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="im-btn im-btn--lg im-search__submit" type="submit">
      <?= icon('search') ?> Rechercher
    </button>
  </div>

  <div class="im-search__footer">
    <a href="<?= e(url('acheter?vue=carte')) ?>"><?= icon('view-map') ?> Rechercher sur la carte</a>
    <a href="<?= e(url('agences')) ?>"><?= icon('verified') ?> Trouver une agence partenaire</a>
  </div>
</form>
