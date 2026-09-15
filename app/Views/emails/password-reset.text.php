<?php
/**
 * Version texte brut (aucun échappement HTML).
 *
 * @var App\Models\User $user
 * @var App\Models\Site $site
 * @var string          $link
 * @var int             $minutes
 * @var string          $ip
 */
?>
<?= __('auth.reset.email_hello', ['name' => $user->firstName]) ?>


<?= __('auth.reset.email_intro', ['site' => $site->name]) ?>


<?= __('auth.reset.email_button') ?> :
<?= $link ?>


<?= __('auth.reset.email_expiry', ['minutes' => $minutes]) ?>

<?= __('auth.reset.email_ignore') ?>


--
<?= __('auth.reset.email_footer', ['site' => $site->name, 'ip' => $ip]) ?>

