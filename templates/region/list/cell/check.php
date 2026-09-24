<?php

/**
 * A boolean cell shown as a check mark, reached whenever the display is
 * `Display::Check`. `CellFormatter` has already picked the text — a check
 * mark or nothing — so this only prints it. A boolean column shown as a
 * badge is `Display::Badge` instead, drawn by `cell/badge.php`; see
 * `CellPartial::templateFor()`.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 */
?>
<span class="ra-grid-cell-check"><?= $e($view->text) ?></span>
