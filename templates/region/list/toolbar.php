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
 * The current sort travels as a hidden input, `<region>[sort]`, in the same
 * bracket convention `GridState::toQuery()` uses -- built here from
 * `$view->columns` rather than from a `GridState` this template is never
 * handed, since at most one column ever carries a `sortDirection`. Without
 * it, submitting the form (with JavaScript or without) carries only `q` and
 * the filters: `GridState::fromQuery()` finds no `sort` key in the request,
 * falls back to the region's own default, and a person who sorted by price
 * and then searched watches the grid silently snap back to it -- and the
 * URL this form's submit pushes into the address bar, spec 8.11's
 * shareable link, has quietly lost half the state it promised to carry.
 * Resetting to page 1 is different: a new search or filter really does
 * start a fresh result set, so this form carries no page number at all.
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
        <?php foreach ($view->columns as $column) { ?>
            <?php if ($column->sortDirection !== null) { ?>
                <input
                    type="hidden"
                    name="<?= $e($view->key) ?>[sort]"
                    value="<?= $e(($column->sortDirection === 'descending' ? '-' : '') . $column->key) ?>"
                >
            <?php } ?>
        <?php } ?>
        <button class="ra-grid-toolbar-submit btn btn-primary" type="submit">Apply</button>
    </form>
<?php } ?>
