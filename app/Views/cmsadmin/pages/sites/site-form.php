<?php
/**
 * Création / modification d'un site et de ses domaines.
 *
 * @var array<string,mixed>|null   $site
 * @var array<string,mixed>        $values
 * @var array<string,string>       $errors
 * @var array<int,string>          $countries
 * @var array<string,string>       $locales
 * @var array<string,string>       $statuses
 * @var array<string,string>       $environments
 * @var list<array<string,mixed>>  $domains
 * @var array<string,mixed>        $domainErrors
 * @var bool                       $isCurrent
 * @var string                     $currentHost
 */
$isEdit = $site !== null;
$value = static fn (string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
$supported = (array) $value('supported_locales', []);
$domainValues = (array) ($domainErrors['_values'] ?? []);
?>
<?= cmsadmin_partial('page-header', [
    'title' => $isEdit ? __('sites.site.edit', ['name' => $site['name']]) : __('sites.site.create'),
    'subtitle' => $isEdit ? $site['country_name'] . ' · ' . $site['code'] : __('sites.site.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('sites.title'), 'url' => 'pays-sites'], ['label' => $isEdit ? $site['name'] : __('sites.site.create')]],
]) ?>

<?php if ($errors !== []): ?>
<div class="im-flash im-flash--error" role="alert">
  <span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span>
  <p><?= e(__('cmsadmin.form_errors', ['count' => count($errors)])) ?></p>
</div>
<?php endif; ?>

<div class="im-form__layout">
  <div class="im-form__main">
    <form class="im-form" id="site-form" method="post" action="<?= e(cmsadmin_url($isEdit ? 'pays-sites/sites/' . $site['id'] : 'pays-sites/sites')) ?>" novalidate>
      <?= csrf_field() ?>
      <section class="card im-panel im-form-section">
        <header class="im-form-section__head"><span class="im-form-section__index">01</span><h2 class="im-panel__title"><?= e(__('cmsadmin.general')) ?></h2></header>
        <div class="row g-3">
          <div class="col-md-6">
            <?= cmsadmin_partial('field', ['name' => 'name', 'label' => __('cmsadmin.name'), 'value' => $value('name'), 'required' => true, 'hint' => __('sites.site.name_hint'), 'error' => $errors['name'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 100]]) ?>
          </div>
          <div class="col-md-3">
            <?= cmsadmin_partial('field', ['name' => $isEdit ? 'code_display' : 'code', 'label' => __('cmsadmin.code'), 'value' => $value('code'), 'required' => !$isEdit, 'disabled' => $isEdit, 'hint' => $isEdit ? __('cmsadmin.code_locked') : null, 'error' => $errors['code'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 30, 'placeholder' => 'sn', 'spellcheck' => 'false']]) ?>
          </div>
          <div class="col-md-3">
            <?= cmsadmin_partial('field', ['name' => $isEdit ? 'country_display' : 'country_id', 'type' => 'select', 'label' => __('sites.country.singular'), 'options' => $countries, 'placeholder' => __('cmsadmin.choose'), 'value' => $value('country_id'), 'required' => !$isEdit, 'disabled' => $isEdit, 'error' => $errors['country_id'] ?? null, 'class' => '']) ?>
          </div>
          <div class="col-md-4">
            <?= cmsadmin_partial('field', ['name' => 'default_locale', 'type' => 'select', 'label' => __('sites.locale'), 'options' => $locales, 'value' => $value('default_locale', 'fr'), 'required' => true, 'error' => $errors['default_locale'] ?? null, 'class' => '']) ?>
          </div>
          <div class="col-md-5">
            <fieldset>
              <legend class="form-label"><?= e(__('sites.supported_locales')) ?></legend>
              <div class="im-checks im-checks--inline">
                <?php foreach ($locales as $code => $label): ?>
                <label class="im-check">
                  <input type="checkbox" name="supported_locales[]" value="<?= e($code) ?>"<?= in_array($code, $supported, true) ? ' checked' : '' ?>>
                  <span class="im-check__box" aria-hidden="true"><span class="mdi mdi-check"></span></span>
                  <span><?= e($label) ?></span>
                </label>
                <?php endforeach; ?>
              </div>
            </fieldset>
          </div>
          <div class="col-md-3">
            <?= cmsadmin_partial('field', ['name' => 'theme', 'label' => __('sites.theme'), 'value' => $value('theme', 'default'), 'required' => true, 'error' => $errors['theme'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 50, 'spellcheck' => 'false']]) ?>
          </div>
        </div>
      </section>

      <section class="card im-panel im-form-section">
        <header class="im-form-section__head"><span class="im-form-section__index">02</span><h2 class="im-panel__title"><?= e(__('sites.contacts')) ?></h2></header>
        <p class="im-panel__subtitle"><?= e(__('sites.contacts_hint')) ?></p>
        <div class="row g-3">
          <div class="col-md-4">
            <?= cmsadmin_partial('field', ['name' => 'contact_email', 'type' => 'email', 'label' => __('auth.email'), 'value' => $value('contact_email'), 'optional' => true, 'error' => $errors['contact_email'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 190]]) ?>
          </div>
          <div class="col-md-4">
            <?= cmsadmin_partial('field', ['name' => 'contact_phone', 'type' => 'tel', 'label' => __('sites.phone'), 'value' => $value('contact_phone'), 'optional' => true, 'error' => $errors['contact_phone'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 30, 'placeholder' => '+225 27 20 00 00 00']]) ?>
          </div>
          <div class="col-md-4">
            <?= cmsadmin_partial('field', ['name' => 'contact_whatsapp', 'type' => 'tel', 'label' => 'WhatsApp', 'value' => $value('contact_whatsapp'), 'optional' => true, 'error' => $errors['contact_whatsapp'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 30]]) ?>
          </div>
          <div class="col-12">
            <?= cmsadmin_partial('field', ['name' => 'address', 'label' => __('sites.address'), 'value' => $value('address'), 'optional' => true, 'error' => $errors['address'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 255]]) ?>
          </div>
          <div class="col-md-4">
            <?= cmsadmin_partial('field', ['name' => 'latitude', 'label' => __('sites.latitude'), 'value' => $value('latitude'), 'optional' => true, 'error' => $errors['latitude'] ?? null, 'class' => '', 'attributes' => ['inputmode' => 'decimal', 'placeholder' => '5.3322343']]) ?>
          </div>
          <div class="col-md-4">
            <?= cmsadmin_partial('field', ['name' => 'longitude', 'label' => __('sites.longitude'), 'value' => $value('longitude'), 'optional' => true, 'error' => $errors['longitude'] ?? null, 'class' => '', 'attributes' => ['inputmode' => 'decimal', 'placeholder' => '-4.0002405']]) ?>
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <p class="form-text mb-2"><?= e(__('sites.coordinates_hint')) ?></p>
          </div>
        </div>
      </section>

      <section class="card im-panel im-form-section">
        <header class="im-form-section__head"><span class="im-form-section__index">03</span><h2 class="im-panel__title"><?= e(__('sites.social.title')) ?></h2></header>
        <p class="im-panel__subtitle"><?= e(__('sites.social.hint')) ?></p>
        <div class="row g-3">
          <?php foreach (array_keys(App\Models\Site::SOCIAL_NETWORKS) as $network): ?>
          <div class="col-md-6">
            <?= cmsadmin_partial('field', ['name' => 'social_' . $network, 'type' => 'url', 'label' => __('sites.social.networks.' . $network), 'value' => $value('social_' . $network), 'optional' => true, 'error' => $errors['social_' . $network] ?? null, 'class' => '', 'attributes' => ['maxlength' => 255, 'placeholder' => __('sites.social.placeholders.' . $network)]]) ?>
          </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="card im-panel im-form-section">
        <header class="im-form-section__head"><span class="im-form-section__index">04</span><h2 class="im-panel__title"><?= e(__('sites.analytics.title')) ?></h2></header>
        <p class="im-panel__subtitle"><?= e(__('sites.analytics.hint')) ?></p>
        <div class="row g-3">
          <div class="col-md-6">
            <?= cmsadmin_partial('field', ['name' => 'analytics_id', 'label' => __('sites.analytics.label'), 'value' => $value('analytics_id'), 'optional' => true, 'error' => $errors['analytics_id'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 40, 'placeholder' => 'G-XXXXXXXXXX', 'autocomplete' => 'off', 'spellcheck' => 'false']]) ?>
          </div>
          <?php if (str_starts_with(strtoupper((string) $value('analytics_id')), 'UA-')): ?>
          <div class="col-md-6 d-flex align-items-end">
            <p class="im-note mb-2"><span class="mdi mdi-alert-outline" aria-hidden="true"></span> <?= e(__('sites.analytics.ua_warning')) ?></p>
          </div>
          <?php endif; ?>
        </div>
      </section>
    </form>

    <?php if ($isEdit): ?>
    <section class="card im-panel im-form-section" id="domaines">
      <header class="im-form-section__head"><span class="im-form-section__index">05</span><h2 class="im-panel__title"><?= e(__('sites.domains')) ?></h2></header>
      <p class="im-panel__subtitle"><?= e(__('sites.domains_hint')) ?></p>

      <?php if ($domains === []): ?>
      <p class="im-note"><span class="mdi mdi-information-outline" aria-hidden="true"></span> <?= e(__('sites.no_domains')) ?></p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table im-table">
          <thead>
            <tr>
              <th scope="col"><?= e(__('sites.host')) ?></th>
              <th scope="col"><?= e(__('sites.environment')) ?></th>
              <th scope="col"><span class="visually-hidden"><?= e(__('cmsadmin.actions')) ?></span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($domains as $domain): $current = $domain['host'] === $currentHost; ?>
            <tr>
              <td>
                <span class="im-cell-main"><?= e($domain['host']) ?></span>
                <span class="im-cell-sub">
                  <?php if ((int) $domain['is_primary'] === 1): ?><span class="im-tag"><?= e(__('sites.primary')) ?></span><?php endif; ?>
                  <?php if ($current): ?><span class="im-tag im-tag--muted"><?= e(__('sites.in_use_now')) ?></span><?php endif; ?>
                </span>
              </td>
              <td><?= e($environments[$domain['environment']] ?? $domain['environment']) ?></td>
              <td class="text-end">
                <div class="im-inline-actions">
                  <?php if ((int) $domain['is_primary'] === 0): ?>
                  <form method="post" action="<?= e(cmsadmin_url('pays-sites/sites/' . $site['id'] . '/domaines/' . $domain['id'] . '/principal')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm im-btn-ghost" type="submit"><?= e(__('sites.make_primary')) ?></button>
                  </form>
                  <?php endif; ?>
                  <?php if (!$current): ?>
                  <form method="post" action="<?= e(cmsadmin_url('pays-sites/sites/' . $site['id'] . '/domaines/' . $domain['id'] . '/supprimer')) ?>">
                    <?= csrf_field() ?>
                    <button class="im-icon-btn im-icon-btn--sm" type="submit" aria-label="<?= e(__('sites.delete_domain', ['host' => $domain['host']])) ?>" data-confirm="<?= e(__('sites.confirm_delete_domain', ['host' => $domain['host']])) ?>"><span class="mdi mdi-trash-can-outline" aria-hidden="true"></span></button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <form class="im-domain-add" method="post" action="<?= e(cmsadmin_url('pays-sites/sites/' . $site['id'] . '/domaines')) ?>" novalidate>
        <?= csrf_field() ?>
        <h3 class="im-domain-add__title"><?= e(__('sites.add_domain')) ?></h3>
        <div class="row g-3 align-items-start">
          <div class="col-md-6">
            <?= cmsadmin_partial('field', ['name' => 'host', 'label' => __('sites.host'), 'value' => $domainValues['host'] ?? '', 'required' => true, 'error' => $domainErrors['host'] ?? null, 'class' => '', 'attributes' => ['maxlength' => 190, 'placeholder' => 'immobilier.dakar.net', 'spellcheck' => 'false', 'autocapitalize' => 'off']]) ?>
          </div>
          <div class="col-md-3">
            <?= cmsadmin_partial('field', ['name' => 'environment', 'type' => 'select', 'label' => __('sites.environment'), 'options' => $environments, 'value' => $domainValues['environment'] ?? 'production', 'error' => $domainErrors['environment'] ?? null, 'class' => '']) ?>
          </div>
          <div class="col-md-3 im-domain-add__submit">
            <?= cmsadmin_partial('switch', ['name' => 'is_primary', 'label' => __('sites.primary'), 'checked' => !empty($domainValues['is_primary']), 'class' => 'mb-2']) ?>
            <button class="btn btn-primary btn-sm" type="submit"><span class="mdi mdi-plus" aria-hidden="true"></span> <?= e(__('sites.add')) ?></button>
          </div>
        </div>
      </form>
    </section>
    <?php endif; ?>
  </div>

  <aside class="im-form__aside">
    <div class="im-sticky">
      <section class="card im-panel">
        <h2 class="im-panel__title"><?= e(__('cmsadmin.publication')) ?></h2>
        <div class="mt-3">
          <?= cmsadmin_partial('field', ['name' => 'status', 'type' => 'select', 'label' => __('cmsadmin.state'), 'options' => $statuses, 'value' => $value('status', 'disabled'), 'required' => true, 'hint' => $isCurrent ? __('sites.site.current_hint') : __('sites.site.status_hint'), 'error' => $errors['status'] ?? null, 'attributes' => ['form' => 'site-form']]) ?>
        </div>
        <div class="d-grid gap-2">
          <button class="btn btn-primary" type="submit" form="site-form"><?= e(__($isEdit ? 'cmsadmin.save' : 'cmsadmin.create')) ?></button>
          <a class="btn im-btn-ghost" href="<?= e(cmsadmin_url('pays-sites')) ?>"><?= e(__('cmsadmin.cancel')) ?></a>
        </div>
      </section>
    </div>
  </aside>
</div>
