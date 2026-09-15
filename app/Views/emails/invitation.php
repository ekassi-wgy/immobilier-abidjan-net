<?php
/**
 * @var App\Models\User $user
 * @var App\Models\Site $site
 * @var string          $link
 * @var string          $loginUrl
 * @var int             $hours
 * @var string          $inviter
 * @var string|null     $agencyName
 */
?>
<p style="margin:0 0 16px;"><?= e(__('auth.reset.email_hello', ['name' => $user->firstName])) ?></p>
<p style="margin:0 0 16px;"><?= e($agencyName !== null
    ? __('users.invite.email_intro_agency', ['inviter' => $inviter, 'agency' => $agencyName, 'site' => $site->name])
    : __('users.invite.email_intro_staff', ['inviter' => $inviter, 'site' => $site->name, 'role' => __('auth.roles.' . $user->role)])) ?></p>
<p style="margin:0 0 28px;"><?= e(__('users.invite.email_action')) ?></p>
<p style="margin:0 0 28px;">
  <a href="<?= e($link) ?>" style="display:inline-block;padding:13px 22px;border-radius:8px;background:#2650DB;color:#FFFFFF;font-weight:600;text-decoration:none;"><?= e(__('users.invite.email_button')) ?></a>
</p>
<p style="margin:0 0 16px;color:#5E6B85;font-size:14px;"><?= e(__('users.invite.email_expiry', ['hours' => $hours])) ?></p>
<p style="margin:0 0 24px;color:#5E6B85;font-size:14px;"><?= e(__('users.invite.email_login', ['email' => $user->email])) ?> <a href="<?= e($loginUrl) ?>" style="color:#2650DB;"><?= e($loginUrl) ?></a></p>
<p style="margin:0;padding-top:20px;border-top:1px solid #E5E7EB;color:#5E6B85;font-size:12.5px;">
  <?= e(__('auth.reset.email_link_fallback')) ?><br>
  <a href="<?= e($link) ?>" style="color:#2650DB;word-break:break-all;"><?= e($link) ?></a>
</p>
