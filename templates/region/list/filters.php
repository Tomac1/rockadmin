<?php

/**
 * One control per filter, by its own type. Field names follow the same
 * bracket convention `GridState::toQuery()` reads back: `<region>[f][<col>]`
 * for a scalar, `<region>[f][<col>][]` for a multiselect, and
 * `<region>[f][<col>][from]`/`[to]` for a range or a date span — so this
 * ordinary GET form and a sort or pager link produce query strings the same
 * parser understands.
 *
 * `data-ra-filter` carries the filter's own key on each control's wrapper —
 * identity metadata a project's own CSS or script can hook a specific
 * filter by, the same role `data-ra-region` plays on the fragment root,
 * never a `RockAdmin.behavior()` name. `grid-filters` itself carried no
 * `data-ra-behavior` here: nothing in `core.js` ever attached to one, and a
 * "behaviour" attribute nothing binds is worse than none at all, since a
 * project has no way to tell "unbound in this build" from "the binding
 * failed".
 *
 * Every single-choice select sits inside `.ra-select-wrap` and is followed
 * by `ui/select-caret`. `.ra-select` sets `appearance: none`, so the
 * browser's own arrow is gone and the design system's chevron is markup; a
 * select without the wrapper is a box with 32px of reserved space on its
 * right and nothing in it. The multiselect is the exception and keeps no
 * wrapper: a list box has no arrow to draw.
 *
 * @var \RockAdmin\Grid\ListView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(string, mixed=): string $partial
 */
?>
<div class="ra-grid-filters">
    <?php foreach ($view->filters as $filter) { ?>
        <?php
            $name = $view->key . '[f][' . $filter->key . ']';
            $id = 'ra-grid-filter-' . $view->key . '-' . $filter->key;
        ?>
        <div class="ra-grid-filter ra-grid-filter-<?= $e($filter->key) ?>" data-ra-filter="<?= $e($filter->key) ?>">
            <label class="ra-grid-filter-label ra-field__label" for="<?= $e($id) ?>"><?= $e($filter->label) ?></label>

            <?php if ($filter->type === 'select') { ?>
                <div class="ra-grid-filter-wrap ra-select-wrap">
                    <select class="ra-grid-filter-input ra-select ra-select--sm" id="<?= $e($id) ?>" name="<?= $e($name) ?>">
                        <option value=""><?= $e($filter->placeholder ?? 'Any') ?></option>
                        <?php foreach ($filter->options as $option) { ?>
                            <option value="<?= $e($option->value) ?>"<?= $e($filter->value === $option->value ? ' selected' : '') ?>>
                                <?= $e($option->label) ?>
                            </option>
                        <?php } ?>
                    </select>
                    <?= $partial('ui/select-caret') ?>
                </div>
            <?php } elseif ($filter->type === 'multiselect') { ?>
                <?php $selected = \is_array($filter->value) && array_is_list($filter->value) ? $filter->value : []; ?>
                <select class="ra-grid-filter-input ra-select ra-select--sm" id="<?= $e($id) ?>" name="<?= $e($name) ?>[]" multiple>
                    <?php foreach ($filter->options as $option) { ?>
                        <option value="<?= $e($option->value) ?>"<?= $e(\in_array($option->value, $selected, true) ? ' selected' : '') ?>>
                            <?= $e($option->label) ?>
                        </option>
                    <?php } ?>
                </select>
            <?php } elseif ($filter->type === 'range' || $filter->type === 'date') { ?>
                <?php
                    $range = \is_array($filter->value) ? $filter->value : [];
                    $from = \is_string($range['from'] ?? null) ? $range['from'] : '';
                    $to = \is_string($range['to'] ?? null) ? $range['to'] : '';
                    $inputType = $filter->type === 'date' ? 'date' : 'text';
                ?>
                <div class="ra-grid-filter-range">
                    <input
                        class="ra-grid-filter-input ra-grid-filter-from ra-input ra-input--sm"
                        type="<?= $e($inputType) ?>"
                        id="<?= $e($id) ?>"
                        name="<?= $e($name) ?>[from]"
                        value="<?= $e($from) ?>"
                        placeholder="From"
                    >
                    <input
                        class="ra-grid-filter-input ra-grid-filter-to ra-input ra-input--sm"
                        type="<?= $e($inputType) ?>"
                        name="<?= $e($name) ?>[to]"
                        value="<?= $e($to) ?>"
                        placeholder="To"
                        aria-label="<?= $e($filter->label . ' to') ?>"
                    >
                </div>
            <?php } elseif ($filter->type === 'boolean') { ?>
                <div class="ra-grid-filter-wrap ra-select-wrap">
                    <select class="ra-grid-filter-input ra-select ra-select--sm" id="<?= $e($id) ?>" name="<?= $e($name) ?>">
                        <option value="">Any</option>
                        <option value="1"<?= $e($filter->value === '1' ? ' selected' : '') ?>>Yes</option>
                        <option value="0"<?= $e($filter->value === '0' ? ' selected' : '') ?>>No</option>
                    </select>
                    <?= $partial('ui/select-caret') ?>
                </div>
            <?php } else { ?>
                <input
                    class="ra-grid-filter-input ra-input ra-input--sm"
                    type="text"
                    id="<?= $e($id) ?>"
                    name="<?= $e($name) ?>"
                    value="<?= $e(\is_string($filter->value) ? $filter->value : '') ?>"
                    <?php if ($filter->placeholder !== null) { ?>placeholder="<?= $e($filter->placeholder) ?>"<?php } ?>
                >
            <?php } ?>
        </div>
    <?php } ?>
</div>
