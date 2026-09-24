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
 * @var \RockAdmin\Grid\RowView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 * @var \Closure(string, mixed=): string $partial
 */
?>
<tr class="<?= $e($view->classes) ?>" data-id="<?= $e($view->key) ?>">
    <?php foreach ($view->cells as $cell) { ?>
        <td class="<?= $e($cell->classes) ?>"<?= $attrs($cell->attributes) ?>>
            <?= $partial(
                $cell->url !== null ? 'region/list/cell/link' : \RockAdmin\Grid\CellPartial::templateFor($cell),
                $cell,
            ) ?>
        </td>
    <?php } ?>
</tr>
