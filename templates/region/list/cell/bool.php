<?php

/**
 * A boolean cell: a check mark for `Display::Check`, or the word "Yes"/"No"
 * for `Display::YesNo` — `CellFormatter` already picked the text, this just
 * prints it. A boolean column shown as a badge is `Display::Badge` instead,
 * so it renders through `cell/enum.php`, not here; see `row.php`.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 */
?>
<span class="ra-grid-cell-bool"><?= $e($view->text) ?></span>
