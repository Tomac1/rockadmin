<?php

/**
 * A money value: `CellFormatter` has already produced the formatted amount,
 * currency included when the column configures one, so this only prints it.
 * A file of its own rather than reusing `cell/text.php` directly, so a
 * project can restyle money cells — tabular figures, a currency icon —
 * without touching every other plain cell; the default row-to-partial
 * mapping in `row.php` cannot reach this file on its own, because a `Money`
 * column is a `Display::Plain` cell exactly like a text one, and nothing
 * that reaches a template says otherwise.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 */
?>
<span class="ra-grid-cell-money"><?= $e($view->text) ?></span>
