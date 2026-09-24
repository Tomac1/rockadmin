<?php

/**
 * A badge: coloured by `$view->variant` when the value carries one — an
 * enum's own colour, or the fixed success/secondary pair `CellFormatter`
 * gives a boolean shown as a badge — and plain secondary otherwise. Reached
 * for any column, of any type, displayed as `Display::Badge`; see
 * `CellPartial::templateFor()`.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 */

$color = $view->variant ?? 'secondary';
?>
<span class="<?= $e(\RockAdmin\View\Classes::of('grid-cell-badge', null, ['badge', 'text-bg-' . $color])) ?>"><?= $e($view->text) ?></span>
