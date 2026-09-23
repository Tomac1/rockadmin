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

        $limit = min($region->perPage, $this->maxPerPage);

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

        if (\is_string($input->value)) {
            return new Filter($input->column, $definition->operator, $input->value);
        }

        if (array_is_list($input->value)) {
            return new Filter($input->column, $definition->operator, $input->value);
        }

        $from = $input->value['from'] ?? null;
        $to = $input->value['to'] ?? null;

        if ($from !== null && $to !== null) {
            return new Filter($input->column, FilterOperator::Between, [$from, $to]);
        }

        if ($from !== null) {
            return new Filter($input->column, FilterOperator::GreaterOrEqual, $from);
        }

        return new Filter($input->column, FilterOperator::LessOrEqual, $to);
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
