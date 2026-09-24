<?php

/**
 * A datetime value, already formatted by `CellFormatter`. Ships as its own
 * file for the same reason as `cell/money.php` — an override seam a project
 * can point at. Reached by `CellPartial::templateFor()` whenever the display
 * is `Display::Plain` and the column's type is `Datetime`.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 */
?>
<time class="ra-grid-cell-datetime"><?= $e($view->text) ?></time>
