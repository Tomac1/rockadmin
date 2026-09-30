<?php

/**
 * A date and a time, in the browser's own `datetime-local` control.
 *
 * This is the one control whose stored value is not already what the control
 * reads. A `datetime-local` input parses the ISO form with a `T` between the
 * date and the time; a row hands the value back with a space, because that
 * is how both MySQL and PostgreSQL write one. A browser given the space form
 * silently shows an empty control — no error, no warning, just a field the
 * person believes was never set.
 *
 * Swapping the separator is the whole conversion, and it is safe in both
 * directions: `Y-m-d H:i`, `Y-m-d H:i:s`, `Y-m-d\TH:i` and `Y-m-d\TH:i:s`
 * are the four shapes `FieldValidator` accepts, and the two space forms map
 * onto the two `T` forms exactly. So a value that was drawn is a value that
 * validates when it comes back, which is the property that matters here:
 * anything outside that set would break the round trip silently, and the
 * person would be told their own unedited value is not a date.
 *
 * Nothing here reformats the value, on purpose. Parsing it to print it again
 * would mean this template deciding what a datetime is, which is
 * `FieldValidator`'s decision, and a value it would refuse is better shown
 * to the person as they will be asked to correct it.
 *
 * @var \RockAdmin\Form\FormFieldView $view
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 */

$stored = \is_string($view->value) ? $view->value : '';
$attributes = \RockAdmin\Form\FormControl::attributes($view) + [
    'type' => 'datetime-local',
    'value' => str_replace(' ', 'T', $stored),
    'step' => $view->step,
];
?>
<input class="ra-form-control ra-form-control-datetime form-control"<?= $attrs($attributes) ?>>
