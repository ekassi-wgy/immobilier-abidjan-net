<?php
/**
 * @var array $plugins
 * @var array $pageScripts
 */
$plugins ??= [];
$pageScripts ??= [];
?>
<script src="<?= e(cmsadmin_asset('vendors/js/vendor.bundle.base.js')) ?>"></script>
<?php if (in_array('select2', $plugins, true)): ?>
<script src="<?= e(cmsadmin_asset('vendors/select2/select2.min.js')) ?>"></script>
<?php endif; ?>
<?php if (in_array('leaflet', $plugins, true)): ?>
<script src="<?= e(cmsadmin_asset('vendors/leaflet/leaflet.js')) ?>"></script>
<?php endif; ?>
<?php if (in_array('chart', $plugins, true)): ?>
<script src="<?= e(cmsadmin_asset('vendors/chart.js/chart.umd.js')) ?>"></script>
<?php endif; ?>
<script src="<?= e(cmsadmin_asset('js/off-canvas.js')) ?>"></script>
<script src="<?= e(cmsadmin_asset('js/hoverable-collapse.js')) ?>"></script>
<script src="<?= e(cmsadmin_asset('js/template.js')) ?>"></script>
<script src="<?= e(cmsadmin_asset('js/cmsadmin.js')) ?>"></script>
<?php foreach ($pageScripts as $script): ?>
<script src="<?= e(cmsadmin_asset($script)) ?>"></script>
<?php endforeach; ?>
