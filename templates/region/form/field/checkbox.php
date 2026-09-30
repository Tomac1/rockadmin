<?php

/**
 * A single yes-or-no box.
 *
 * There is no companion hidden input carrying `0`. An unchecked box sends
 * nothing at all, and that is exactly what `FieldValidator` reads: absent
 * means false, present means true, and nothing else. A hidden partner would
 * make the box always present, so "unchecked" and "not offered" would stop
 * being distinguishable — and a required checkbox, which must be ticked,
 * would pass while unticked.
 *
 * The value is `1` because something has to be sent; the validator coerces
 * presence to true without reading it.
 *
 * `readonly` renders as `disabled`, because a checkbox has no `readonly`
 * that a browser honours — it would still be clickable.
 *
 * @var \RockAdmin\Form\FormFieldView $view
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 */

$attributes = \RockAdmin\Form\FormControl::attributes($view, disableWhenReadonly: true) + [
    'type' => 'checkbox',
    'value' => '1',
    'checked' => $view->value === true,
];
?>
<input class="ra-form-control ra-form-control-checkbox form-check-input"<?= $attrs($attributes) ?>>
