<?php

/**
 * Two regions side by side: the main content and a narrower side column.
 * A page that uses this layout with only one region still renders — an
 * empty column, not a failure — because the layout does not know what a
 * region is, only its own slot names.
 *
 * @var \RockAdmin\View\PageView $view
 * @var \Closure(mixed): string $raw
 */
?>
<div class="ra-layout ra-layout-two-column">
    <div class="ra-layout-main"><?= $raw($view->slot('main')) ?></div>
    <div class="ra-layout-side"><?= $raw($view->slot('side')) ?></div>
</div>
