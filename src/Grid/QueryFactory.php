<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use RockAdmin\Db\CountStrategy;
use RockAdmin\Db\Filter;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\Page as DbPage;
use RockAdmin\Db\Query;
use RockAdmin\Db\Search;
use RockAdmin\Db\Sort;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\RegionDefinition;

/**
 * The seam between description and execution: a page, a region and a URL's
 * state go in, a `RockAdmin\Db\Query` comes out.
 *
 * Everything above this class describes a grid; everything below it runs
 * one. This is where a `RegionDefinition` and a `GridState` — both of which
 * exist only to be read, never to touch the database — turn into the one
 * object `RowSource` understands.
 */
final class QueryFactory
{
    public function __construct(
        // Capped so a region misconfigured with an enormous per_page, or a URL
        // that tried to smuggle one in, cannot turn one request into a full
        // table scan. GridState never lets per_page arrive from the URL at
        // all, so this only ever protects against the region's own config.
        private readonly int $maxPerPage = 200,
    ) {
    }

    public function build(PageDefinition $page, RegionDefinition $region, GridState $state): Query
    {
        [$columns, $collections] = $this->splitColumns($region);

        // PageRepository already refuses a per_page below 1 when a page
        // loads, loudly, naming the page and region where a person can fix
        // it. This lower bound exists for the RegionDefinition that never
        // went through that loader -- built directly in a test, or by some
        // future caller -- because Page::of() throws below 1, and a 500 is a
        // poor answer to a bad number reaching this far.
        $limit = max(1, min($region->perPage, $this->maxPerPage));

        return new Query(
            entity: $page->entity,
            columns: $columns,
            // The entity's scope is carried through untouched, including an
            // unresolved Placeholder — Query::$scope is applied unconditionally
            // by the builder, never filtered against the selected columns, so
            // it is the one condition a URL can never discard.
            scope: $page->scope,
            filters: $this->filters($region, $state),
            search: $this->search($region, $state),
            sort: $this->sortWithTiebreaker($region, $state, $page->entity->key),
            page: DbPage::of($state->page, $limit),
            count: CountStrategy::Exact,
            collections: $collections,
        );
    }

    /**
     * @return array{0: array<string, string>, 1: list<\RockAdmin\Db\Collection>}
     */
    private function splitColumns(RegionDefinition $region): array
    {
        $columns = [];
        $collections = [];

        foreach ($region->columns as $column) {
            if ($column->collection !== null) {
                $collections[] = $column->collection;

                continue;
            }

            $columns[$column->key] = $column->source;
        }

        return [$columns, $collections];
    }

    /** @return list<Filter> */
    private function filters(RegionDefinition $region, GridState $state): array
    {
        $filters = [];

        foreach ($state->filters as $input) {
            $filter = $this->toFilter($region, $input);

            if ($filter !== null) {
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    /**
     * Decides the operator from the shape of the value and the column's
     * declared FilterDefinition — the one place this mapping lives.
     *
     * A URL's shape and a column's operator do not always agree: a URL can
     * send `?f[state]=active` to a column that declared `in`, or
     * `?f[state][]=active` to one that declared `equals`. Handing either
     * straight to the builder unguarded either throws -- a 500 from a link
     * anyone can type -- or, worse, silently coerces an array to a string
     * and matches nothing while looking like it ran. Where a shape and an
     * operator cannot be honestly reconciled, the filter is dropped instead:
     * it came from a URL, and the discard rule already says unreadable input
     * is dropped rather than raised.
     */
    private function toFilter(RegionDefinition $region, FilterInput $input): ?Filter
    {
        if (!$region->hasColumn($input->column)) {
            return null;
        }

        $definition = $region->column($input->column)->filter;

        if ($definition === null) {
            return null;
        }

        $operator = $definition->operator;

        if ($operator === FilterOperator::IsNull || $operator === FilterOperator::IsNotNull) {
            // The value's presence, or lack of it, already decided which of
            // these two operators the column declares -- no shape it takes
            // afterwards changes what that means.
            return new Filter($input->column, $operator);
        }

        if (\is_array($input->value) && !array_is_list($input->value)) {
            return $this->rangeFilter($input->column, $input->value);
        }

        $values = \is_array($input->value) ? $input->value : [$input->value];

        if (\count($values) === 1) {
            return $this->scalarFilter($input->column, $operator, $values[0]);
        }

        // A list of several values has one honest reading: "any of these".
        // An operator that already means that, or means exact equality,
        // accepts it as `in`. Anything else -- a range, a text match, an
        // ordering comparison -- has no meaning for a list, and guessing one
        // would be worse than dropping the filter.
        return match ($operator) {
            FilterOperator::In, FilterOperator::Equals => new Filter($input->column, FilterOperator::In, $values),
            default => null,
        };
    }

    private function scalarFilter(string $column, FilterOperator $operator, string $value): ?Filter
    {
        return match ($operator) {
            // One value is not a range.
            FilterOperator::Between => null,
            FilterOperator::In => new Filter($column, FilterOperator::In, [$value]),
            default => new Filter($column, $operator, $value),
        };
    }

    /** @param array{from?: string, to?: string} $range */
    private function rangeFilter(string $column, array $range): ?Filter
    {
        $from = $range['from'] ?? null;
        $to = $range['to'] ?? null;

        return match (true) {
            $from !== null && $to !== null => new Filter($column, FilterOperator::Between, [$from, $to]),
            $from !== null => new Filter($column, FilterOperator::GreaterOrEqual, $from),
            $to !== null => new Filter($column, FilterOperator::LessOrEqual, $to),
            // GridState never produces a range with neither end -- it drops
            // one before a FilterInput is ever built -- but a FilterInput
            // could in principle be constructed directly, and dropping here
            // is the same answer this class gives any other unreadable shape.
            default => null,
        };
    }

    private function search(RegionDefinition $region, GridState $state): ?Search
    {
        if ($state->search === '' || $region->searchable === []) {
            return null;
        }

        $columns = [];

        foreach ($region->searchable as $key) {
            if ($region->hasColumn($key)) {
                $columns[] = $key;
            }
        }

        if ($columns === []) {
            return null;
        }

        return new Search($state->search, $columns);
    }

    /**
     * The state's sort, or the region's own when the URL carries none, always
     * with the entity key appended as a final tiebreaker.
     *
     * Two rows that share every other sorted value can otherwise swap places
     * between page one and page two of an offset-paged grid, and one of them
     * is silently never shown to anybody. The key is only appended once: a
     * sort that already names it is left alone.
     *
     * @return list<Sort>
     */
    private function sortWithTiebreaker(RegionDefinition $region, GridState $state, string $key): array
    {
        $sort = $state->sort !== [] ? $state->sort : $region->sort;

        foreach ($sort as $existing) {
            if ($existing->column === $key) {
                return $sort;
            }
        }

        return [...$sort, new Sort($key)];
    }
}
