<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use RockAdmin\Db\Result;
use RockAdmin\Db\RowSource;
use RockAdmin\Db\SortDirection;
use RockAdmin\Http\UrlGenerator;
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
 */
final class ListRegion
{
    public function __construct(
        private readonly RowSource $rows,
        private readonly QueryFactory $queries,
        private readonly CellFormatter $cells,
        private readonly UrlGenerator $urls,
    ) {
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

        return new ListView(
            key: $region->key,
            pageName: $page->name,
            columns: $this->columnViews($page, $region, $state),
            rows: $this->rowViews($page, $region, $result->rows),
            filters: $this->filterViews($region, $state),
            pagination: $this->pagination($page, $region, $state, $limit, $result),
            search: $state->search,
            searchable: $region->searchable !== [],
            regionUrl: $this->urls->route('region', ['page' => $page->name, 'region' => $region->key]),
            pageUrl: $this->urls->route('page.index', ['page' => $page->name]),
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
     * @return list<RowView>
     */
    private function rowViews(PageDefinition $page, RegionDefinition $region, array $rows): array
    {
        $views = [];

        foreach ($rows as $row) {
            $key = $row[$page->entity->key] ?? null;
            $url = $key !== null && \is_scalar($key)
                ? $this->urls->route('page.detail', ['page' => $page->name, 'id' => (string) $key])
                : '';

            $cells = [];

            foreach ($region->columns as $column) {
                $cells[] = $this->cells->format($column, $row[$column->key] ?? null, $column->link ? $url : null);
            }

            $views[] = new RowView($key, $cells, $url, Classes::of('grid-row'));
        }

        return $views;
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
