<?php

/**
 * The pager. Every link here — previous, next, and each numbered page — was
 * already built by `PaginationView::of()` from the grid's full current
 * state, filters and sort included, so paging never resets either: this
 * template only writes out the URLs it was handed, never builds one of its
 * own. Each link's own `data-ra-action="paginate"` is what `core.js`
 * actually binds; the `<nav>` itself carries no `data-ra-behavior` —
 * nothing was ever registered under `grid-pagination`, and a "behaviour"
 * attribute nothing binds is worse than none, since a project cannot tell
 * "this build binds nothing here" from "the binding failed".
 *
 * @var \RockAdmin\Grid\PaginationView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 */
?>
<?php if ($view->hasPrevious || $view->hasNext || \count($view->pages) > 1) { ?>
    <nav class="ra-grid-pagination" aria-label="Pagination">
        <ul class="ra-grid-pagination-list pagination">
            <li class="ra-grid-pagination-item ra-grid-pagination-prev page-item<?= $e($view->hasPrevious ? '' : ' disabled') ?>">
                <?php if ($view->hasPrevious && $view->previousUrl !== null) { ?>
                    <a class="ra-grid-pagination-link page-link" href="<?= $href($view->previousUrl) ?>" data-ra-action="paginate" rel="prev">Previous</a>
                <?php } else { ?>
                    <span class="ra-grid-pagination-link page-link">Previous</span>
                <?php } ?>
            </li>
            <?php foreach ($view->pages as $page) { ?>
                <li class="ra-grid-pagination-item page-item<?= $e($page['current'] ? ' active' : '') ?>">
                    <?php if ($page['current']) { ?>
                        <span class="ra-grid-pagination-link page-link" aria-current="page"><?= $e($page['number']) ?></span>
                    <?php } else { ?>
                        <a class="ra-grid-pagination-link page-link" href="<?= $href($page['url']) ?>" data-ra-action="paginate"><?= $e($page['number']) ?></a>
                    <?php } ?>
                </li>
            <?php } ?>
            <li class="ra-grid-pagination-item ra-grid-pagination-next page-item<?= $e($view->hasNext ? '' : ' disabled') ?>">
                <?php if ($view->hasNext && $view->nextUrl !== null) { ?>
                    <a class="ra-grid-pagination-link page-link" href="<?= $href($view->nextUrl) ?>" data-ra-action="paginate" rel="next">Next</a>
                <?php } else { ?>
                    <span class="ra-grid-pagination-link page-link">Next</span>
                <?php } ?>
            </li>
        </ul>
    </nav>
<?php } ?>
