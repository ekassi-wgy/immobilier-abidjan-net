<?php
/**
 * Champ mot de passe avec bouton « afficher » et message d'erreur accessible.
 *
 * @var string      $id
 * @var string      $name
 * @var string      $label
 * @var string      $autocomplete  current-password | new-password
 * @var string|null $error
 * @var string|null $hint
 * @var bool        $autofocus
 * @var bool        $disabled
 */
$error ??= null;
$hint ??= null;
$autofocus ??= false;
$disabled ??= false;
$describedBy = trim(($hint !== null ? $id . '-hint ' : '') . ($error !== null ? $id . '-error' : ''));
?>
<div class="mb-3">
  <label class="form-label" for="<?= e($id) ?>"><?= e($label) ?></label>
  <div class="im-input-affix">
    <input class="form-control form-control-lg<?= $error !== null ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" type="password"
      autocomplete="<?= e($autocomplete) ?>" required maxlength="128"<?= $autofocus ? ' autofocus' : '' ?><?= $disabled ? ' disabled' : '' ?>
      <?= $describedBy !== '' ? 'aria-describedby="' . e($describedBy) . '"' : '' ?><?= $error !== null ? ' aria-invalid="true"' : '' ?>>
    <button class="im-input-affix__button" type="button" data-toggle-password="#<?= e($id) ?>" aria-label="<?= e(__('auth.show_password')) ?>" aria-pressed="false">
      <span class="mdi mdi-eye-outline" aria-hidden="true"></span>
    </button>
  </div>
  <?php if ($hint !== null): ?><p class="form-text" id="<?= e($id) ?>-hint"><?= e($hint) ?></p><?php endif; ?>
  <?php if ($error !== null): ?><p class="invalid-feedback d-block" id="<?= e($id) ?>-error"><?= e($error) ?></p><?php endif; ?>
</div>
