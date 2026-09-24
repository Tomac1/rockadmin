<?php

/**
 * A boolean cell shown as the word "Yes" or "No", reached whenever the
 * display is `Display::YesNo`. `CellFormatter` has already picked the text,
 * so this only prints it. A boolean column shown as a badge is
 * `Display::Badge` instead, drawn by `cell/badge.php`; see
 * `CellPartial::templateFor()`.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 */
?>
<span class="ra-grid-cell-yesno"><?= $e($view->text) ?></span>
