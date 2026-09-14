<?php
/** @var array $flash Liste de ['type' => 'success'|'error'|'info', 'message' => string] */
$icons = ['success' => 'mdi-check-circle-outline', 'error' => 'mdi-alert-circle-outline', 'info' => 'mdi-information-outline'];
?>
<?php foreach ($flash as $message): $type = isset($icons[$message['type']]) ? $message['type'] : 'info'; ?>
<div class="im-flash im-flash--<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
  <span class="mdi <?= e($icons[$type]) ?>" aria-hidden="true"></span>
  <p><?= e($message['message']) ?></p>
  <button type="button" class="im-flash__close" aria-label="Fermer"><span class="mdi mdi-close" aria-hidden="true"></span></button>
</div>
<?php endforeach; ?>
