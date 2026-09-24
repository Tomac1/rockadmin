<?php

/**
 * A datetime value, already formatted by `CellFormatter`. Ships as its own
 * file for the same reason as `cell/money.php` — an override seam a project
 * can point at, not a difference in what the default mapping renders today.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 */
?>
<time class="ra-grid-cell-datetime"><?= $e($view->text) ?></time>
