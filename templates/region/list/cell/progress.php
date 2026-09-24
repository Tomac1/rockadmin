<?php

/**
 * A number shown as a progress bar, sized from `$view->percent` — reached
 * whenever the display is `Display::Progress`. The percentage decides the
 * bar's width only; it never decides its colour, so a grid full of bars does
 * not start reading as a status board.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 */
?>
<div class="ra-grid-cell-progress progress" role="progressbar" aria-valuenow="<?= $e($view->percent) ?>" aria-valuemin="0" aria-valuemax="100">
    <div class="ra-grid-cell-progress-bar progress-bar" style="width: <?= $e($view->percent) ?>%"><?= $e($view->text) ?></div>
</div>
