<?php

/**
 * What an empty grid says. An empty table with no explanation reads as
 * broken; "nothing here yet" and "nothing matches these filters" are
 * different facts, and only `ListView::isFilteredEmpty()` knows which one is
 * true — a search or a filter is active, so there is data somewhere, just
 * not on this page, and the reader gets a link that clears them rather than
 * being left to guess why the table is blank.
 *
 * @var \RockAdmin\Grid\ListView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 */
?>
<div class="ra-grid-empty">
    <?php if ($view->isFilteredEmpty()) { ?>
        <p class="ra-grid-empty-message ra-grid-empty-filtered">Nothing matches these filters.</p>
        <a class="ra-grid-empty-clear" href="<?= $href($view->regionUrl) ?>">Clear filters and search</a>
    <?php } else { ?>
        <p class="ra-grid-empty-message ra-grid-empty-unfiltered">Nothing here yet.</p>
    <?php } ?>
</div>
