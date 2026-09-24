<?php

/**
 * The grid table itself: a head built from the columns, a body built from
 * the rows. Nothing here decides anything — `head.php` and `body.php` do —
 * this only holds the two together inside one `<table>`. Carries no
 * `data-ra-behavior`: nothing in `core.js` ever attached one to `grid-table`,
 * and an unbound "behaviour" attribute is worse than none, since a project
 * cannot tell "this build binds nothing here" from "the binding failed".
 *
 * @var \RockAdmin\Grid\ListView $view
 * @var \Closure(string, mixed=): string $partial
 */
?>
<table class="ra-grid-table table table-hover">
    <?= $partial('region/list/head', $view) ?>
    <?= $partial('region/list/body', $view) ?>
</table>
