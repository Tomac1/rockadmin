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
 * The box is drawn, not native. The design system hides the real input
 * (`opacity: 0; width: 0`) and styles the span beside it, which is how it
 * gets a 16px box with the right border radius in every browser. The input
 * is still the input -- it is what is focused, what is tabbed to, what the
 * label toggles and what the form submits -- so nothing about the control's
 * behaviour depends on this markup; only its appearance does.
 *
 * The tick ships always and is revealed by CSS rather than being inserted
 * when the box is ticked, because there is no JavaScript here to insert it.
 * That is the same trick the design system already uses for the radio's dot,
 * one rule further up the same file.
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
<label class="ra-form-control ra-form-control-checkbox ra-checkbox-label">
    <input class="ra-form-control-checkbox-input ra-checkbox-input"<?= $attrs($attributes) ?>>
    <span class="ra-form-control-checkbox-box ra-checkbox-box" aria-hidden="true">
        <svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="2,6 5,9 10,3"/></svg>
    </span>
</label>
