<?php
/**
 * Version texte brut (aucun échappement HTML).
 *
 * @var App\Models\User $user
 * @var App\Models\Site $site
 * @var string          $link
 * @var string          $loginUrl
 * @var int             $hours
 * @var string          $inviter
 * @var string|null     $agencyName
 */
?>
<?= __('auth.reset.email_hello', ['name' => $user->firstName]) ?>


<?= $agencyName !== null
    ? __('users.invite.email_intro_agency', ['inviter' => $inviter, 'agency' => $agencyName, 'site' => $site->name])
    : __('users.invite.email_intro_staff', ['inviter' => $inviter, 'site' => $site->name, 'role' => __('auth.roles.' . $user->role)]) ?>


<?= __('users.invite.email_action') ?>

<?= $link ?>


<?= __('users.invite.email_expiry', ['hours' => $hours]) ?>

<?= __('users.invite.email_login', ['email' => $user->email]) ?> <?= $loginUrl ?>

