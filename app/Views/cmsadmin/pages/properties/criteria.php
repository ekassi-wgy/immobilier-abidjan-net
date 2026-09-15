<?php
/**
 * Critères de la catégorie choisie (fragment rechargé par le formulaire quand la catégorie change).
 *
 * @var array<string,mixed>|null $schema  CatalogRepository::formSchema()
 * @var array<int,mixed>         $values  [attribute_id => valeur]
 * @var array<string,string>     $errors  Clés « attributes.{id} »
 */
$values ??= [];
$errors ??= [];
?>
<?php if ($schema === null): ?>
<p class="im-note mb-0"><span class="mdi mdi-information-outline" aria-hidden="true"></span> <?= e(__('properties.criteria_pick_category')) ?></p>
<?php elseif ($schema['groups'] === []): ?>
<p class="im-note mb-0"><span class="mdi mdi-information-outline" aria-hidden="true"></span> <?= e(__('properties.criteria_none')) ?></p>
<?php else: ?>
  <?php foreach ($schema['groups'] as $group => $attributes): ?>
  <h3 class="im-subtitle"><?= e($group) ?></h3>
  <div class="row g-3">
    <?php foreach ($attributes as $attribute):
        $id = (int) $attribute['id'];
        $name = 'attributes[' . $id . ']';
        $value = $values[$id] ?? null;
        $error = $errors['attributes.' . $id] ?? null;
        $required = (int) $attribute['is_required'] === 1;
        $hint = $attribute['help_text'] ?: null;
        $width = in_array($attribute['input_type'], ['multiselect', 'text'], true) ? 'col-12' : 'col-6 col-md-4';
    ?>
    <div class="<?= e($width) ?>">
      <?php if ($attribute['input_type'] === 'boolean'): ?>
      <fieldset>
        <legend class="form-label"><?= e($attribute['name']) ?><?= $required ? '' : ' <span class="im-optional">' . e(__('cmsadmin.optional')) . '</span>' ?></legend>
        <div class="im-segmented im-segmented--compact">
          <label class="im-segmented__option"><input type="radio" name="<?= e($name) ?>" value=""<?= $value === null || $value === '' ? ' checked' : '' ?>><span><?= e(__('properties.not_specified')) ?></span></label>
          <label class="im-segmented__option"><input type="radio" name="<?= e($name) ?>" value="1"<?= (string) $value === '1' ? ' checked' : '' ?>><span><?= e(__('cmsadmin.yes')) ?></span></label>
          <label class="im-segmented__option"><input type="radio" name="<?= e($name) ?>" value="0"<?= (string) $value === '0' ? ' checked' : '' ?>><span><?= e(__('cmsadmin.no')) ?></span></label>
        </div>
        <?php if ($error !== null): ?><p class="invalid-feedback d-block"><?= e($error) ?></p><?php endif; ?>
      </fieldset>
      <?php elseif ($attribute['input_type'] === 'multiselect'): ?>
      <fieldset>
        <legend class="form-label"><?= e($attribute['name']) ?><?= $required ? '' : ' <span class="im-optional">' . e(__('cmsadmin.optional')) . '</span>' ?></legend>
        <div class="im-checks">
          <?php foreach ($attribute['options'] as $optionId => $label): ?>
          <label class="im-check">
            <input type="checkbox" name="<?= e($name) ?>[]" value="<?= e($optionId) ?>"<?= in_array((int) $optionId, array_map('intval', (array) $value), true) ? ' checked' : '' ?>>
            <span class="im-check__box" aria-hidden="true"><span class="mdi mdi-check"></span></span>
            <span><?= e($label) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
        <?php if ($hint !== null): ?><p class="form-text"><?= e($hint) ?></p><?php endif; ?>
        <?php if ($error !== null): ?><p class="invalid-feedback d-block"><?= e($error) ?></p><?php endif; ?>
      </fieldset>
      <?php elseif ($attribute['input_type'] === 'select'): ?>
      <?= cmsadmin_partial('field', [
          'name' => $name, 'type' => 'select', 'label' => $attribute['name'], 'options' => $attribute['options'],
          'placeholder' => __('properties.not_specified'), 'value' => $value, 'required' => $required, 'optional' => !$required,
          'hint' => $hint, 'error' => $error, 'class' => '',
      ]) ?>
      <?php else: ?>
      <?= cmsadmin_partial('field', [
          'name' => $name,
          'type' => match ($attribute['input_type']) { 'date' => 'date', 'text' => 'text', default => 'number' },
          'label' => $attribute['name'], 'value' => $value, 'required' => $required, 'optional' => !$required,
          'suffix' => $attribute['unit'] ?: null, 'hint' => $hint, 'error' => $error, 'class' => '',
          'attributes' => array_filter([
              'min' => $attribute['min_value'] !== null ? (float) $attribute['min_value'] : ($attribute['input_type'] === 'year' ? 1800 : 0),
              'max' => $attribute['max_value'] !== null ? (float) $attribute['max_value'] : null,
              'step' => $attribute['input_type'] === 'decimal' ? '0.01' : ($attribute['input_type'] === 'text' ? null : '1'),
              'inputmode' => $attribute['input_type'] === 'decimal' ? 'decimal' : null,
              'maxlength' => $attribute['input_type'] === 'text' ? 500 : null,
          ], static fn ($v): bool => $v !== null),
      ]) ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
