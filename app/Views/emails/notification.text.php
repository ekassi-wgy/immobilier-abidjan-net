<?php
/**
 * @var App\Models\Site $site
 * @var string          $name
 * @var string          $title
 * @var string|null     $body
 * @var string          $link
 * @var string|null     $button
 * @var string|null     $footer
 */
$button ??= __('notifications.email_button');
$footer ??= __('notifications.email_footer', ['site' => $site->name]);
?>
<?= __('auth.reset.email_hello', ['name' => $name]) ?>


<?= $title ?>

<?= $body !== null ? $body . "\n" : '' ?>

<?= $button ?> : <?= $link ?>


--
<?= $footer ?>

