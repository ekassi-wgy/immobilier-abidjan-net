<?php

/**
 * Champ de formulaire public (lot 1.11) : libellé, contrôle, aide et message d'erreur.
 * Les valeurs ressaisies après une erreur viennent de $old, jamais de la requête brute.
 *
 * @var string $name
 * @var string $label
 * @var array  $errors      [champ => message]
 * @var array  $old         Valeurs ressaisies
 * @var string $type        text | email | tel | number | password | textarea | select | file
 * @var string $accept      Types acceptés (file)
 * @var bool   $multiple    Plusieurs fichiers (file) — le nom reçoit alors « [] »
 * @var array  $options     [valeur => libellé] pour un select, éventuellement groupé
 * @var bool   $grouped     Les options sont des optgroups [groupe => [valeur => libellé]]
 * @var string $placeholder
 * @var string $help
 * @var string $autocomplete
 * @var bool   $required
 * @var int    $maxlength
 * @var string $empty       Première option d'un select
 */
$type ??= 'text';
$options ??= [];
$grouped ??= false;
$placeholder ??= '';
$help ??= '';
$autocomplete ??= '';
$required ??= false;
$maxlength ??= 190;
$empty ??= '';
$accept ??= '';
$multiple ??= false;
$errors ??= [];
$old ??= [];

$id = 'f-' . preg_replace('/[^a-z0-9]+/i', '-', $name);
$value = (string) ($old[$name] ?? '');
$invalid = isset($errors[$name]);
$class = 'im-control' . ($invalid ? ' is-invalid' : '');
$attributes = ($required ? ' required' : '')
    . ($autocomplete !== '' ? ' autocomplete="' . e($autocomplete) . '"' : '')
    . ($invalid ? ' aria-invalid="true" aria-describedby="' . e($id) . '-error"' : '');
?>
<div class="im-field">
  <label class="im-field__label" for="<?= e($id) ?>"><?= e($label) ?><?= $required ? '' : ' <span class="im-field__optional">' . e(__('front.forms.optional')) . '</span>' ?></label>

  <?php if ($type === 'textarea'): ?>
  <textarea class="<?= e($class) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" rows="5" maxlength="<?= e($maxlength) ?>"<?= $attributes ?> placeholder="<?= e($placeholder) ?>"><?= e($value) ?></textarea>

  <?php elseif ($type === 'select'): ?>
  <select class="<?= e($class) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $attributes ?>>
    <?php if ($empty !== ''): ?><option value=""><?= e($empty) ?></option><?php endif; ?>
    <?php if ($grouped): ?>
      <?php foreach ($options as $group => $items): ?>
      <optgroup label="<?= e($group) ?>">
        <?php foreach ($items as $key => $text): ?>
        <option value="<?= e($key) ?>"<?= $value === (string) $key ? ' selected' : '' ?>><?= e($text) ?></option>
        <?php endforeach; ?>
      </optgroup>
      <?php endforeach; ?>
    <?php else: ?>
      <?php foreach ($options as $key => $text): ?>
      <option value="<?= e($key) ?>"<?= $value === (string) $key ? ' selected' : '' ?>><?= e($text) ?></option>
      <?php endforeach; ?>
    <?php endif; ?>
  </select>

  <?php elseif ($type === 'file'): ?>
  <?php /* Un fichier n'est jamais ré-affiché après une erreur : le navigateur l'interdit, l'aide le rappelle. */ ?>
  <input class="<?= e($class) ?> im-control--file" id="<?= e($id) ?>" name="<?= e($name . ($multiple ? '[]' : '')) ?>" type="file"<?= $accept !== '' ? ' accept="' . e($accept) . '"' : '' ?><?= $multiple ? ' multiple' : '' ?><?= $attributes ?>>

  <?php else: ?>
  <input class="<?= e($class) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>"
         value="<?= e($value) ?>" maxlength="<?= e($maxlength) ?>"<?= $type === 'number' ? ' inputmode="numeric" min="0"' : '' ?><?= $attributes ?>
         <?= $placeholder !== '' ? 'placeholder="' . e($placeholder) . '"' : '' ?>>
  <?php endif; ?>

  <?php if ($invalid): ?>
  <span class="im-field__error" id="<?= e($id) ?>-error"><?= e($errors[$name]) ?></span>
  <?php elseif ($help !== ''): ?>
  <span class="im-field__help"><?= e($help) ?></span>
  <?php endif; ?>
</div>
