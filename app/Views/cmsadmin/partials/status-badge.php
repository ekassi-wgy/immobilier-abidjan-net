<?php
/**
 * Badge de statut d'annonce (workflow du cahier des charges §2.1).
 *
 * @var string $status pending|published|rejected|unpublished|archived|expired
 */
$statuses = ['pending', 'published', 'rejected', 'unpublished', 'archived', 'expired'];
$status = in_array($status, $statuses, true) ? $status : 'unpublished';
?>
<span class="im-status im-status--<?= e($status) ?>"><?= e(__('properties.status.' . $status)) ?></span>
