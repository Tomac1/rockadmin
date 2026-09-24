<?php

/**
 * A number shown as a percentage, with no bar — reached whenever the
 * display is `Display::Percent`. `CellFormatter` has already formatted the
 * text (e.g. "42 %"), so this only prints it.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 */
?>
<span class="ra-grid-cell-percent"><?= $e($view->text) ?></span>
