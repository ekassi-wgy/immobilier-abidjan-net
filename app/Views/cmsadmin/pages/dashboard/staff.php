<?php
/**
 * Tableau de bord de l'équipe interne (lot 1.12) : chiffres réels du pays du site courant.
 * Il remplace la maquette de prévisualisation qui occupait cet écran jusqu'ici.
 *
 * @var array<string,mixed>       $user
 * @var array<string,int>         $counts       Annonces par statut + 'revision'
 * @var list<array<string,mixed>> $kpis         ['label','value','hint','tone'?,'link'?]
 * @var array                     $audience     ['labels','views','leads','totals','has_data']
 * @var int                       $audienceDays
 * @var int                       $expiryDays
 * @var list<array<string,mixed>> $top          Biens les plus consultés
 * @var list<array<string,mixed>> $topAgencies  Agences les plus consultées
 * @var list<array<string,mixed>> $toReview     Annonces à valider (dépôts et modifications)
 * @var list<array<string,mixed>> $expiring     Annonces qui expirent bientôt
 * @var list<array<string,mixed>> $recent       Dernières annonces du pays
 * @var list<array<string,mixed>> $leads        Dernières demandes de contact
 * @var int                       $newRequests  Demandes de partenariat non traitées
 * @var string                    $country
 * @var array                     $commission   ['mode','label'] — null tant que non décidé
 */
$firstName = explode(' ', trim((string) $user['name']))[0];
$maxViews = max([1, ...array_map(static fn (array $row): int => (int) $row['views'], $top)]);
$maxAgencyViews = max([1, ...array_map(static fn (array $row): int => (int) $row['views'], $topAgencies)]);
$propertyUrl = static fn (array $row): string => cmsadmin_url('annonces/' . $row['reference']);
$chartData = ['labels' => $audience['labels'], 'views' => $audience['views'], 'leads' => $audience['leads'],
    'legend' => ['views' => __('properties.views'), 'leads' => __('properties.leads')]];
?>
<section class="im-welcome">
  <div>
    <p class="im-eyebrow"><?= e($country) ?></p>
    <h1 class="im-welcome__title"><?= e(__('dashboard.hello', ['name' => $firstName])) ?></h1>
    <p class="im-welcome__text"><?= e(__('dashboard.intro_staff', ['days' => $audienceDays])) ?></p>
  </div>
  <div class="im-welcome__actions">
    <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url('exports')) ?>"><span class="mdi mdi-tray-arrow-down" aria-hidden="true"></span> <?= e(__('exports.title')) ?></a>
    <a class="btn btn-primary" href="<?= e(cmsadmin_url('annonces/nouvelle')) ?>"><span class="mdi mdi-plus" aria-hidden="true"></span> <?= e(__('properties.create')) ?></a>
  </div>
</section>

<?php if ($newRequests > 0): ?>
<div class="im-flash im-flash--info" role="status">
  <span class="mdi mdi-handshake-outline" aria-hidden="true"></span>
  <p>
    <?= e(__n('dashboard.partner_requests', $newRequests)) ?>
    <a href="<?= e(cmsadmin_url('demandes-partenariat')) ?>"><?= e(__('dashboard.see_requests')) ?></a>
  </p>
</div>
<?php endif; ?>

<?php if ($commission['mode'] === null): ?>
<div class="im-flash im-flash--info" role="status">
  <span class="mdi mdi-percent-outline" aria-hidden="true"></span>
  <p>
    <?= e(__('dashboard.commission_undefined')) ?>
    <a href="<?= e(cmsadmin_url('parametres')) ?>"><?= e(__('dashboard.set_commission')) ?></a>
  </p>
</div>
<?php endif; ?>

<section class="im-kpis" aria-label="<?= e(__('dashboard.key_figures')) ?>">
  <?php foreach ($kpis as $kpi): ?>
  <div class="im-kpi<?= ($kpi['tone'] ?? '') === 'alert' ? ' im-kpi--alert' : '' ?>">
    <p class="im-kpi__label"><?= e($kpi['label']) ?></p>
    <p class="im-kpi__value"><?= e(format_number((int) $kpi['value'])) ?></p>
    <p class="im-kpi__hint"><?= e($kpi['hint']) ?></p>
    <?php if (isset($kpi['link'])): ?>
    <a class="im-kpi__link" href="<?= e(cmsadmin_url($kpi['link']['url'])) ?>"><?= e($kpi['link']['label']) ?> <span class="mdi mdi-arrow-right" aria-hidden="true"></span></a>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</section>

<div class="row g-4">
  <div class="col-xxl-8">
    <section class="card im-panel h-100">
      <header class="im-panel__head">
        <div>
          <h2 class="im-panel__title"><?= e(__('dashboard.audience_staff')) ?></h2>
          <p class="im-panel__subtitle"><?= e(__('dashboard.audience_subtitle_staff', ['days' => $audienceDays])) ?></p>
        </div>
        <?php if ($audience['has_data']): ?>
        <ul class="im-legend">
          <li><span class="im-legend__swatch im-legend__swatch--line" aria-hidden="true"></span><?= e(__('properties.views')) ?> <strong><?= e(format_number($audience['totals']['views'])) ?></strong></li>
          <li><span class="im-legend__swatch im-legend__swatch--bar" aria-hidden="true"></span><?= e(__('properties.leads')) ?> <strong><?= e(format_number($audience['totals']['leads'])) ?></strong></li>
        </ul>
        <?php endif; ?>
      </header>
      <?php if ($audience['has_data']): ?>
      <div class="im-chart">
        <canvas id="im-audience-chart" aria-label="<?= e(__('dashboard.audience_chart_label', ['days' => $audienceDays])) ?>" role="img"></canvas>
      </div>
      <script type="application/json" id="im-audience-data"><?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
      <?php else: ?>
        <?= cmsadmin_partial('empty-state', [
            'icon' => 'mdi-chart-line-variant',
            'title' => __('dashboard.audience_empty_title'),
            'text' => __('dashboard.audience_empty_staff'),
        ]) ?>
      <?php endif; ?>
    </section>
  </div>

  <div class="col-xxl-4">
    <section class="card im-panel h-100">
      <header class="im-panel__head">
        <div>
          <h2 class="im-panel__title"><?= e(__('dashboard.to_review')) ?></h2>
          <p class="im-panel__subtitle"><?= e(__('dashboard.to_review_subtitle')) ?></p>
        </div>
        <?php if ($toReview !== []): ?>
        <a class="im-link" href="<?= e(cmsadmin_url('annonces?statut=en-attente')) ?>"><?= e(__('dashboard.see_all')) ?> <span class="mdi mdi-arrow-right" aria-hidden="true"></span></a>
        <?php endif; ?>
      </header>
      <?php if ($toReview === []): ?>
        <?= cmsadmin_partial('empty-state', [
            'icon' => 'mdi-check-all',
            'title' => __('dashboard.to_review_empty_title'),
            'text' => __('dashboard.to_review_empty_text'),
        ]) ?>
      <?php else: ?>
      <ul class="im-feed">
        <?php foreach ($toReview as $row): ?>
        <li class="im-feed__item">
          <span class="im-feed__icon mdi <?= e($row['kind'] === 'revision' ? 'mdi-file-document-edit-outline' : 'mdi-inbox-arrow-down-outline') ?>" aria-hidden="true"></span>
          <div class="im-feed__body">
            <p class="im-feed__title"><a class="im-cell-link" href="<?= e($propertyUrl($row)) ?>"><?= e($row['title']) ?></a></p>
            <p class="im-feed__meta">
              <?= e($row['kind'] === 'revision' ? __('properties.tabs.revision') : __('properties.status.pending')) ?>
              <?= $row['agency_name'] !== null ? ' · ' . e($row['agency_name']) : '' ?>
            </p>
          </div>
          <a class="btn btn-sm im-btn-ghost" href="<?= e($propertyUrl($row)) ?>"><?= e(__('properties.open')) ?></a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
  </div>

  <div class="col-xxl-4">
    <section class="card im-panel h-100">
      <header class="im-panel__head">
        <div>
          <h2 class="im-panel__title"><?= e(__('dashboard.top_properties')) ?></h2>
          <p class="im-panel__subtitle"><?= e(__('dashboard.last_days', ['days' => $audienceDays])) ?></p>
        </div>
      </header>
      <?php if ($top === []): ?>
        <?= cmsadmin_partial('empty-state', [
            'icon' => 'mdi-eye-outline',
            'title' => __('dashboard.top_empty_title'),
            'text' => __('dashboard.top_empty_staff'),
        ]) ?>
      <?php else: ?>
      <ol class="im-ranking">
        <?php foreach ($top as $index => $row): ?>
        <li class="im-ranking__item">
          <span class="im-ranking__rank"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
          <div class="im-ranking__body">
            <a class="im-ranking__title" href="<?= e($propertyUrl($row)) ?>"><?= e($row['title']) ?></a>
            <span class="im-ranking__meta"><?= e(trim(($row['commune_name'] ?? '') . ' · ' . __('dashboard.leads_count', ['count' => format_number((int) $row['leads'])]), ' ·')) ?></span>
            <span class="im-meter" aria-hidden="true"><span style="width: <?= e((int) round((int) $row['views'] / $maxViews * 100)) ?>%"></span></span>
          </div>
          <span class="im-ranking__value"><?= e(format_number((int) $row['views'])) ?><small><?= e(mb_strtolower(__('properties.views'))) ?></small></span>
        </li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
    </section>
  </div>

  <div class="col-xxl-4">
    <section class="card im-panel h-100">
      <header class="im-panel__head">
        <div>
          <h2 class="im-panel__title"><?= e(__('dashboard.top_agencies')) ?></h2>
          <p class="im-panel__subtitle"><?= e(__('dashboard.last_days', ['days' => $audienceDays])) ?></p>
        </div>
      </header>
      <?php if ($topAgencies === []): ?>
        <?= cmsadmin_partial('empty-state', [
            'icon' => 'mdi-office-building-outline',
            'title' => __('dashboard.top_agencies_empty_title'),
            'text' => __('dashboard.top_agencies_empty_text'),
        ]) ?>
      <?php else: ?>
      <ol class="im-ranking">
        <?php foreach ($topAgencies as $index => $row): ?>
        <li class="im-ranking__item">
          <span class="im-ranking__rank"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
          <div class="im-ranking__body">
            <a class="im-ranking__title" href="<?= e(cmsadmin_url('agences/' . $row['id'] . '/modifier')) ?>"><?= e($row['name']) ?></a>
            <span class="im-ranking__meta"><?= e(__n('dashboard.agency_listings', (int) $row['listings'])) ?> · <?= e(__('dashboard.leads_count', ['count' => format_number((int) $row['leads'])])) ?></span>
            <span class="im-meter" aria-hidden="true"><span style="width: <?= e((int) round((int) $row['views'] / $maxAgencyViews * 100)) ?>%"></span></span>
          </div>
          <span class="im-ranking__value"><?= e(format_number((int) $row['views'])) ?><small><?= e(mb_strtolower(__('properties.views'))) ?></small></span>
        </li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
    </section>
  </div>

  <div class="col-xxl-8">
    <section class="card im-panel h-100">
      <header class="im-panel__head">
        <div>
          <h2 class="im-panel__title"><?= e(__('dashboard.expiring')) ?></h2>
          <p class="im-panel__subtitle"><?= e(__('dashboard.expiring_subtitle', ['days' => $expiryDays])) ?></p>
        </div>
      </header>
      <?php if ($expiring === []): ?>
        <?= cmsadmin_partial('empty-state', [
            'icon' => 'mdi-timer-sand',
            'title' => __('dashboard.expiring_empty_title'),
            'text' => __('dashboard.expiring_empty_text'),
        ]) ?>
      <?php else: ?>
      <ul class="im-feed">
        <?php foreach ($expiring as $row): ?>
        <li class="im-feed__item">
          <span class="im-feed__icon mdi mdi-timer-sand" aria-hidden="true"></span>
          <div class="im-feed__body">
            <p class="im-feed__title"><a class="im-cell-link" href="<?= e($propertyUrl($row)) ?>"><?= e($row['title']) ?></a></p>
            <p class="im-feed__meta"><?= e(__('dashboard.expires_in', ['count' => max(0, (int) $row['days_left']), 'date' => substr((string) $row['expires_at'], 0, 10)])) ?></p>
          </div>
          <a class="btn btn-sm im-btn-ghost" href="<?= e($propertyUrl($row)) ?>"><?= e(__('properties.open')) ?></a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
  </div>

  <div class="col-xxl-4">
    <section class="card im-panel h-100">
      <header class="im-panel__head">
        <div>
          <h2 class="im-panel__title"><?= e(__('leads.latest')) ?></h2>
          <p class="im-panel__subtitle"><?= e(__('leads.latest_subtitle')) ?></p>
        </div>
        <a class="im-link" href="<?= e(cmsadmin_url('contacts')) ?>"><?= e(__('dashboard.see_all')) ?> <span class="mdi mdi-arrow-right" aria-hidden="true"></span></a>
      </header>
      <?php if ($leads === []): ?>
        <?= cmsadmin_partial('empty-state', [
            'icon' => 'mdi-email-outline',
            'title' => __('leads.empty_title'),
            'text' => __('leads.empty_text'),
        ]) ?>
      <?php else: ?>
      <ul class="im-feed">
        <?php foreach ($leads as $lead): ?>
        <li class="im-feed__item">
          <span class="im-feed__icon mdi mdi-email-outline" aria-hidden="true"></span>
          <div class="im-feed__body">
            <p class="im-feed__title"><a class="im-cell-link" href="<?= e(cmsadmin_url('contacts/' . $lead['id'])) ?>"><?= e($lead['name']) ?></a></p>
            <p class="im-feed__meta"><?= e($lead['property_title'] ?? __('leads.type.' . $lead['type'])) ?></p>
          </div>
          <span class="im-feed__time"><?= e(substr((string) $lead['created_at'], 0, 10)) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
  </div>

  <div class="col-12">
    <section class="card im-panel">
      <header class="im-panel__head">
        <div>
          <h2 class="im-panel__title"><?= e(__('dashboard.recent_properties_title_staff')) ?></h2>
          <p class="im-panel__subtitle"><?= e(__('dashboard.recent_properties_staff')) ?></p>
        </div>
        <a class="im-link" href="<?= e(cmsadmin_url('annonces')) ?>"><?= e(__('dashboard.see_all')) ?> <span class="mdi mdi-arrow-right" aria-hidden="true"></span></a>
      </header>
      <?php if ($recent === []): ?>
        <?= cmsadmin_partial('empty-state', [
            'icon' => 'mdi-home-city-outline',
            'title' => __('properties.empty_title'),
            'text' => __('properties.empty_text'),
            'action' => ['label' => __('properties.create'), 'url' => 'annonces/nouvelle'],
        ]) ?>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table im-table">
          <thead>
            <tr>
              <th scope="col"><?= e(__('properties.singular')) ?></th>
              <th scope="col" class="text-end"><?= e(__('properties.fields.price')) ?></th>
              <th scope="col" class="text-end"><?= e(__('properties.views')) ?></th>
              <th scope="col" class="text-end"><?= e(__('properties.leads')) ?></th>
              <th scope="col"><?= e(__('cmsadmin.state')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $row): ?>
            <tr>
              <td>
                <div class="im-property-cell">
                  <span class="im-thumb" aria-hidden="true">
                    <?php if ($row['cover_path']): ?><img src="<?= e(url($row['cover_path'] . '-400.webp')) ?>" alt="" width="48" height="36" loading="lazy"><?php else: ?><span class="mdi mdi-home-outline"></span><?php endif; ?>
                  </span>
                  <div>
                    <a class="im-property-cell__title im-cell-link" href="<?= e($propertyUrl($row)) ?>"><?= e($row['title']) ?></a>
                    <span class="im-property-cell__meta"><?= e($row['reference']) ?> · <?= e($row['category_name']) ?><?= $row['commune_name'] !== null ? ' · ' . e($row['commune_name']) : '' ?></span>
                  </div>
                </div>
              </td>
              <td class="text-end im-num"><?= e($row['price'] !== null ? format_price($row['price']) : __('common.price_on_request')) ?></td>
              <td class="text-end im-num"><?= e(format_number((int) $row['views_count'])) ?></td>
              <td class="text-end im-num"><?= e(format_number((int) $row['leads_count'])) ?></td>
              <td>
                <?= cmsadmin_partial('status-badge', ['status' => $row['status']]) ?>
                <?php if ($row['pending_revision_id'] !== null): ?><span class="im-tag"><?= e(__('properties.tabs.revision')) ?></span><?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
  </div>
</div>
