<?php

/**
 * A choice of several, from the options the field declares.
 *
 * The name carries the brackets PHP reads back as a list —
 * `FormControl::name()` writes them, because the name is also what
 * `Submission` looks the value up by and the two must agree. There is no
 * empty option: choosing nothing is expressed by selecting nothing, and an
 * empty entry in a multiple select is just one more thing to select.
 *
 * `readonly` renders as `disabled`, for the reason `select.php` gives.
 *
 * @var \RockAdmin\Form\FormFieldView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 */

$selected = \is_array($view->value) ? $view->value : [];
$attributes = \RockAdmin\Form\FormControl::attributes($view, disableWhenReadonly: true) + ['multiple' => true];
?>
<select class="ra-form-control ra-form-control-multiselect form-select"<?= $attrs($attributes) ?>>
    <?php foreach ($view->options as $option) { ?>
        <option value="<?= $e($option->value) ?>"<?= $attrs(['selected' => \in_array($option->value, $selected, true)]) ?>><?= $e($option->label) ?></option>
    <?php } ?>
</select>
