<?php
/**
 * Badge de statut d'annonce (workflow du cahier des charges §2.1).
 *
 * @var string $status pending|published|rejected|unpublished|archived|expired
 */
$labels = [
    'pending' => 'En attente',
    'published' => 'Publiée',
    'rejected' => 'Rejetée',
    'unpublished' => 'Dépubliée',
    'archived' => 'Archivée',
    'expired' => 'Expirée',
];
$status = array_key_exists($status, $labels) ? $status : 'unpublished';
?>
<span class="im-status im-status--<?= e($status) ?>"><?= e($labels[$status]) ?></span>
