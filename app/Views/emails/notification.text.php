<?php
/**
 * @var App\Models\Site $site
 * @var string          $name
 * @var string          $title
 * @var string|null     $body
 * @var string          $link
 */
?>
<?= __('auth.reset.email_hello', ['name' => $name]) ?>


<?= $title ?>

<?= $body !== null ? $body . "\n" : '' ?>

<?= __('notifications.email_button') ?> : <?= $link ?>


--
<?= __('notifications.email_footer', ['site' => $site->name]) ?>

