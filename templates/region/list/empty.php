<?php

/**
 * What an empty grid says. An empty table with no explanation reads as
 * broken; "nothing here yet" and "nothing matches these filters" are
 * different facts, and only `ListView::isFilteredEmpty()` knows which one is
 * true — a search or a filter is active, so there is data somewhere, just
 * not on this page, and the reader gets a link that clears them rather than
 * being left to guess why the table is blank.
 *
 * The clear link addresses `$view->pageUrl`, never `$view->regionUrl`: the
 * same rule a sort or pager link already follows (see `ListRegion::pageStateUrl()`),
 * because `regionUrl` is the fragment's own address, with no shell — a
 * plain no-JavaScript follow, or a copied link, would land on 13 KB of HTML
 * with no `<html>` around it. `data-ra-action="paginate"` lets `core.js`
 * intercept it the same way it intercepts a pager link, so the region
 * reloads in place instead of a full page navigation; the action name
 * matters only as a key into `core.js`'s registry, and `followRegionLink()`
 * is exactly what both need.
 *
 * @var \RockAdmin\Grid\ListView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 */
?>
<div class="ra-grid-empty">
    <?php if ($view->isFilteredEmpty()) { ?>
        <p class="ra-grid-empty-message ra-grid-empty-filtered">Nothing matches these filters.</p>
        <a class="ra-grid-empty-clear" href="<?= $href($view->pageUrl) ?>" data-ra-action="paginate">Clear filters and search</a>
    <?php } else { ?>
        <p class="ra-grid-empty-message ra-grid-empty-unfiltered">Nothing here yet.</p>
    <?php } ?>
</div>
