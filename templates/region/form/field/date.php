<?php

/**
 * A date, in the browser's own date control.
 *
 * A `date` input reads and writes `YYYY-MM-DD` and nothing else, whatever
 * the person's locale shows them, which is the same single format
 * `FieldValidator` accepts for this type. So the stored value goes in
 * untouched: there is nothing to convert, and converting would be the place
 * a round trip could break.
 *
 * @var \RockAdmin\Form\FormFieldView $view
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 */

$attributes = \RockAdmin\Form\FormControl::attributes($view) + [
    'type' => 'date',
    'value' => \is_string($view->value) ? $view->value : '',
];
?>
<input class="ra-form-control ra-form-control-date form-control"<?= $attrs($attributes) ?>>
