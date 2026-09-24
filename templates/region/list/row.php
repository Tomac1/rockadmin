<?php

/**
 * One row: `<tr data-id="…">`, and one cell partial per cell.
 *
 * The partial is chosen by the cell's display when that display is
 * distinctive — a badge, a checkbox, a progress bar and a link each draw
 * something a plain cell does not. Four of the seven types share
 * `Display::Plain`, though, so a plain cell falls back to its type: money, a
 * date and JSON each want their own markup, and without the type there would
 * be nothing left to tell them apart by.
 *
 * Both are enums rather than configuration. Rule 4 forbids handing a template
 * a `ColumnDefinition`; it does not forbid telling it what a value is.
 *
 * @var \RockAdmin\Grid\RowView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 * @var \Closure(string, mixed=): string $partial
 */

$cellPartial = static fn (\RockAdmin\Grid\CellView $cell): string => match ($cell->display) {
    \RockAdmin\Page\Display::Badge => 'region/list/cell/enum',
    \RockAdmin\Page\Display::Check, \RockAdmin\Page\Display::YesNo => 'region/list/cell/bool',
    \RockAdmin\Page\Display::Progress, \RockAdmin\Page\Display::Percent => 'region/list/cell/int',
    \RockAdmin\Page\Display::Link => 'region/list/cell/link',
    \RockAdmin\Page\Display::Plain => match ($cell->type) {
        \RockAdmin\Page\ColumnType::Money => 'region/list/cell/money',
        \RockAdmin\Page\ColumnType::Datetime => 'region/list/cell/datetime',
        \RockAdmin\Page\ColumnType::Json => 'region/list/cell/json',
        \RockAdmin\Page\ColumnType::Int => 'region/list/cell/int',
        \RockAdmin\Page\ColumnType::Text,
        \RockAdmin\Page\ColumnType::Bool,
        \RockAdmin\Page\ColumnType::Enum => 'region/list/cell/text',
    },
};
?>
<tr class="<?= $e($view->classes) ?>" data-id="<?= $e($view->key) ?>">
    <?php foreach ($view->cells as $cell) { ?>
        <td class="<?= $e($cell->classes) ?>"<?= $attrs($cell->attributes) ?>>
            <?= $partial($cellPartial($cell), $cell) ?>
        </td>
    <?php } ?>
</tr>
