<?php
/**
 * Barre supérieure du back-office.
 *
 * @var array  $user
 * @var array  $site
 * @var array  $counters
 * @var string $csrfToken
 */
$isStaff = in_array($user['role'], ['super_admin', 'country_admin'], true);
$pending = (int) ($counters['pending_properties'] ?? 0);
$newLeads = (int) ($counters['new_leads'] ?? 0);
$notifications = $isStaff ? $pending : $newLeads;
$initials = mb_strtoupper(implode('', array_map(
    static fn (string $part): string => mb_substr($part, 0, 1),
    array_slice(preg_split('/\s+/', trim($user['name'])) ?: [], 0, 2)
)));
?>
<nav class="navbar default-layout col-lg-12 col-12 p-0 fixed-top d-flex align-items-top flex-row im-topbar">
  <div class="navbar-brand-wrapper d-flex align-items-center justify-content-start">
    <a class="navbar-brand brand-logo" href="<?= e(cmsadmin_url()) ?>">
      <img src="<?= e(cmsadmin_asset('images/logo-immobilier-abidjan-net.png')) ?>" alt="immobilier.abidjan.net" width="192" height="43">
    </a>
    <a class="navbar-brand brand-logo-mini" href="<?= e(cmsadmin_url()) ?>">
      <img src="<?= e(cmsadmin_asset('images/logo-symbole.png')) ?>" alt="immobilier.abidjan.net" width="36" height="35">
    </a>
  </div>

  <div class="navbar-menu-wrapper d-flex align-items-center">
    <button class="navbar-toggler im-icon-btn im-topbar__minimize" type="button" data-bs-toggle="minimize" aria-label="Réduire ou déplier le menu">
      <span class="mdi mdi-dock-left" aria-hidden="true"></span>
    </button>

    <div class="im-topbar__context d-none d-md-flex">
      <span class="im-site-pill" title="Site courant">
        <span class="im-site-pill__dot" aria-hidden="true"></span>
        <?= e($site['country']) ?>
        <span class="im-site-pill__sep" aria-hidden="true">·</span>
        <span class="im-site-pill__meta"><?= e($site['currency']) ?></span>
      </span>
    </div>

    <form class="im-topbar__search d-none d-lg-flex" action="<?= e(cmsadmin_url('annonces')) ?>" method="get" role="search">
      <span class="mdi mdi-magnify" aria-hidden="true"></span>
      <label class="visually-hidden" for="im-global-search">Rechercher une annonce</label>
      <input id="im-global-search" type="search" name="q" placeholder="Référence, titre, quartier…" autocomplete="off">
      <kbd>/</kbd>
    </form>

    <ul class="navbar-nav ms-auto im-topbar__actions">
      <li class="nav-item d-none d-sm-block">
        <a class="im-link-muted" href="<?= e($site['url']) ?>" target="_blank" rel="noopener">
          Voir le site <span class="mdi mdi-arrow-top-right" aria-hidden="true"></span>
        </a>
      </li>

      <li class="nav-item dropdown">
        <a class="im-icon-btn" id="im-notifications" href="#" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
          <span class="mdi mdi-bell-outline" aria-hidden="true"></span>
          <?php if ($notifications > 0): ?><span class="im-dot" aria-hidden="true"></span><?php endif; ?>
        </a>
        <div class="dropdown-menu dropdown-menu-end im-dropdown" aria-labelledby="im-notifications">
          <p class="im-dropdown__title">Notifications</p>
          <?php if ($isStaff && $pending > 0): ?>
          <a class="dropdown-item im-dropdown__item" href="<?= e(cmsadmin_url('annonces?statut=en-attente')) ?>">
            <span class="mdi mdi-timer-sand" aria-hidden="true"></span>
            <span><strong><?= e(format_number($pending)) ?> annonce<?= $pending > 1 ? 's' : '' ?></strong> en attente de validation</span>
          </a>
          <?php endif; ?>
          <?php if ($newLeads > 0): ?>
          <a class="dropdown-item im-dropdown__item" href="<?= e(cmsadmin_url('contacts')) ?>">
            <span class="mdi mdi-email-outline" aria-hidden="true"></span>
            <span><strong><?= e(format_number($newLeads)) ?> nouvelle<?= $newLeads > 1 ? 's' : '' ?> demande<?= $newLeads > 1 ? 's' : '' ?></strong> de contact</span>
          </a>
          <?php endif; ?>
          <?php if ($notifications === 0 && $newLeads === 0): ?>
          <p class="im-dropdown__empty">Rien de nouveau pour le moment.</p>
          <?php endif; ?>
        </div>
      </li>

      <li class="nav-item dropdown">
        <a class="im-user" id="im-user-menu" href="#" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="im-avatar" aria-hidden="true"><?= e($initials) ?></span>
          <span class="im-user__text d-none d-xl-flex">
            <span class="im-user__name"><?= e($user['name']) ?></span>
            <span class="im-user__role"><?= e($user['agency_name'] ?? $user['role_label']) ?></span>
          </span>
          <span class="mdi mdi-chevron-down d-none d-xl-inline" aria-hidden="true"></span>
        </a>
        <div class="dropdown-menu dropdown-menu-end im-dropdown" aria-labelledby="im-user-menu">
          <div class="im-dropdown__head">
            <strong><?= e($user['name']) ?></strong>
            <span><?= e($user['email']) ?></span>
          </div>
          <a class="dropdown-item im-dropdown__item" href="<?= e(cmsadmin_url('mon-compte')) ?>">
            <span class="mdi mdi-account-outline" aria-hidden="true"></span> Mon compte
          </a>
          <form action="<?= e(cmsadmin_url('deconnexion')) ?>" method="post">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit" class="dropdown-item im-dropdown__item">
              <span class="mdi mdi-logout" aria-hidden="true"></span> Se déconnecter
            </button>
          </form>
        </div>
      </li>
    </ul>

    <button class="navbar-toggler navbar-toggler-right d-lg-none im-icon-btn" type="button" data-bs-toggle="offcanvas" aria-label="Ouvrir le menu">
      <span class="mdi mdi-menu" aria-hidden="true"></span>
    </button>
  </div>
</nav>
