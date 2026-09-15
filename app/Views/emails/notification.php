<?php
/**
 * Email générique de notification du back-office.
 *
 * @var App\Models\Site $site
 * @var string          $name
 * @var string          $title
 * @var string|null     $body
 * @var string          $link
 */
?>
<p style="margin:0 0 16px;"><?= e(__('auth.reset.email_hello', ['name' => $name])) ?></p>
<p style="margin:0 0 12px;font-size:17px;font-weight:700;color:#143D8A;"><?= e($title) ?></p>
<?php if ($body !== null && $body !== ''): ?>
<p style="margin:0 0 28px;"><?= nl2br(e($body)) ?></p>
<?php endif; ?>
<p style="margin:0 0 28px;">
  <a href="<?= e($link) ?>" style="display:inline-block;padding:13px 22px;border-radius:8px;background:#2650DB;color:#FFFFFF;font-weight:600;text-decoration:none;"><?= e(__('notifications.email_button')) ?></a>
</p>
<p style="margin:0;padding-top:20px;border-top:1px solid #E5E7EB;color:#8A94A8;font-size:12px;"><?= e(__('notifications.email_footer', ['site' => $site->name])) ?></p>
