<?php

/**
 * A password control, which deliberately never redraws what was typed.
 *
 * Every other control in this directory comes back holding the submitted
 * value, because a refused form that lost what the person typed is a form
 * they have to fill in twice. A password is the exception: writing it back
 * into the page puts a secret into the response body, the browser's view
 * source, and any proxy or error reporter that keeps one. The person
 * retypes it, which is what every browser's own autofill assumes as well.
 *
 * Length bounds are `minlength` and `maxlength` for the same reason as
 * `text.php`: `FieldValidator` reads a password field's `min` and `max` as
 * lengths.
 *
 * @var \RockAdmin\Form\FormFieldView $view
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 */

$attributes = \RockAdmin\Form\FormControl::attributes($view) + [
    'type' => 'password',
    'value' => '',
    'placeholder' => $view->placeholder === '' ? null : $view->placeholder,
    'minlength' => $view->min,
    'maxlength' => $view->max,
    'pattern' => $view->pattern,
    'autocomplete' => 'new-password',
];
?>
<input class="ra-form-control ra-form-control-password form-control"<?= $attrs($attributes) ?>>
