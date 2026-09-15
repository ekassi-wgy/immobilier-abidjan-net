<?php
/**
 * Badge d'état générique : actif / inactif, ou libellé libre avec variante de couleur.
 *
 * @var bool|null   $active
 * @var string|null $label
 * @var string      $variant published (vert) | pending (ambre) | rejected (rouge) | unpublished (gris) | archived
 */
$active ??= null;
$label ??= $active === null ? '' : __($active ? 'cmsadmin.active' : 'cmsadmin.inactive');
$variant ??= $active === false ? 'unpublished' : 'published';
?>
<span class="im-status im-status--<?= e($variant) ?>"><?= e($label) ?></span>
