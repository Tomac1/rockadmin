<?php

/**
 * A badge: coloured by `$view->variant` when the value carries one — an
 * enum's own colour, or the fixed success/secondary pair `CellFormatter`
 * gives a boolean shown as a badge — and plain secondary otherwise. Reused
 * for any column, of any type, displayed as a badge: a `text` column shown
 * as a badge renders exactly this markup, which is the concrete example
 * `row.php`'s mapping comment gives for choosing by display rather than by
 * type.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 */

$color = $view->variant ?? 'secondary';
?>
<span class="<?= $e(\RockAdmin\View\Classes::of('grid-cell-badge', null, ['badge', 'text-bg-' . $color])) ?>"><?= $e($view->text) ?></span>
