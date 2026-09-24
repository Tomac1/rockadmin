<?php

/**
 * A money value: `CellFormatter` has already produced the formatted amount,
 * currency included when the column configures one, so this only prints it.
 * A file of its own rather than sharing `cell/plain.php`, so a project can
 * restyle money cells — tabular figures, a currency icon — without touching
 * every other plain cell. Reached by `CellPartial::templateFor()` whenever
 * the display is `Display::Plain` and the column's type is `Money`.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 */
?>
<span class="ra-grid-cell-money"><?= $e($view->text) ?></span>
