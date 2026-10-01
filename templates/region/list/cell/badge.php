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

/*
 * The design system names a badge by its colour, not by what the colour
 * means: there is `ra-badge--green`, not `ra-badge--success`. Configuration
 * writes the meaning -- `'color' => 'success'` on an enum option -- because
 * that is what somebody describing a page knows, so the two are joined here.
 *
 * An unknown name falls back to grey rather than being passed through: a
 * class the design system does not define renders as an unstyled span, which
 * reads as a missing value rather than as a mistake in configuration.
 */
$palette = [
    'success' => 'green',
    'danger' => 'red',
    'warning' => 'yellow',
    'info' => 'blue',
    'primary' => 'indigo',
    'secondary' => 'gray',
];

$color = $palette[$view->variant ?? 'secondary'] ?? 'gray';
?>
<span class="<?= $e(\RockAdmin\View\Classes::of('grid-cell-badge', null, ['ra-badge', 'ra-badge--' . $color, 'ra-badge--sm'])) ?>"><?= $e($view->text) ?></span>
