<?php

/**
 * A cell wrapped in a link to the row's detail page. `Display::Link` is only
 * ever paired with `Text` or `Int` (see `ColumnType::allows()`), and neither
 * gives it a progress bar or a badge, so the content it wraps is always
 * plain text — there is no further display to delegate to, only the anchor
 * this partial itself adds.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 */
?>
<?php if ($view->url !== null) { ?>
    <a class="ra-grid-cell-link" href="<?= $href($view->url) ?>"><?= $e($view->text) ?></a>
<?php } else { ?>
    <span class="ra-grid-cell-link"><?= $e($view->text) ?></span>
<?php } ?>
