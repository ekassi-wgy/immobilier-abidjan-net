<?php
/** @var array $site */
?>
<footer class="footer im-footer">
  <span>© <?= e(date('Y')) ?> <?= e(site()->name ?? config('app.name')) ?> — Espace de gestion</span>
  <span class="im-footer__meta"><?= e($site['country']) ?> · <?= e($site['currency']) ?></span>
</footer>
