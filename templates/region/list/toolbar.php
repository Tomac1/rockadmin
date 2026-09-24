<?php

/**
 * The search box and the filter controls, in one ordinary GET form. This is
 * the no-JavaScript path: submitting it navigates to the whole page's own
 * address with the new query string, exactly what a sort link or a pager
 * link already does -- `$view->pageUrl`, not the region's fragment address,
 * because a submit with no JavaScript must land on a full page, not a
 * shell-less fragment. `core.js` intercepts the submit and fetches the
 * region's own fragment address with the same query instead — declared here
 * as `data-ra-behavior="grid-toolbar"`, nothing scripted.
 *
 * @var \RockAdmin\Grid\ListView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 * @var \Closure(string, mixed=): string $partial
 */
?>
<?php if ($view->searchable || $view->filters !== []) { ?>
    <form class="ra-grid-toolbar" method="get" action="<?= $href($view->pageUrl) ?>" data-ra-behavior="grid-toolbar">
        <?php if ($view->searchable) { ?>
            <div class="ra-grid-search">
                <label class="ra-grid-search-label visually-hidden" for="ra-grid-search-<?= $e($view->key) ?>">Search</label>
                <input
                    class="ra-grid-search-input form-control"
                    type="search"
                    id="ra-grid-search-<?= $e($view->key) ?>"
                    name="<?= $e($view->key) ?>[q]"
                    value="<?= $e($view->search) ?>"
                    placeholder="Search…"
                >
            </div>
        <?php } ?>
        <?php if ($view->filters !== []) { ?>
            <?= $partial('region/list/filters', $view) ?>
        <?php } ?>
        <button class="ra-grid-toolbar-submit btn btn-primary" type="submit">Apply</button>
    </form>
<?php } ?>
