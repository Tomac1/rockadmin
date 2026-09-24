<?php

/**
 * A cell wrapped in an anchor to the row's detail page. Applied whenever
 * `$view->url !== null` — a fact of the column's own `link: true`, decided
 * once by `ListRegion` and carried on `CellView::$url`, never a display of
 * its own. This is a wrapper, not an alternative rendering: the content
 * inside the anchor is exactly what `CellPartial::templateFor()` would have
 * drawn for this cell's actual display, so a badge, a progress bar or plain
 * text can all open the same row.
 *
 * Callers (`row.php`, `field.php`) reach this template only when
 * `$view->url` is set, so it is not re-checked here.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $href
 * @var \Closure(string, mixed=): string $partial
 */
?>
<a class="ra-grid-cell-link" href="<?= $href($view->url) ?>"><?= $partial(\RockAdmin\Grid\CellPartial::templateFor($view), $view) ?></a>
