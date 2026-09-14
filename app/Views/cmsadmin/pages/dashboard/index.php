<?php
/**
 * Tableau de bord (Super Admin, Admin Pays, Agence).
 *
 * @var array  $user
 * @var string $today              Date du jour formatée (ex. « lundi 14 septembre 2026 »)
 * @var array  $stats              Liste de ['label', 'value', 'hint', 'tone'? ('up'|'down'|'alert'), 'link'? ['label','url']]
 * @var array  $chart              ['labels' => [], 'views' => [], 'leads' => [], 'totals' => ['views','leads']]
 * @var array  $pendingProperties  Liste de ['reference','title','category','commune','price','currency','agency','submitted']
 * @var array  $topProperties      Liste de ['reference','title','commune','views','leads']
 * @var array  $latestLeads        Liste de ['name','property','channel' (form|whatsapp|phone),'received']
 */
$isAgency = $user['role'] === 'agency';
$firstName = explode(' ', trim($user['name']))[0];
$channels = [
    'form' => ['icon' => 'mdi-email-outline', 'label' => 'Formulaire'],
    'whatsapp' => ['icon' => 'mdi-whatsapp', 'label' => 'WhatsApp'],
    'phone' => ['icon' => 'mdi-phone-outline', 'label' => 'Appel'],
];
$maxViews = max(array_column($topProperties, 'views') ?: [1]);
?>
<section class="im-welcome">
  <div>
    <p class="im-eyebrow"><?= e(ucfirst($today)) ?></p>
    <h1 class="im-welcome__title">Bonjour <?= e($firstName) ?></h1>
    <p class="im-welcome__text">
      <?= $isAgency
          ? e('Voici l’activité de ' . ($user['agency_name'] ?? 'votre agence') . ' sur les 30 derniers jours.')
          : e('Voici l’activité de la plateforme sur les 30 derniers jours.') ?>
    </p>
  </div>
  <div class="im-welcome__actions">
    <?php if (!$isAgency): ?>
    <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url('annonces?statut=en-attente')) ?>">Annonces à valider</a>
    <?php endif; ?>
    <a class="btn btn-primary" href="<?= e(cmsadmin_url('annonces/nouvelle')) ?>">
      <span class="mdi mdi-plus" aria-hidden="true"></span> Ajouter une annonce
    </a>
  </div>
</section>

<section class="im-kpis" aria-label="Chiffres clés">
  <?php foreach ($stats as $stat): ?>
  <div class="im-kpi<?= ($stat['tone'] ?? '') === 'alert' ? ' im-kpi--alert' : '' ?>">
    <p class="im-kpi__label"><?= e($stat['label']) ?></p>
    <p class="im-kpi__value"><?= e(format_number($stat['value'])) ?></p>
    <p class="im-kpi__hint<?= isset($stat['tone']) ? ' is-' . e($stat['tone']) : '' ?>">
      <?php if (($stat['tone'] ?? '') === 'up'): ?><span class="mdi mdi-arrow-top-right" aria-hidden="true"></span><?php endif; ?>
      <?= e($stat['hint']) ?>
    </p>
    <?php if (!empty($stat['link'])): ?>
    <a class="im-kpi__link" href="<?= e(cmsadmin_url($stat['link']['url'])) ?>"><?= e($stat['link']['label']) ?> <span class="mdi mdi-arrow-right" aria-hidden="true"></span></a>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</section>

<div class="row g-4">
  <div class="col-xxl-8">
    <section class="card im-panel h-100">
      <header class="im-panel__head">
        <div>
          <h2 class="im-panel__title">Audience des annonces</h2>
          <p class="im-panel__subtitle">Vues des fiches et demandes de contact, par jour</p>
        </div>
        <ul class="im-legend">
          <li><span class="im-legend__swatch im-legend__swatch--line" aria-hidden="true"></span>Vues <strong><?= e(format_number($chart['totals']['views'])) ?></strong></li>
          <li><span class="im-legend__swatch im-legend__swatch--bar" aria-hidden="true"></span>Contacts <strong><?= e(format_number($chart['totals']['leads'])) ?></strong></li>
        </ul>
      </header>
      <div class="im-chart">
        <canvas id="im-audience-chart" aria-label="Évolution des vues et des contacts sur 30 jours" role="img"></canvas>
      </div>
      <script type="application/json" id="im-audience-data"><?= json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
    </section>
  </div>

  <div class="col-xxl-4">
    <section class="card im-panel h-100">
      <header class="im-panel__head">
        <div>
          <h2 class="im-panel__title">Biens les plus consultés</h2>
          <p class="im-panel__subtitle">30 derniers jours</p>
        </div>
      </header>
      <ol class="im-ranking">
        <?php foreach ($topProperties as $index => $property): ?>
        <li class="im-ranking__item">
          <span class="im-ranking__rank"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
          <div class="im-ranking__body">
            <a class="im-ranking__title" href="<?= e(cmsadmin_url('annonces/' . $property['reference'])) ?>"><?= e($property['title']) ?></a>
            <span class="im-ranking__meta"><?= e($property['commune']) ?> · <?= e(format_number($property['leads'])) ?> contacts</span>
            <span class="im-meter" aria-hidden="true"><span style="width: <?= e(round($property['views'] / $maxViews * 100)) ?>%"></span></span>
          </div>
          <span class="im-ranking__value"><?= e(format_number($property['views'])) ?><small>vues</small></span>
        </li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>

  <div class="col-xxl-8">
    <section class="card im-panel h-100">
      <header class="im-panel__head">
        <div>
          <h2 class="im-panel__title"><?= $isAgency ? 'Mes annonces en attente' : 'À valider en priorité' ?></h2>
          <p class="im-panel__subtitle"><?= $isAgency ? 'En cours d’examen par l’équipe de modération' : 'Les plus anciennes soumissions en premier' ?></p>
        </div>
        <a class="im-link" href="<?= e(cmsadmin_url('annonces?statut=en-attente')) ?>">Tout voir <span class="mdi mdi-arrow-right" aria-hidden="true"></span></a>
      </header>
      <?php if ($pendingProperties === []): ?>
        <?= cmsadmin_partial('empty-state', [
            'icon' => 'mdi-check-all',
            'title' => 'Aucune annonce en attente',
            'text' => 'Toutes les soumissions ont été traitées.',
        ]) ?>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table im-table">
          <thead>
            <tr>
              <th scope="col">Bien</th>
              <?php if (!$isAgency): ?><th scope="col">Agence</th><?php endif; ?>
              <th scope="col" class="text-end">Prix</th>
              <th scope="col">Soumise</th>
              <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingProperties as $property): ?>
            <tr>
              <td>
                <div class="im-property-cell">
                  <span class="im-thumb" aria-hidden="true"><span class="mdi mdi-home-outline"></span></span>
                  <div>
                    <span class="im-property-cell__title"><?= e($property['title']) ?></span>
                    <span class="im-property-cell__meta"><?= e($property['reference']) ?> · <?= e($property['category']) ?> · <?= e($property['commune']) ?></span>
                  </div>
                </div>
              </td>
              <?php if (!$isAgency): ?><td><?= e($property['agency']) ?></td><?php endif; ?>
              <td class="text-end im-num"><?= e(format_price($property['price'], $property['currency'])) ?></td>
              <td class="im-muted"><?= e($property['submitted']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm im-btn-ghost" href="<?= e(cmsadmin_url('annonces/' . $property['reference'])) ?>"><?= $isAgency ? 'Voir' : 'Examiner' ?></a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
  </div>

  <div class="col-xxl-4">
    <section class="card im-panel h-100">
      <header class="im-panel__head">
        <div>
          <h2 class="im-panel__title">Derniers contacts</h2>
          <p class="im-panel__subtitle">Demandes reçues des visiteurs</p>
        </div>
        <a class="im-link" href="<?= e(cmsadmin_url('contacts')) ?>">Tout voir <span class="mdi mdi-arrow-right" aria-hidden="true"></span></a>
      </header>
      <ul class="im-feed">
        <?php foreach ($latestLeads as $lead): $channel = $channels[$lead['channel']] ?? $channels['form']; ?>
        <li class="im-feed__item">
          <span class="im-feed__icon mdi <?= e($channel['icon']) ?>" title="<?= e($channel['label']) ?>" aria-hidden="true"></span>
          <div class="im-feed__body">
            <p class="im-feed__title"><?= e($lead['name']) ?></p>
            <p class="im-feed__meta"><?= e($lead['property']) ?></p>
          </div>
          <span class="im-feed__time"><?= e($lead['received']) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>
</div>
