<?php

/**
 * A value that travels with the form and is never shown.
 *
 * `field.php` draws this one on its own: no wrapper, no label, no help and
 * no error, because there is nothing visible to attach any of them to.
 *
 * This is not the same thing as a field declared `hidden` in configuration.
 * `Submission` drops such a field on the way in and its default is reapplied
 * when the row is saved, so `FormRegion` only ever gives one a view at all
 * when its value came from the row — a default put into the page would add
 * nothing except something to tamper with. The class is still
 * `ra-form-control-hidden`, like every other control's, so the type that
 * drew it is readable in the markup.
 *
 * @var \RockAdmin\Form\FormFieldView $view
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 */

$attributes = [
    'id' => \RockAdmin\Form\FormControl::id($view),
    'name' => \RockAdmin\Form\FormControl::name($view),
    'value' => \is_string($view->value) ? $view->value : '',
];
?>
<input class="ra-form-control ra-form-control-hidden" type="hidden"<?= $attrs($attributes) ?>>
