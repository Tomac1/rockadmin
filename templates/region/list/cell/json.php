<?php

/**
 * A JSON value, truncated by `CellFormatter` when it ran long. When it was
 * truncated, the untruncated value travels in `$view->attributes['title']`,
 * so hovering the cell shows the whole thing without the grid growing to fit
 * it — `$attrs()` writes that attribute only when it is actually present.
 * Reached by `CellPartial::templateFor()` whenever the display is
 * `Display::Plain` and the column's type is `Json`.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 */
?>
<span class="ra-grid-cell-json"<?= $attrs($view->attributes) ?>><?= $e($view->text) ?></span>
