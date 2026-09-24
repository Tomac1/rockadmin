<?php

/**
 * One control per filter, by its own type. Field names follow the same
 * bracket convention `GridState::toQuery()` reads back: `<region>[f][<col>]`
 * for a scalar, `<region>[f][<col>][]` for a multiselect, and
 * `<region>[f][<col>][from]`/`[to]` for a range or a date span — so this
 * ordinary GET form and a sort or pager link produce query strings the same
 * parser understands.
 *
 * @var \RockAdmin\Grid\ListView $view
 * @var \Closure(mixed): string $e
 */
?>
<div class="ra-grid-filters" data-ra-behavior="grid-filters">
    <?php foreach ($view->filters as $filter) { ?>
        <?php
            $name = $view->key . '[f][' . $filter->key . ']';
            $id = 'ra-grid-filter-' . $view->key . '-' . $filter->key;
        ?>
        <div class="ra-grid-filter ra-grid-filter-<?= $e($filter->key) ?>" data-ra-filter="<?= $e($filter->key) ?>">
            <label class="ra-grid-filter-label form-label" for="<?= $e($id) ?>"><?= $e($filter->label) ?></label>

            <?php if ($filter->type === 'select') { ?>
                <select class="ra-grid-filter-input form-select" id="<?= $e($id) ?>" name="<?= $e($name) ?>">
                    <option value=""><?= $e($filter->placeholder ?? 'Any') ?></option>
                    <?php foreach ($filter->options as $option) { ?>
                        <option value="<?= $e($option->value) ?>"<?= $e($filter->value === $option->value ? ' selected' : '') ?>>
                            <?= $e($option->label) ?>
                        </option>
                    <?php } ?>
                </select>
            <?php } elseif ($filter->type === 'multiselect') { ?>
                <?php $selected = \is_array($filter->value) && array_is_list($filter->value) ? $filter->value : []; ?>
                <select class="ra-grid-filter-input form-select" id="<?= $e($id) ?>" name="<?= $e($name) ?>[]" multiple>
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
                        class="ra-grid-filter-input ra-grid-filter-from form-control"
                        type="<?= $e($inputType) ?>"
                        name="<?= $e($name) ?>[from]"
                        value="<?= $e($from) ?>"
                        placeholder="From"
                    >
                    <input
                        class="ra-grid-filter-input ra-grid-filter-to form-control"
                        type="<?= $e($inputType) ?>"
                        name="<?= $e($name) ?>[to]"
                        value="<?= $e($to) ?>"
                        placeholder="To"
                    >
                </div>
            <?php } elseif ($filter->type === 'boolean') { ?>
                <select class="ra-grid-filter-input form-select" id="<?= $e($id) ?>" name="<?= $e($name) ?>">
                    <option value="">Any</option>
                    <option value="1"<?= $e($filter->value === '1' ? ' selected' : '') ?>>Yes</option>
                    <option value="0"<?= $e($filter->value === '0' ? ' selected' : '') ?>>No</option>
                </select>
            <?php } else { ?>
                <input
                    class="ra-grid-filter-input form-control"
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
