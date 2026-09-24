<?php

/**
 * One row: `<tr data-id="…">`, and one cell partial per cell — the partial
 * chosen by the cell's own display, not by its column's type.
 *
 * Display, not type, because `CellView` never carries a `RockAdmin\Page\ColumnType`
 * — handing a template a type would hand it a piece of configuration, which
 * rule 4 of this project forbids, and it would also mean two different facts
 * (type and display) both having to agree before the template knew what to
 * draw. `Display::Plain` alone already covers a text, a money, a datetime and
 * a JSON cell identically, because `CellFormatter` has turned every one of
 * them into its final display text before this template ever sees it —
 * there is nothing left for a partial to tell them apart by, so they share
 * `cell/text.php`. `cell/money.php`, `cell/datetime.php` and `cell/json.php`
 * still ship as their own files — an override seam for a project that wants
 * one of them to look different — but this default mapping has no way to
 * reach them on its own, for the same reason it has no way to reach a type
 * at all.
 *
 * @var \RockAdmin\Grid\RowView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 * @var \Closure(string, mixed=): string $partial
 */

$cellPartial = static fn (\RockAdmin\Page\Display $display): string => match ($display) {
    \RockAdmin\Page\Display::Plain => 'region/list/cell/text',
    \RockAdmin\Page\Display::Badge => 'region/list/cell/enum',
    \RockAdmin\Page\Display::Check, \RockAdmin\Page\Display::YesNo => 'region/list/cell/bool',
    \RockAdmin\Page\Display::Progress, \RockAdmin\Page\Display::Percent => 'region/list/cell/int',
    \RockAdmin\Page\Display::Link => 'region/list/cell/link',
};
?>
<tr class="ra-grid-row <?= $e($view->classes) ?>" data-id="<?= $e($view->key) ?>">
    <?php foreach ($view->cells as $cell) { ?>
        <td class="<?= $e($cell->classes) ?>"<?= $attrs($cell->attributes) ?>>
            <?= $partial($cellPartial($cell->display), $cell) ?>
        </td>
    <?php } ?>
</tr>
