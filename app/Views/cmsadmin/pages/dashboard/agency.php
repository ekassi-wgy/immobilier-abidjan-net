<?php
/**
 * Tableau de bord d'une agence partenaire (lot 1.7) : chiffres réels de l'agence connectée.
 *
 * @var array<string,mixed>       $user
 * @var array<string,mixed>       $agency
 * @var array<string,int>         $counts    Annonces par statut + 'revision'
 * @var list<array<string,mixed>> $kpis      ['label','value','hint','tone'?,'link'?]
 * @var array                     $audience  ['labels','views','leads','totals','has_data']
 * @var int                       $audienceDays
 * @var int                       $expiryDays
 * @var list<array<string,mixed>> $top       Biens les plus consultés
 * @var list<array<string,mixed>> $toFix     Annonces rejetées ou modification refusée
 * @var list<array<string,mixed>> $expiring  Annonces qui expirent bientôt
 * @var list<array<string,mixed>> $recent    Dernières annonces
 * @var list<string>              $missing   Éléments manquants du profil public
 * @var bool                      $isOwner   Responsable de l'agence (peut modifier le profil)
 */
$firstName = explode(' ', trim((string) $user['name']))[0];
$maxViews = max([1, ...array_map(static fn (array $row): int => (int) $row['views'], $top)]);
$propertyUrl = static fn (array $row): string => cmsadmin_url('annonces/' . $row['reference']);
$chartData = ['labels' => $audience['labels'], 'views' => $audience['views'], 'leads' => $audience['leads'],
    'legend' => ['views' => __('properties.views'), 'leads' => __('properties.leads')]];
?>
<section class="im-welcome">
  <div>
    <p class="im-eyebrow"><?= e($agency['name']) ?></p>
    <h1 class="im-welcome__title"><?= e(__('dashboard.hello', ['name' => $firstName])) ?></h1>
    <p class="im-welcome__text"><?= e(__('dashboard.intro_agency', ['days' => $audienceDays])) ?></p>
  </div>
  <div class="im-welcome__actions">
    <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url('annonces')) ?>"><?= e(__('properties.title_agency')) ?></a>
    <a class="btn btn-primary" href="<?= e(cmsadmin_url('annonces/nouvelle')) ?>"><span class="mdi mdi-plus" aria-hidden="true"></span> <?= e(__('properties.create')) ?></a>
  </div>
</section>

<?php if ($missing !== []): ?>
<div class="im-flash im-flash--info" role="status">
  <span class="mdi mdi-card-account-details-outline" aria-hidden="true"></span>
  <p>
    <?= e(__('dashboard.profile_incomplete', ['items' => implode(', ', array_map('mb_strtolower', $missing))])) ?>
    <?php if ($isOwner): ?><a href="<?= e(cmsadmin_url('profil-agence')) ?>"><?= e(__('dashboard.complete_profile')) ?></a><?php endif; ?>
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
          <h2 class="im-panel__title"><?= e(__('dashboard.audience')) ?></h2>
          <p class="im-panel__subtitle"><?= e(__('dashboard.audience_subtitle', ['days' => $audienceDays])) ?></p>
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
            'text' => __('dashboard.audience_empty_text'),
        ]) ?>
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
            'text' => __('dashboard.top_empty_text'),
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

  <div class="col-xxl-8">
    <section class="card im-panel h-100">
      <header class="im-panel__head">
        <div>
          <h2 class="im-panel__title"><?= e(__('dashboard.to_do')) ?></h2>
          <p class="im-panel__subtitle"><?= e(__('dashboard.to_do_subtitle', ['days' => $expiryDays])) ?></p>
        </div>
      </header>
      <?php if ($toFix === [] && $expiring === []): ?>
        <?= cmsadmin_partial('empty-state', [
            'icon' => 'mdi-check-all',
            'title' => __('dashboard.to_do_empty_title'),
            'text' => __('dashboard.to_do_empty_text'),
        ]) ?>
      <?php else: ?>
      <ul class="im-feed">
        <?php foreach ($toFix as $row): $reason = $row['status'] === 'rejected' ? $row['rejection_reason'] : $row['revision_reason']; ?>
        <li class="im-feed__item">
          <span class="im-feed__icon mdi mdi-alert-circle-outline" aria-hidden="true"></span>
          <div class="im-feed__body">
            <p class="im-feed__title"><a class="im-cell-link" href="<?= e($propertyUrl($row)) ?>"><?= e($row['title']) ?></a></p>
            <p class="im-feed__meta"><?= e(__($row['status'] === 'rejected' ? 'properties.rejected_reason' : 'properties.revision_rejected_reason')) ?> <?= e((string) ($reason ?? '—')) ?></p>
          </div>
          <a class="btn btn-sm im-btn-ghost" href="<?= e($propertyUrl($row) . '/modifier') ?>"><?= e(__('cmsadmin.edit')) ?></a>
        </li>
        <?php endforeach; ?>
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
          <h2 class="im-panel__title"><?= e(__('dashboard.partner_leads_title')) ?></h2>
          <p class="im-panel__subtitle"><?= e(__('dashboard.partner_leads_subtitle')) ?></p>
        </div>
      </header>
      <ol class="im-steps-list">
        <li><?= e(__('dashboard.partner_leads_step1')) ?></li>
        <li><?= e(__('dashboard.partner_leads_step2')) ?></li>
        <li><?= e(__('dashboard.partner_leads_step3')) ?></li>
      </ol>
    </section>
  </div>

  <div class="col-12">
    <section class="card im-panel">
      <header class="im-panel__head">
        <div>
          <h2 class="im-panel__title"><?= e(__('dashboard.recent_properties')) ?></h2>
          <p class="im-panel__subtitle"><?= e(__('dashboard.recent_properties_subtitle')) ?></p>
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
