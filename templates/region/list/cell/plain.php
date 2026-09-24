<?php

/**
 * The default cell: whatever `CellFormatter` already decided the value looks
 * like, printed as text. Reached whenever the display is `Display::Plain`
 * and the type has no template of its own — text, int, bool and enum all
 * arrive here; money, datetime and JSON keep their own file so a project can
 * restyle them separately without touching this one (see
 * `CellPartial::templateFor()`).
 *
 * The wrapping class still follows the column's own type rather than a
 * single fixed name, so "every int cell" and "every text cell" stay two
 * separate things a project can target even though both reach this file.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 */

$typeSlug = match ($view->type) {
    \RockAdmin\Page\ColumnType::Int => 'int',
    \RockAdmin\Page\ColumnType::Bool => 'bool',
    \RockAdmin\Page\ColumnType::Enum => 'enum',
    \RockAdmin\Page\ColumnType::Text,
    \RockAdmin\Page\ColumnType::Money,
    \RockAdmin\Page\ColumnType::Datetime,
    \RockAdmin\Page\ColumnType::Json => 'text',
};
?>
<span class="<?= $e(\RockAdmin\View\Classes::of('grid-cell-' . $typeSlug)) ?>"><?= $e($view->text) ?></span>
