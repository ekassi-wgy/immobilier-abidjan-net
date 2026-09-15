<?php
/**
 * @var App\Models\User $user
 * @var App\Models\Site $site
 * @var string          $link
 * @var int             $minutes
 * @var string          $ip
 */
?>
<p style="margin:0 0 16px;"><?= e(__('auth.reset.email_hello', ['name' => $user->firstName])) ?></p>
<p style="margin:0 0 28px;"><?= e(__('auth.reset.email_intro', ['site' => $site->name])) ?></p>
<p style="margin:0 0 28px;">
  <a href="<?= e($link) ?>" style="display:inline-block;padding:13px 22px;border-radius:8px;background:#2650DB;color:#FFFFFF;font-weight:600;text-decoration:none;"><?= e(__('auth.reset.email_button')) ?></a>
</p>
<p style="margin:0 0 16px;color:#5E6B85;font-size:14px;"><?= e(__('auth.reset.email_expiry', ['minutes' => $minutes])) ?></p>
<p style="margin:0 0 24px;color:#5E6B85;font-size:14px;"><?= e(__('auth.reset.email_ignore')) ?></p>
<p style="margin:0;padding-top:20px;border-top:1px solid #E5E7EB;color:#5E6B85;font-size:12.5px;">
  <?= e(__('auth.reset.email_link_fallback')) ?><br>
  <a href="<?= e($link) ?>" style="color:#2650DB;word-break:break-all;"><?= e($link) ?></a>
</p>
<p style="margin:16px 0 0;color:#8A94A8;font-size:12px;"><?= e(__('auth.reset.email_footer', ['site' => $site->name, 'ip' => $ip])) ?></p>
