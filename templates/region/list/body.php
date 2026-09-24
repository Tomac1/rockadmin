<?php

/**
 * One `region/list/row` per row. This template only knows there are rows —
 * `row.php` is where each row's cells get chosen a partial.
 *
 * @var \RockAdmin\Grid\ListView $view
 * @var \Closure(string, mixed=): string $partial
 */
?>
<tbody class="ra-grid-body">
    <?php foreach ($view->rows as $row) { ?>
        <?= $partial('region/list/row', $row) ?>
    <?php } ?>
</tbody>
