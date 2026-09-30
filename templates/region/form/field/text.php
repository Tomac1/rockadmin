<?php

/**
 * A single-line text control.
 *
 * `min` and `max` are lengths for a text field, not values — the same
 * reading `FieldValidator` makes of them — so they are written as
 * `minlength` and `maxlength`. `pattern` is the author's own expression
 * without delimiters, exactly as the HTML attribute wants it; the server
 * anchors it through `FieldValidator::compile()` and is the check that
 * actually counts, since the browser's is a hint anybody can switch off.
 *
 * @var \RockAdmin\Form\FormFieldView $view
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 */

$attributes = \RockAdmin\Form\FormControl::attributes($view) + [
    'type' => 'text',
    'value' => \is_string($view->value) ? $view->value : '',
    'placeholder' => $view->placeholder === '' ? null : $view->placeholder,
    'minlength' => $view->min,
    'maxlength' => $view->max,
    'pattern' => $view->pattern,
];
?>
<input class="ra-form-control ra-form-control-text form-control"<?= $attrs($attributes) ?>>
