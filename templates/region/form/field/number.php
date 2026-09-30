<?php

/**
 * A numeric control.
 *
 * `min` and `max` are values here, not lengths, which is the one place the
 * two keys mean different things by type — `FieldValidator` reads them the
 * same way, and refuses a number outside them server-side.
 *
 * `step` is a rendering hint and nothing more. It tells the browser what the
 * spinner should move by and what it should accept, and the server does not
 * check it: a value of `0.005` against `step="0.01"` is saved. Do not read
 * this attribute as a promise about what reaches the database — the column's
 * own type is that promise.
 *
 * @var \RockAdmin\Form\FormFieldView $view
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 */

$attributes = \RockAdmin\Form\FormControl::attributes($view) + [
    'type' => 'number',
    'value' => \is_string($view->value) ? $view->value : '',
    'placeholder' => $view->placeholder === '' ? null : $view->placeholder,
    'min' => $view->min,
    'max' => $view->max,
    'step' => $view->step,
];
?>
<input class="ra-form-control ra-form-control-number form-control"<?= $attrs($attributes) ?>>
