<?php

/**
 * A choice of one, drawn as a row of radio buttons.
 *
 * This is the one control with no single element for a `<label for>` to
 * point at: every option is its own input, and every one of them carries a
 * label of its own. The group is labelled the other way round instead —
 * `field.php` puts an id on the field's label and this names it in
 * `aria-labelledby`, which is the relationship an assistive technology
 * reads either way.
 *
 * The group itself carries `FormControl::id($view)`, so that the error
 * summary's `#fragment` link, which knows only the field's key, still
 * lands somewhere real. `tabindex="-1"` is what makes that landing focus
 * the group rather than scroll past it.
 *
 * `readonly` renders as `disabled` on each input: a radio has no `readonly`
 * a browser honours.
 *
 * The dot is drawn, not native, the same way the checkbox's box is: the
 * design system hides the real input and styles the span beside it. Which
 * means `ra-radio-input` belongs on the `<input>` and nowhere else -- it was
 * on the group `<div>`, and `opacity: 0; width: 0` applied to the group is
 * every option at once, so the whole control was invisible.
 *
 * @var \RockAdmin\Form\FormFieldView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 */

$value = \is_string($view->value) ? $view->value : '';
$index = 0;
$group = [
    'id' => \RockAdmin\Form\FormControl::id($view),
    'role' => 'radiogroup',
    'tabindex' => '-1',
    'aria-labelledby' => \RockAdmin\Form\FormControl::labelId($view),
    'aria-invalid' => $view->hasError() ? 'true' : null,
    'aria-describedby' => \RockAdmin\Form\FormControl::describedBy($view),
];
?>
<div class="ra-form-control ra-form-control-radio ra-radio-group ra-radio-group--horizontal"<?= $attrs($group) ?>>
    <?php foreach ($view->options as $option) { ?>
        <?php $optionId = \RockAdmin\Form\FormControl::optionId($view, $index); ?>
        <label class="ra-form-radio ra-radio-label" for="<?= $e($optionId) ?>">
            <input
                class="ra-form-radio-input ra-radio-input"
                type="radio"
                <?= $attrs([
                    'id' => $optionId,
                    'name' => \RockAdmin\Form\FormControl::name($view),
                    'value' => $option->value,
                    'checked' => $option->value === $value,
                    'required' => $view->required,
                    'disabled' => $view->readonly,
                ]) ?>
            >
            <span class="ra-form-radio-dot ra-radio-dot" aria-hidden="true"></span>
            <span class="ra-form-radio-label"><?= $e($option->label) ?></span>
        </label>
        <?php ++$index; ?>
    <?php } ?>
</div>
