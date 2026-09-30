<?php

/**
 * A multi-line text control.
 *
 * The value is element content rather than an attribute, which is the one
 * thing that makes this control different from `text.php` and the one place
 * a value could close the element it sits in. `$e()` escapes `<` and `>`, so
 * a value of `</textarea><script>…` is text inside the box, and
 * `FormTemplatesTest::testATextareaDoesNotLetAValueCloseItsOwnTag()` holds
 * that.
 *
 * There is no newline between the opening tag and the value: HTML drops a
 * single leading newline inside a `<textarea>`, so a value that genuinely
 * starts with one would lose it on every round trip through the form.
 *
 * @var \RockAdmin\Form\FormFieldView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 */

$attributes = \RockAdmin\Form\FormControl::attributes($view) + [
    'rows' => $view->rows,
    'placeholder' => $view->placeholder === '' ? null : $view->placeholder,
    'minlength' => $view->min,
    'maxlength' => $view->max,
];
?>
<textarea class="ra-form-control ra-form-control-textarea form-control"<?= $attrs($attributes) ?>><?= $e(\is_string($view->value) ? $view->value : '') ?></textarea>
