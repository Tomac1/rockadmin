<?php

/**
 * A choice of one, from the options the field declares.
 *
 * A field that is not required opens with an empty option, because a select
 * with no empty entry has no way to say "none" — the first option would be
 * chosen by the browser and saved by a person who never touched the control.
 * A required field does not offer one: there is nothing it could mean.
 *
 * `readonly` renders as `disabled`, because a `<select>` has no `readonly`
 * attribute at all. Nothing is lost by that: `Submission` drops a readonly
 * field on the way in, so its value never came from the wire either way.
 *
 *
 * The caret is markup, not a background image. `.ra-select` sets
 * `appearance: none`, which takes the browser's own arrow away, and the
 * design system puts a 14px chevron back as a sibling inside a positioned
 * wrapper -- so a select without the wrapper is a box with 32px of reserved
 * space on its right and nothing in it, which is how this shipped.
 *
 * `multiple` is the exception and gets no wrapper: a list box has no arrow
 * to draw, and a chevron floating over its last visible row would be a
 * control that looks like it drops down and does not.
 * @var \RockAdmin\Form\FormFieldView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 * @var \Closure(string, mixed=): string $partial
 */

$value = \is_string($view->value) ? $view->value : '';
$attributes = \RockAdmin\Form\FormControl::attributes($view, disableWhenReadonly: true);
?>
<div class="ra-form-control-wrap ra-select-wrap">
    <select class="<?= $e(\RockAdmin\Form\FormControl::classes($view, 'ra-select')) ?>"<?= $attrs($attributes) ?>>
        <?php if (!$view->required) { ?>
            <option value=""><?= $e($view->placeholder === '' ? '—' : $view->placeholder) ?></option>
        <?php } ?>
        <?php foreach ($view->options as $option) { ?>
            <option value="<?= $e($option->value) ?>"<?= $attrs(['selected' => $option->value === $value]) ?>><?= $e($option->label) ?></option>
        <?php } ?>
    </select>
    <?= $partial('ui/select-caret') ?>
</div>
