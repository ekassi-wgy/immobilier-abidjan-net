<?php
/**
 * Messages flash du site public (après une redirection 303).
 *
 * @var list<array{type: string, message: string}> $flash
 */
$flash ??= [];
?>
<?php foreach ($flash as $message): $type = in_array($message['type'], ['success', 'error'], true) ? $message['type'] : 'info'; ?>
<p class="im-alert im-alert--<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
  <?= icon($type === 'success' ? 'check' : 'info') ?> <?= e($message['message']) ?>
</p>
<?php endforeach; ?>
