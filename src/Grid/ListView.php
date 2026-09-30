<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use RockAdmin\View\Classes;

/**
 * A grid, fully assembled: rows, headers, filters and a pager, ready for a
 * template to read.
 *
 * Nothing here is a `RockAdmin\Page\ColumnDefinition` or any other piece of
 * configuration — rule 4 of this project forbids a template reaching two
 * layers down — and nothing here computes at render time: every URL, every
 * class and every direction was already decided by `ListRegion`.
 */
final class ListView
{
    /**
     * @param list<ColumnView> $columns
     * @param list<RowView>    $rows
     * @param list<FilterView> $filters
     */
    public function __construct(
        public readonly string $key,
        public readonly string $pageName,
        public readonly array $columns,
        public readonly array $rows,
        public readonly array $filters,
        public readonly PaginationView $pagination,
        public readonly string $search,
        public readonly bool $searchable,
        /**
         * '/r/{page}/{region}', the region's own fragment address. Used to
         * fetch a refresh and to carry the region's identity on the
         * fragment's own root (data-ra-region-url) -- never as the href of
         * a link a person might follow or copy, because a fragment on its
         * own has no shell.
         */
        public readonly string $regionUrl,
        /**
         * '/p/{page}', the whole page carrying this region -- with the
         * grid's own query string appended, this is what every sort link,
         * pager link and the toolbar's form action point at, so following
         * or copying one always lands on the full page it promises (spec
         * 8.11: grid state lives in the URL precisely so a link is
         * shareable). core.js reads the same query back off it to build the
         * region's own fetch address instead of navigating there.
         */
        public readonly string $pageUrl,
        /**
         * '/p/{page}/create' with this grid's own state as the return
         * address, or null when the page declares no form region and there
         * is therefore nothing to create. The same fact decides whether each
         * row carries an edit link, which is what `hasRowActions()` reads:
         * a grid that can create can edit, because both are the one form.
         */
        public readonly ?string $createUrl = null,
    ) {
    }

    /**
     * Whether the table carries an actions column at all. `head.php` and
     * `row.php` must agree about this or the header and the body disagree
     * about how many cells a row has, so it is answered here once rather
     * than inferred twice.
     */
    public function hasRowActions(): bool
    {
        return $this->createUrl !== null;
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /**
     * Why the grid has nothing to show, for a template that wants to say
     * "nothing here yet" rather than "nothing matches these filters".
     *
     * An unfiltered, unsearched grid with no rows has no data at all. A grid
     * with an active search or filter has rows somewhere; this one simply did
     * not find any, and the honest fix is to offer a link that clears them
     * rather than implying the table itself is empty. `$filters` lists every
     * filterable column whether or not it is set, so "active" is read from
     * each filter's own value — untouched, that value is always an empty
     * string, per `FilterView`.
     */
    public function isFilteredEmpty(): bool
    {
        return $this->isEmpty() && ($this->search !== '' || $this->hasActiveFilters());
    }

    private function hasActiveFilters(): bool
    {
        foreach ($this->filters as $filter) {
            if ($filter->value !== '') {
                return true;
            }
        }

        return false;
    }

    public function classes(): string
    {
        return Classes::of('grid', $this->key);
    }
}
