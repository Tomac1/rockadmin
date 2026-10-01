<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use RockAdmin\Auth\Gate;
use RockAdmin\Auth\Identity;
use RockAdmin\Db\Result;
use RockAdmin\Db\RowSource;
use RockAdmin\Db\SortDirection;
use RockAdmin\Form\FormFields;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\ColumnDefinition;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\RegionDefinition;
use RockAdmin\View\Classes;

/**
 * Turns a described list region and the state read out of its URL into a
 * `ListView` a template can render.
 *
 * Everything above this class describes a grid or executes one; this is
 * where the result is assembled: one `QueryFactory::build()`, one
 * `RowSource::fetch()` (two only when the requested page turns out to be
 * past the end), and every header, row and pager link decided once so
 * nothing downstream has to ask a database or a configuration object
 * anything at render time.
 *
 * **What it may draw is the gate's answer, not a rule of its own** (spec
 * 9.2). The create button needs `{page}.create` and a row's edit link needs
 * `{page}.update`, asked of the same `Gate::allows()` the kernel calls at step
 * 7 — so a control this grid draws is one the kernel will accept, and a
 * control it refuses never appears. There is deliberately no second condition
 * for writability beside the permission: a page with no form region generates
 * no write permissions at all (`Permission::forPage()` reads
 * `firstFormRegion()`), so the gate already answers no for a read-only page,
 * even to a role holding `*`. Checking the form region here as well would be
 * the same fact read twice, which is exactly how milestone 7 came to draw Edit
 * links into a 404.
 */
final class ListRegion
{
    /**
     * Built here rather than injected, from the gate, the identity and the URL
     * generator this class already holds: eleven call sites construct a
     * `ListRegion`, and an argument every one of them would have to assemble
     * out of three it is already passing is a wiring tax with no decision
     * behind it.
     */
    private readonly ActionBuilder $actions;

    public function __construct(
        private readonly RowSource $rows,
        private readonly QueryFactory $queries,
        private readonly CellFormatter $cells,
        private readonly UrlGenerator $urls,
        private readonly Gate $gate,
        /**
         * Who is asking, resolved at render time rather than at construction:
         * the region is wired once and the user belongs to the request. The
         * lookup is memoised inside `Identity`, so asking here costs no query
         * the kernel has not already paid for.
         */
        private readonly Identity $identity,
        /**
         * The session's token, needed by a `post` action's own form and by
         * nothing else this class draws. Optional because most of what builds a
         * `ListRegion` has no action column to draw; a `post` action without
         * one is refused by `ActionBuilder` rather than drawn unsigned.
         */
        ?Csrf $csrf = null,
    ) {
        $this->actions = new ActionBuilder($gate, $identity, $urls, $csrf);
    }

    public function render(PageDefinition $page, RegionDefinition $region, GridState $state): ListView
    {
        $query = $this->queries->build($page, $region, $state);
        $result = $this->rows->fetch($query);

        $limit = $query->page === null ? max(1, $region->perPage) : $query->page->limit;
        $pageCount = $result->total !== null ? max(1, (int) ceil($result->total / $limit)) : null;

        // A page bookmarked past the end must show the real last page, not an
        // empty grid — so, and only when the total says the requested page
        // does not exist, the query runs a second time for the page that does.
        if ($pageCount !== null && $state->page > $pageCount) {
            $state = $state->withPage($pageCount);
            $query = $this->queries->build($page, $region, $state);
            $result = $this->rows->fetch($query);
        }

        // Where a write comes back to: this grid's own state URL, so saving or
        // cancelling lands on the page the person left -- filters, sort and
        // page number intact, which is the whole point of spec 8.11 keeping
        // that state in the URL.
        //
        // One address, and two permissions over it. Creating and editing are
        // genuinely different rights -- a role may hold one without the other
        // -- so each gets its own nullable value, and each value decides
        // everything downstream of it: `$editReturnTo` decides both whether a
        // row carries an edit link and whether the table has an actions column
        // at all. Deciding those two from different facts is how milestone 7
        // came to render a body one cell wider than its header (fixed in
        // 32105bb), and adding a permission to the decision does not make it
        // safe to split again.
        $user = $this->identity->user();
        $stateUrl = $this->pageStateUrl($page, $region, $state);

        $createReturnTo = $this->gate->allows($user, $page->name . '.create') ? $stateUrl : null;

        // An `actions` column (spec 8.5) is the third way this project has
        // found to make a header and a body disagree about how many cells a row
        // has: a writable grid that also declares one could claim two trailing
        // cells, or claim one and draw two. It cannot, because a page that says
        // where its row controls go has said it — the grid's own Edit cell is
        // not drawn beside a column that exists to draw controls, and the one
        // value below decides the header, the rows' edit links and nothing
        // else, exactly as it did before.
        //
        // The alternative — drawing both — puts two Edit controls in a row, one
        // configured and one not, and leaves a project no way to remove the
        // second.
        $editReturnTo = $this->gate->allows($user, $page->name . '.update') && !$this->hasActionColumn($region)
            ? $stateUrl
            : null;

        return new ListView(
            key: $region->key,
            pageName: $page->name,
            columns: $this->columnViews($page, $region, $state),
            rows: $this->rowViews($page, $region, $result->rows, $editReturnTo),
            filters: $this->filterViews($region, $state),
            pagination: $this->pagination($page, $region, $state, $limit, $result),
            search: $state->search,
            searchable: $region->searchable !== [],
            regionUrl: $this->urls->route('region', ['page' => $page->name, 'region' => $region->key]),
            pageUrl: $this->urls->route('page.index', ['page' => $page->name]),
            createUrl: $createReturnTo !== null
                ? $this->urls->route(
                    'page.create',
                    ['page' => $page->name],
                    [FormFields::RETURN_TO => $createReturnTo],
                )
                : null,
            searchPlaceholder: $region->searchPlaceholder,
            // The same `$editReturnTo` the rows above were built from, so the
            // header and the body cannot disagree about how many cells a row
            // has. `hasRowActions()` reads this and nothing else.
            rowActions: $editReturnTo !== null,
        );
    }

    /** @return list<ColumnView> */
    private function columnViews(PageDefinition $page, RegionDefinition $region, GridState $state): array
    {
        $current = $state->sort[0] ?? null;
        $views = [];

        foreach ($region->columns as $column) {
            $isCurrent = $current !== null && $current->column === $column->key;

            $sortUrl = $column->sortable
                ? $this->pageStateUrl($page, $region, $state->withSort($column->key))
                : null;

            $views[] = new ColumnView(
                key: $column->key,
                label: $column->label,
                classes: Classes::of('grid-head', $column->key, $column->class !== '' ? [$column->class] : []),
                align: $column->align,
                width: $column->width,
                sortUrl: $sortUrl,
                sortDirection: $isCurrent
                    ? ($current->direction === SortDirection::Asc ? 'ascending' : 'descending')
                    : null,
            );
        }

        return $views;
    }

    /**
     * @param  list<array<string, mixed>> $rows
     * @param  ?string                    $returnTo null when this user may not update
     *                                              this page, so no row draws an edit
     *                                              link and no row draws an actions cell
     * @return list<RowView>
     */
    private function rowViews(
        PageDefinition $page,
        RegionDefinition $region,
        array $rows,
        ?string $returnTo,
    ): array {
        $views = [];

        foreach ($rows as $row) {
            $key = $row[$page->entity->key] ?? null;
            $url = $key !== null && \is_scalar($key)
                ? $this->urls->route('page.detail', ['page' => $page->name, 'id' => (string) $key])
                : '';

            $editUrl = $this->editUrl($page, $key, $returnTo);

            $cells = [];

            foreach ($region->columns as $column) {
                $cells[] = $column->type->isActions()
                    ? $this->actionCell($page->name, $column, $row)
                    : $this->cells->format($column, $row[$column->key] ?? null, $column->link ? $url : null);
            }

            $views[] = new RowView($key, $cells, $url, Classes::of('grid-row'), $editUrl);
        }

        return $views;
    }

    /** Whether this region declares a column that draws controls (spec 8.5). */
    private function hasActionColumn(RegionDefinition $region): bool
    {
        foreach ($region->columns as $column) {
            if ($column->type->isActions()) {
                return true;
            }
        }

        return false;
    }

    /**
     * One `actions` column's cell for one row.
     *
     * It is a `CellView` like every other cell, carrying no value and no URL of
     * its own — a cell per column, whatever the column holds, is what keeps a
     * row the same width as its header. The controls themselves are
     * `ActionBuilder`'s answer: this method decides nothing about them.
     *
     * The classes follow `CellFormatter`'s own rule, so `ra-grid-cell-tools` is
     * the selector 8.5 promises and the alignment is still the column's own
     * decision rather than something a template recomputes.
     *
     * @param array<string, mixed> $row
     */
    private function actionCell(string $pageName, ColumnDefinition $column, array $row): CellView
    {
        $actions = $this->actions->forRow($pageName, $column->actions, $row);

        $extra = ['text-' . $column->align];

        if ($column->class !== '') {
            $extra[] = $column->class;
        }

        // A row that offers none of its column's actions -- every one of them
        // gated away or `when`-ed out -- is empty for the same reason a null
        // value is, and says so with the same class, so a mostly-empty actions
        // column looks deliberate rather than broken.
        if ($actions === []) {
            $extra[] = 'ra-grid-cell-empty';
        }

        return new CellView(
            key: $column->key,
            value: null,
            text: '',
            type: $column->type,
            display: $column->display,
            classes: Classes::of('grid-cell', $column->key, $extra),
            url: null,
            attributes: [],
            percent: null,
            variant: null,
            actions: $actions,
        );
    }

    /**
     * One row's edit address: null when this user may not update this page —
     * which includes a page that declares no form, because such a page
     * generates no `update` permission for anybody to hold — `''` when they
     * may but this row came back with no key to address, and the URL
     * otherwise. See `RowView::$editUrl` for why the two empty cases are not
     * the same one.
     */
    private function editUrl(PageDefinition $page, mixed $key, ?string $returnTo): ?string
    {
        if ($returnTo === null) {
            return null;
        }

        if ($key === null || !\is_scalar($key)) {
            return '';
        }

        return $this->urls->route(
            'page.edit',
            ['page' => $page->name, 'id' => (string) $key],
            [FormFields::RETURN_TO => $returnTo],
        );
    }

    /** @return list<FilterView> */
    private function filterViews(RegionDefinition $region, GridState $state): array
    {
        /** @var array<string, string|list<string>|array{from?: string, to?: string}> $current */
        $current = [];

        foreach ($state->filters as $input) {
            $current[$input->column] = $input->value;
        }

        $views = [];

        foreach ($region->columns as $column) {
            $definition = $column->filter;

            if ($definition === null) {
                continue;
            }

            $views[] = new FilterView(
                key: $column->key,
                label: $definition->label,
                type: $definition->type,
                placeholder: $definition->placeholder,
                options: $definition->options,
                value: $current[$column->key] ?? '',
            );
        }

        return $views;
    }

    private function pagination(
        PageDefinition $page,
        RegionDefinition $region,
        GridState $state,
        int $limit,
        Result $result,
    ): PaginationView {
        return PaginationView::of(
            $state->page,
            $limit,
            $result->total,
            \count($result->rows),
            fn (int $number): string => $this->pageStateUrl($page, $region, $state->withPage($number)),
        );
    }

    /**
     * The whole page's own address for a given grid state -- what a sort
     * header, a pager link and the toolbar's form action all point at.
     * Never the region's fragment address: spec 8.11 promises that grid
     * state living in the URL makes a link shareable, and a link that lands
     * on a shell-less fragment when copied into a fresh tab breaks that
     * promise. core.js intercepts the click or submit and turns this same
     * address into a region fetch instead; a no-JavaScript browser, or
     * anyone who copies the link, simply follows it and gets the whole page.
     */
    private function pageStateUrl(PageDefinition $page, RegionDefinition $region, GridState $state): string
    {
        return $this->urls->route(
            'page.index',
            ['page' => $page->name],
            $this->flatten($state->toQuery($region->key)),
        );
    }

    /**
     * `GridState::toQuery()` nests a region's parameters under its own key so
     * several regions can share one query string. `UrlGenerator` wants a flat
     * `array<string, string|int>` because it never decides how a value is
     * encoded on its own -- that is `build()`'s job, applied once, uniformly.
     * Flattening here, into literal bracket-suffixed keys such as
     * 'grid[f][state]', produces exactly the string `http_build_query()` would
     * have produced from the nested array; PHP's own query-string parser reads
     * the two identically on the way back in.
     *
     * @param  array<array-key, mixed> $value
     * @return array<string, string|int>
     */
    private function flatten(array $value, string $prefix = ''): array
    {
        $flat = [];

        foreach ($value as $key => $item) {
            $paramKey = $prefix === '' ? (string) $key : "{$prefix}[{$key}]";

            if (\is_array($item)) {
                $flat = [...$flat, ...$this->flatten($item, $paramKey)];

                continue;
            }

            if (\is_int($item)) {
                $flat[$paramKey] = $item;

                continue;
            }

            // GridState::toQuery() only ever puts a string, an int or a
            // nested array at a leaf; the nested case is handled above. A
            // scalar guard keeps this honest instead of casting whatever
            // mixed happens to arrive.
            $flat[$paramKey] = \is_scalar($item) ? (string) $item : '';
        }

        return $flat;
    }
}
