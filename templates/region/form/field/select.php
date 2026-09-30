<?php

/**
 * A choice of one, from the options the field declares.
 *
 * A field that is not required opens with an empty option, because a select
 * with no empty entry has no way to say "none" — the first option would be
 * chosen by the browser and saved by a person who never touched the control.
 * A required field does not offer one: there is nothing it could mean.
 *
 * `readonly` renders as `disabled`, because a `<select>` has no `readonly`
 * attribute at all. Nothing is lost by that: `Submission` drops a readonly
 * field on the way in, so its value never came from the wire either way.
 *
 * @var \RockAdmin\Form\FormFieldView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 */

$value = \is_string($view->value) ? $view->value : '';
$attributes = \RockAdmin\Form\FormControl::attributes($view, disableWhenReadonly: true);
?>
<select class="ra-form-control ra-form-control-select form-select"<?= $attrs($attributes) ?>>
    <?php if (!$view->required) { ?>
        <option value=""><?= $e($view->placeholder === '' ? '—' : $view->placeholder) ?></option>
    <?php } ?>
    <?php foreach ($view->options as $option) { ?>
        <option value="<?= $e($option->value) ?>"<?= $attrs(['selected' => $option->value === $value]) ?>><?= $e($option->label) ?></option>
    <?php } ?>
</select>
