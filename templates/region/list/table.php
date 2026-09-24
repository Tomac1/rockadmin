<?php

/**
 * The grid table itself: a head built from the columns, a body built from
 * the rows. Nothing here decides anything — `head.php` and `body.php` do —
 * this only holds the two together inside one `<table>`.
 *
 * @var \RockAdmin\Grid\ListView $view
 * @var \Closure(string, mixed=): string $partial
 */
?>
<table class="ra-grid-table table table-hover" data-ra-behavior="grid-table">
    <?= $partial('region/list/head', $view) ?>
    <?= $partial('region/list/body', $view) ?>
</table>
