<?php

/**
 * A number, or — for `Display::Progress` and `Display::Percent` — a bar
 * sized from `$view->percent`. The percentage decides the bar's width only;
 * it never decides its colour, so a grid full of bars does not start reading
 * as a status board.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 */
?>
<?php if ($view->display === \RockAdmin\Page\Display::Progress && $view->percent !== null) { ?>
    <div class="ra-grid-cell-progress progress" role="progressbar" aria-valuenow="<?= $e($view->percent) ?>" aria-valuemin="0" aria-valuemax="100">
        <div class="ra-grid-cell-progress-bar progress-bar" style="width: <?= $e($view->percent) ?>%"><?= $e($view->text) ?></div>
    </div>
<?php } elseif ($view->display === \RockAdmin\Page\Display::Percent) { ?>
    <span class="ra-grid-cell-percent"><?= $e($view->text) ?></span>
<?php } else { ?>
    <span class="ra-grid-cell-int"><?= $e($view->text) ?></span>
<?php } ?>
