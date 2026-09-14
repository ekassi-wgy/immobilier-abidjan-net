<?php
/**
 * Liste des annonces (modèle de page « liste » du back-office).
 *
 * @var array $user
 * @var array $statusTabs  Liste de ['slug' (''|en-attente|…), 'label', 'count']
 * @var array $filters     ['q', 'statut', 'categorie', 'commune', 'agence']
 * @var array $categories  [id => libellé]
 * @var array $communes    [id => libellé]
 * @var array $agencies    [id => libellé] (vide pour une agence)
 * @var array $properties  Liste de ['reference','title','category','transaction','commune','district','price','currency','period'?,'agency','status','updated','featured']
 * @var array $pagination  ['page','pages','total','perPage']
 * @var string $csrfToken
 */
$isAgency = $user['role'] === 'agency';
$isStaff = !$isAgency;
$hasFilters = array_filter(array_diff_key($filters, ['statut' => true])) !== [];
?>
<?= cmsadmin_partial('page-header', [
    'title' => $isAgency ? 'Mes annonces' : 'Annonces',
    'subtitle' => $isAgency
        ? 'Chaque création ou modification est soumise à validation avant publication.'
        : 'Toutes les annonces des agences partenaires et de la plateforme.',
    'breadcrumb' => [['label' => 'Tableau de bord', 'url' => '/'], ['label' => 'Annonces']],
    'actions' => [['label' => 'Ajouter une annonce', 'url' => 'annonces/nouvelle', 'icon' => 'mdi-plus']],
]) ?>

<section class="card im-panel im-panel--flush">
  <nav class="im-tabs" aria-label="Filtrer par statut">
    <?php foreach ($statusTabs as $tab): $current = ($filters['statut'] ?? '') === $tab['slug']; ?>
    <a class="im-tabs__item<?= $current ? ' is-current' : '' ?>" href="<?= e(cmsadmin_url('annonces' . ($tab['slug'] !== '' ? '?statut=' . $tab['slug'] : ''))) ?>"<?= $current ? ' aria-current="page"' : '' ?>>
      <?= e($tab['label']) ?> <span class="im-tabs__count"><?= e(format_number($tab['count'])) ?></span>
    </a>
    <?php endforeach; ?>
  </nav>

  <form class="im-filters" method="get" action="<?= e(cmsadmin_url('annonces')) ?>">
    <?php if (!empty($filters['statut'])): ?><input type="hidden" name="statut" value="<?= e($filters['statut']) ?>"><?php endif; ?>
    <div class="im-filters__search">
      <span class="mdi mdi-magnify" aria-hidden="true"></span>
      <label class="visually-hidden" for="f-q">Recherche</label>
      <input class="form-control" id="f-q" type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Référence, titre, quartier…">
    </div>
    <div class="im-filters__field">
      <label class="visually-hidden" for="f-categorie">Catégorie</label>
      <select class="form-select" id="f-categorie" name="categorie" data-select2 data-placeholder="Toutes catégories">
        <option value=""></option>
        <?php foreach ($categories as $id => $label): ?>
        <option value="<?= e($id) ?>"<?= (string) ($filters['categorie'] ?? '') === (string) $id ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="im-filters__field">
      <label class="visually-hidden" for="f-commune">Commune</label>
      <select class="form-select" id="f-commune" name="commune" data-select2 data-placeholder="Toutes communes">
        <option value=""></option>
        <?php foreach ($communes as $id => $label): ?>
        <option value="<?= e($id) ?>"<?= (string) ($filters['commune'] ?? '') === (string) $id ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($isStaff): ?>
    <div class="im-filters__field">
      <label class="visually-hidden" for="f-agence">Agence</label>
      <select class="form-select" id="f-agence" name="agence" data-select2 data-placeholder="Toutes agences">
        <option value=""></option>
        <?php foreach ($agencies as $id => $label): ?>
        <option value="<?= e($id) ?>"<?= (string) ($filters['agence'] ?? '') === (string) $id ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="im-filters__actions">
      <button class="btn btn-primary" type="submit">Filtrer</button>
      <?php if ($hasFilters): ?>
      <a class="im-link-muted" href="<?= e(cmsadmin_url('annonces' . (!empty($filters['statut']) ? '?statut=' . $filters['statut'] : ''))) ?>">Réinitialiser</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($properties === []): ?>
    <?= cmsadmin_partial('empty-state', [
        'icon' => 'mdi-home-search-outline',
        'title' => 'Aucune annonce trouvée',
        'text' => $hasFilters ? 'Essayez d’élargir vos critères de recherche.' : 'Les annonces créées apparaîtront ici.',
        'action' => ['label' => 'Ajouter une annonce', 'url' => 'annonces/nouvelle'],
    ]) ?>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table im-table im-table--list">
      <thead>
        <tr>
          <th scope="col">Bien</th>
          <th scope="col">Localisation</th>
          <th scope="col" class="text-end">Prix</th>
          <?php if ($isStaff): ?><th scope="col">Agence</th><?php endif; ?>
          <th scope="col">Statut</th>
          <th scope="col">Mise à jour</th>
          <th scope="col"><span class="visually-hidden">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($properties as $property): $ref = $property['reference']; ?>
        <tr>
          <td>
            <div class="im-property-cell">
              <span class="im-thumb" aria-hidden="true"><span class="mdi mdi-home-outline"></span></span>
              <div>
                <a class="im-property-cell__title" href="<?= e(cmsadmin_url('annonces/' . $ref . '/modifier')) ?>"><?= e($property['title']) ?></a>
                <span class="im-property-cell__meta">
                  <?= e($ref) ?> · <?= e($property['category']) ?>
                  <?php if (!empty($property['featured'])): ?><span class="im-tag">À la une</span><?php endif; ?>
                </span>
              </div>
            </div>
          </td>
          <td>
            <span class="im-cell-main"><?= e($property['commune']) ?></span>
            <span class="im-cell-sub"><?= e($property['district']) ?></span>
          </td>
          <td class="text-end">
            <span class="im-cell-main im-num"><?= e(format_price($property['price'], $property['currency'])) ?></span>
            <span class="im-cell-sub"><?= e($property['transaction']) ?><?= !empty($property['period']) ? ' · ' . e($property['period']) : '' ?></span>
          </td>
          <?php if ($isStaff): ?><td><?= e($property['agency']) ?></td><?php endif; ?>
          <td><?= cmsadmin_partial('status-badge', ['status' => $property['status']]) ?></td>
          <td class="im-muted"><?= e($property['updated']) ?></td>
          <td class="text-end">
            <div class="dropdown">
              <button class="im-icon-btn im-icon-btn--sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions pour <?= e($ref) ?>">
                <span class="mdi mdi-dots-horizontal" aria-hidden="true"></span>
              </button>
              <div class="dropdown-menu dropdown-menu-end im-dropdown">
                <a class="dropdown-item im-dropdown__item" href="<?= e(cmsadmin_url('annonces/' . $ref . '/modifier')) ?>"><span class="mdi mdi-pencil-outline" aria-hidden="true"></span> Modifier</a>
                <?php if ($property['status'] === 'published'): ?>
                <a class="dropdown-item im-dropdown__item" href="<?= e(url('annonces/' . $ref)) ?>" target="_blank" rel="noopener"><span class="mdi mdi-open-in-new" aria-hidden="true"></span> Voir sur le site</a>
                <?php endif; ?>
                <?php if ($isStaff && $property['status'] === 'pending'): ?>
                <a class="dropdown-item im-dropdown__item" href="<?= e(cmsadmin_url('annonces/' . $ref . '/validation')) ?>"><span class="mdi mdi-check-decagram-outline" aria-hidden="true"></span> Valider ou rejeter</a>
                <?php endif; ?>
                <?php if (in_array($property['status'], ['published', 'unpublished', 'expired'], true)): ?>
                <form method="post" action="<?= e(cmsadmin_url('annonces/' . $ref . '/archiver')) ?>">
                  <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                  <button class="dropdown-item im-dropdown__item" type="submit" data-confirm="Archiver cette annonce (bien vendu ou loué) ?"><span class="mdi mdi-archive-outline" aria-hidden="true"></span> Archiver</button>
                </form>
                <?php endif; ?>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?= cmsadmin_partial('pagination', $pagination + [
      'path' => 'annonces',
      'query' => array_filter($filters),
  ]) ?>
  <?php endif; ?>
</section>
