<?php

/**
 * One label-and-value pair, or a wide row when `$view->wide` is true — a
 * `json` cell, a long `text` cell, spanning the full width instead of
 * squeezing into a definition-list column.
 *
 * The value itself is drawn by the very same cell partial a grid cell of
 * the same display would use — `region/list/cell/*.php` — so a value reads
 * identically wherever it is shown; see
 * `PreviewRegionTest::testThePreviewAndTheGridFormatTheSameValueIdentically()`.
 * A cell with nothing to show (`CellFormatter` renders a null value as
 * empty text) uses `region/preview/missing` instead, so an empty field
 * reads as "not set" rather than as a blank line.
 *
 * @var \RockAdmin\Grid\FieldView $view
 * @var \Closure(mixed): string $e
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
<div class="<?= $e($view->classes()) ?>">
    <dt class="ra-field-label"><?= $e($view->label) ?></dt>
    <dd class="ra-field-value">
        <?php if ($view->cell->text === '') { ?>
            <?= $partial('region/preview/missing', $view) ?>
        <?php } else { ?>
            <?= $partial($cellPartial($view->cell), $view->cell) ?>
        <?php } ?>
    </dd>
</div>
