<?php
/**
 * Champ de formulaire standard du back-office (libellé, aide, erreur accessibles).
 *
 * @var string                    $name
 * @var string                    $label
 * @var mixed                     $value
 * @var string                    $type       text | email | number | url | tel | textarea | select
 * @var array<string|int, string> $options    Pour select : [valeur => libellé]
 * @var string|null               $placeholder Pour select : option vide (null = pas d'option vide)
 * @var string|null               $error
 * @var string|null               $hint
 * @var bool                      $required
 * @var bool                      $optional   Affiche « facultatif »
 * @var bool                      $disabled
 * @var array<string, string|int> $attributes Attributs HTML supplémentaires (maxlength, step, min, data-…)
 * @var string                    $class      Classes du conteneur
 * @var string|null               $suffix     Unité affichée dans le champ (m², FCFA…)
 */
$type ??= 'text';
$value ??= '';
$options ??= [];
$placeholder ??= null;
$error ??= null;
$hint ??= null;
$required ??= false;
$optional ??= false;
$disabled ??= false;
$attributes ??= [];
$class ??= 'mb-3';
$suffix ??= null;

$id = 'f-' . preg_replace('/[^a-z0-9]+/i', '-', $name);
$describedBy = trim(($hint !== null ? $id . '-hint ' : '') . ($error !== null ? $id . '-error' : ''));
$attrs = '';
foreach ($attributes as $attribute => $attributeValue) {
    $attrs .= ' ' . e($attribute) . '="' . e($attributeValue) . '"';
}
$common = 'id="' . e($id) . '" name="' . e($name) . '"'
    . ($required ? ' required' : '') . ($disabled ? ' disabled' : '')
    . ($describedBy !== '' ? ' aria-describedby="' . e($describedBy) . '"' : '')
    . ($error !== null ? ' aria-invalid="true"' : '') . $attrs;
$invalid = $error !== null ? ' is-invalid' : '';
?>
<div class="<?= e($class) ?>">
  <label class="form-label" for="<?= e($id) ?>"><?= e($label) ?><?php if ($optional): ?> <span class="im-optional"><?= e(__('cmsadmin.optional')) ?></span><?php endif; ?></label>
  <?php if ($type === 'textarea'): ?>
  <textarea class="form-control<?= $invalid ?>" rows="4" <?= $common ?>><?= e($value) ?></textarea>
  <?php elseif ($type === 'select'): ?>
  <select class="form-select<?= $invalid ?>" <?= $common ?>>
    <?php if ($placeholder !== null): ?><option value=""><?= e($placeholder) ?></option><?php endif; ?>
    <?php foreach ($options as $optionValue => $optionLabel): ?>
    <option value="<?= e($optionValue) ?>"<?= (string) $value === (string) $optionValue ? ' selected' : '' ?>><?= e($optionLabel) ?></option>
    <?php endforeach; ?>
  </select>
  <?php elseif ($suffix !== null): ?>
  <div class="im-input-affix">
    <input class="form-control<?= $invalid ?><?= $type === 'number' ? ' im-num' : '' ?>" type="<?= e($type) ?>" value="<?= e($value) ?>" <?= $common ?>>
    <span class="im-input-affix__suffix"><?= e($suffix) ?></span>
  </div>
  <?php else: ?>
  <input class="form-control<?= $invalid ?><?= $type === 'number' ? ' im-num' : '' ?>" type="<?= e($type) ?>" value="<?= e($value) ?>" <?= $common ?>>
  <?php endif; ?>
  <?php if ($hint !== null): ?><p class="form-text" id="<?= e($id) ?>-hint"><?= e($hint) ?></p><?php endif; ?>
  <?php if ($error !== null): ?><p class="invalid-feedback d-block" id="<?= e($id) ?>-error"><?= e($error) ?></p><?php endif; ?>
</div>
