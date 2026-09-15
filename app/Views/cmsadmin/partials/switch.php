<?php
/**
 * Interrupteur (case à cocher). Une valeur « 0 » cachée garantit l'envoi du champ décoché.
 *
 * @var string      $name
 * @var string      $label
 * @var bool        $checked
 * @var string|null $hint
 * @var bool        $disabled
 * @var string      $class
 */
$checked ??= false;
$hint ??= null;
$disabled ??= false;
$class ??= 'mb-3';
?>
<div class="<?= e($class) ?>">
  <input type="hidden" name="<?= e($name) ?>" value="0">
  <label class="im-switch">
    <input type="checkbox" name="<?= e($name) ?>" value="1"<?= $checked ? ' checked' : '' ?><?= $disabled ? ' disabled' : '' ?>>
    <span class="im-switch__track" aria-hidden="true"></span>
    <span><?= e($label) ?></span>
  </label>
  <?php if ($hint !== null): ?><p class="form-text"><?= e($hint) ?></p><?php endif; ?>
</div>
