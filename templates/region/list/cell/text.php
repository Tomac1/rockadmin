<?php

/**
 * The default cell: whatever `CellFormatter` already decided the value looks
 * like, printed as text. Used for every `Display::Plain` cell — a text, a
 * money, a datetime and a JSON column all arrive with their final text
 * already built, so there is nothing left for a template to tell them apart
 * by; see `row.php` for the full reasoning.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 */
?>
<span class="ra-grid-cell-text"><?= $e($view->text) ?></span>
