<?php
/**
 * En-tête de l'espace propriétaire : identité du compte et navigation.
 *
 * @var App\Models\User $user
 * @var string          $current  biens | profil
 * @var string          $csrfToken
 */
$current ??= 'biens';
?>
<header class="im-owner-head">
  <div>
    <p class="im-eyebrow"><?= e(__('front.nav.owner_space')) ?></p>
    <h1 class="im-h2 im-owner-head__title"><?= e(__('owner.dashboard.hello', ['name' => $user->firstName])) ?></h1>
  </div>
  <nav class="im-owner-nav" aria-label="<?= e(__('front.nav.owner_space')) ?>">
    <a class="im-chip<?= $current === 'biens' ? ' is-active' : '' ?>" href="<?= e(url('mon-espace')) ?>"<?= $current === 'biens' ? ' aria-current="page"' : '' ?>><?= icon('house') ?> <?= e(__('owner.nav.properties')) ?></a>
    <a class="im-chip<?= $current === 'profil' ? ' is-active' : '' ?>" href="<?= e(url('mon-espace/profil')) ?>"<?= $current === 'profil' ? ' aria-current="page"' : '' ?>><?= icon('user') ?> <?= e(__('owner.nav.profile')) ?></a>
    <form method="post" action="<?= e(url('mon-espace/deconnexion')) ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
      <button class="im-chip" type="submit"><?= e(__('owner.nav.logout')) ?></button>
    </form>
  </nav>
</header>
