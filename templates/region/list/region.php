<?php

/**
 * The list region: search and filters, the table or the empty state, and the
 * pager. `data-ra-region` and `data-ra-region-url` are what `core.js` reads
 * to know which region this fragment belongs to and where to reload it from
 * after a filter, a sort or a page change — the same `regionUrl` every link
 * and form inside this fragment already points at, so a reload and a
 * first-load render identically.
 *
 * @var \RockAdmin\Grid\ListView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(string, mixed=): string $partial
 */

$classes = 'ra-region ra-region-list ' . \RockAdmin\View\Classes::identity('region-list', $view->pageName);
?>
<div class="<?= $e($classes) ?>" data-ra-region="<?= $e($view->key) ?>" data-ra-region-url="<?= $e($view->regionUrl) ?>">
    <?= $partial('region/list/toolbar', $view) ?>
    <?php if ($view->isEmpty()) { ?>
        <?= $partial('region/list/empty', $view) ?>
    <?php } else { ?>
        <?= $partial('region/list/table', $view) ?>
    <?php } ?>
    <?= $partial('region/list/pagination', $view->pagination) ?>
</div>
