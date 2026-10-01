<?php

/**
 * One row: `<tr data-id="…">`, and one cell partial per cell.
 *
 * Which partial draws a given cell's display is `CellPartial::templateFor()`'s
 * decision, not this template's own — `templates/region/preview/field.php`
 * needs the very same mapping for the very same reason, and writing it out
 * twice is the shape rule 3 exists to forbid. A cell whose `$url` is set
 * (the column declared `link: true`) is wrapped in `region/list/cell/link.php`
 * instead, regardless of its display — a link is a wrapper around whatever
 * the display drew, not a display of its own.
 *
 * The last cell is the actions one, and it exists exactly when
 * `ListView::hasRowActions()` put a `<th>` there: `$view->editUrl` is null for
 * a page that declares no form, and `''` for a row that came back with no key
 * to address — which still draws the cell, empty, because a body that
 * sometimes omitted it would shift every column in that row.
 *
 * @var \RockAdmin\Grid\RowView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 * @var \Closure(mixed): string $href
 * @var \Closure(string, mixed=): string $partial
 */
?>
<tr class="<?= $e($view->classes) ?>" data-id="<?= $e($view->key) ?>">
    <?php if ($view->selectionFormId !== null) { ?>
        <?php
        /*
         * The checkbox submits with a form it is not inside, by `form="…"`.
         * A selection form wrapping the table would contain the `<form>` an
         * action cell draws, and nested forms are dropped by the browser
         * without a word — every row action would become a button that does
         * nothing with JavaScript off, and nothing would say why.
         */
        ?>
        <td class="ra-grid-cell-select">
            <label class="ra-checkbox-label ra-grid-select-label">
                <input
                    class="ra-checkbox-input ra-grid-select"
                    type="checkbox"
                    form="<?= $e($view->selectionFormId) ?>"
                    name="<?= $e(\RockAdmin\Form\FormFields::IDS) ?>[]"
                    value="<?= $e($view->key) ?>"
                    data-ra-select
                    aria-label="Select row <?= $e($view->key) ?>"
                >
                <span class="ra-checkbox-box" aria-hidden="true">
                    <svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="2,6 5,9 10,3"/></svg>
                </span>
            </label>
        </td>
    <?php } ?>
    <?php foreach ($view->cells as $cell) { ?>
        <td class="<?= $e($cell->classes) ?>"<?= $attrs($cell->attributes) ?>>
            <?= $partial(
                $cell->url !== null ? 'region/list/cell/link' : \RockAdmin\Grid\CellPartial::templateFor($cell),
                $cell,
            ) ?>
        </td>
    <?php } ?>
    <?php if ($view->editUrl !== null) { ?>
        <td class="ra-td-actions ra-grid-cell-actions">
            <?php if ($view->editUrl !== '') { ?>
                <a class="ra-btn ra-btn--ghost ra-btn--sm ra-grid-edit" href="<?= $href($view->editUrl) ?>">Edit</a>
            <?php } ?>
        </td>
    <?php } ?>
</tr>
