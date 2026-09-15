<?php
/**
 * Mise en page principale du back-office cmsadmin.
 *
 * @var string $title        Titre de la page
 * @var string $content      HTML de la page (déjà rendu)
 * @var array  $user         ['name', 'email', 'role', 'role_label', 'agency_name'?]
 * @var string $activeMenu   Clé de menu active (ex. "properties.pending")
 * @var array  $counters     Compteurs du menu (ex. ['pending_properties' => 12])
 * @var array  $site         ['name', 'country', 'currency', 'url']
 * @var string $csrfToken
 * @var array  $plugins      Plugins optionnels : 'select2', 'chart'
 * @var array  $pageScripts  Scripts propres à la page (chemins relatifs aux assets cmsadmin)
 * @var array  $flash        Messages flash
 */
$plugins ??= [];
$pageScripts ??= [];
?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<?= cmsadmin_partial('head', ['title' => $title, 'plugins' => $plugins]) ?>
<body class="im-cmsadmin sidebar-fixed">
  <script>try{if(localStorage.getItem('cmsadmin.sidebar')==='icon-only'&&matchMedia('(min-width:992px)').matches){document.body.classList.add('sidebar-icon-only')}}catch(e){}</script>
  <a class="im-skip-link" href="#contenu"><?= e(__('common.skip_to_content')) ?></a>

  <div class="container-scroller">
    <?= cmsadmin_partial('navbar', [
        'user' => $user,
        'site' => $site,
        'counters' => $counters ?? [],
        'csrfToken' => $csrfToken ?? '',
        'notifications' => $notifications ?? [],
        'unread' => $unread ?? 0,
        'activeMenu' => $activeMenu ?? '',
    ]) ?>

    <div class="container-fluid page-body-wrapper">
      <?= cmsadmin_partial('sidebar', [
          'user' => $user,
          'activeMenu' => $activeMenu ?? '',
          'counters' => $counters ?? [],
      ]) ?>

      <div class="main-panel">
        <main class="content-wrapper" id="contenu" tabindex="-1">
          <?= cmsadmin_partial('flash', ['flash' => $flash ?? []]) ?>
          <?= $content ?>
        </main>
        <?= cmsadmin_partial('footer', ['site' => $site]) ?>
      </div>
    </div>
  </div>

  <?= cmsadmin_partial('scripts', ['plugins' => $plugins, 'pageScripts' => $pageScripts]) ?>
</body>
</html>
