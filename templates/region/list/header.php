<?php

/**
 * The grid's own header strip: the one place a page-level action lives.
 *
 * Right now that is the create button, and only when the page declares a form
 * region -- `ListView::$createUrl` is null otherwise, so a page with nothing
 * to create draws no button rather than one that 404s. Milestone 8's header
 * actions (spec 8.5) arrive in this file beside it.
 *
 * The link carries the grid's own state as its return address, built by
 * `ListRegion` rather than here: a template has no `UrlGenerator` and must
 * never assemble a URL, and the return address is the grid's current filters,
 * sort and page, which only the region knows.
 *
 * It is an ordinary link, with no `data-ra-action`: creating a row is a whole
 * page, not a fragment swap, so there is nothing for `core.js` to intercept.
 *
 * @var \RockAdmin\Grid\ListView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 */
?>
<?php if ($view->createUrl !== null) { ?>
    <div class="ra-grid-header">
        <a class="ra-grid-create btn btn-primary" href="<?= $href($view->createUrl) ?>">New <?= $e($view->pageName) ?></a>
    </div>
<?php } ?>
