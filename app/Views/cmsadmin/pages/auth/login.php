<?php
/**
 * Connexion unique au back-office (Super Admin, Admin Pays, Agences partenaires).
 * Aucune inscription publique : les comptes sont créés manuellement.
 *
 * @var string      $csrfToken
 * @var string|null $errorMessage  Message générique (ne jamais préciser si l'email existe)
 * @var string      $email         Email ressaisi après échec
 * @var bool        $locked        Trop de tentatives
 */
$errorMessage ??= null;
$email ??= '';
$locked ??= false;
?>
<main class="im-auth">
  <section class="im-auth__aside" aria-hidden="true">
    <img class="im-auth__logo" src="<?= e(cmsadmin_asset('images/logo-immobilier-abidjan-net-blanc.png')) ?>" alt="" width="214" height="48">

    <div class="im-auth__statement">
      <p class="im-eyebrow im-eyebrow--light">Espace de gestion</p>
      <p class="im-auth__headline">Chaque annonce publiée est une annonce vérifiée.</p>
    </div>

    <ul class="im-auth__facts">
      <li><span>01</span>Validation de chaque annonce avant mise en ligne</li>
      <li><span>02</span>Agences partenaires sélectionnées</li>
      <li><span>03</span>Côte d’Ivoire · extension panafricaine</li>
    </ul>
  </section>

  <section class="im-auth__main">
    <div class="im-auth__panel">
      <img class="im-auth__logo-mobile" src="<?= e(cmsadmin_asset('images/logo-immobilier-abidjan-net.png')) ?>" alt="immobilier.abidjan.net" width="178" height="40">

      <h1 class="im-auth__title">Connexion</h1>
      <p class="im-auth__subtitle">Accédez à votre espace de gestion des annonces.</p>

      <?php if ($locked): ?>
      <div class="im-flash im-flash--error" role="alert">
        <span class="mdi mdi-lock-clock" aria-hidden="true"></span>
        <p>Trop de tentatives. Réessayez dans quelques minutes.</p>
      </div>
      <?php elseif ($errorMessage !== null): ?>
      <div class="im-flash im-flash--error" role="alert">
        <span class="mdi mdi-alert-circle-outline" aria-hidden="true"></span>
        <p><?= e($errorMessage) ?></p>
      </div>
      <?php endif; ?>

      <form method="post" action="<?= e(cmsadmin_url('connexion')) ?>" class="im-auth__form">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <div class="mb-3">
          <label class="form-label" for="login-email">Adresse email</label>
          <input class="form-control form-control-lg" id="login-email" name="email" type="email" autocomplete="username" required autofocus value="<?= e($email) ?>"<?= $locked ? ' disabled' : '' ?>>
        </div>

        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-baseline">
            <label class="form-label" for="login-password">Mot de passe</label>
            <a class="im-link im-link--small" href="<?= e(cmsadmin_url('mot-de-passe-oublie')) ?>">Mot de passe oublié ?</a>
          </div>
          <div class="im-input-affix">
            <input class="form-control form-control-lg" id="login-password" name="password" type="password" autocomplete="current-password" required<?= $locked ? ' disabled' : '' ?>>
            <button class="im-input-affix__button" type="button" data-toggle-password="#login-password" aria-label="Afficher le mot de passe" aria-pressed="false">
              <span class="mdi mdi-eye-outline" aria-hidden="true"></span>
            </button>
          </div>
        </div>

        <label class="im-check mb-4">
          <input type="checkbox" name="remember" value="1">
          <span class="im-check__box" aria-hidden="true"><span class="mdi mdi-check"></span></span>
          <span>Rester connecté sur cet appareil</span>
        </label>

        <button class="btn btn-primary btn-lg w-100" type="submit"<?= $locked ? ' disabled' : '' ?>>Se connecter</button>
      </form>

      <p class="im-auth__footnote">
        Agence immobilière ? <a class="im-link" href="<?= e(url('devenir-partenaire')) ?>">Devenir partenaire</a>
      </p>
    </div>
  </section>
</main>
