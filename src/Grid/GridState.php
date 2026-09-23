<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\Sort;
use RockAdmin\Db\SortDirection;
use RockAdmin\Page\RegionDefinition;

/**
 * One region's state, read out of its slice of the query string.
 *
 * Everything here arrives from a URL a stranger can write, so nothing is
 * trusted and nothing throws: a filter naming a column the region does not
 * declare is dropped, a sort naming an unsortable column is dropped, a page
 * number that is not a number becomes 1. A stale bookmark must show a grid,
 * not an error page.
 */
final class GridState
{
    /**
     * @param list<FilterInput> $filters
     * @param list<Sort>        $sort
     */
    public function __construct(
        public readonly string $search,
        public readonly array $filters,
        public readonly array $sort,
        public readonly int $page,
    ) {
    }

    /**
     * @param array<array-key, mixed> $query the whole query string
     */
    public static function fromQuery(array $query, string $regionKey, RegionDefinition $region): self
    {
        $namespace = self::namespaceOf($query, $regionKey);

        return new self(
            self::readSearch($namespace, $region),
            self::readFilters($namespace, $region),
            self::readSort($namespace, $region),
            self::readPage($namespace),
        );
    }

    /**
     * @param array<array-key, mixed> $query
     *
     * @return array<array-key, mixed>
     */
    private static function namespaceOf(array $query, string $regionKey): array
    {
        $namespace = $query[$regionKey] ?? null;

        return \is_array($namespace) ? $namespace : [];
    }

    /** @param array<array-key, mixed> $namespace */
    private static function readSearch(array $namespace, RegionDefinition $region): string
    {
        if ($region->searchable === []) {
            return '';
        }

        $raw = $namespace['q'] ?? null;

        if (!\is_string($raw)) {
            return '';
        }

        return trim($raw);
    }

    /**
     * @param array<array-key, mixed> $namespace
     *
     * @return list<FilterInput>
     */
    private static function readFilters(array $namespace, RegionDefinition $region): array
    {
        $raw = $namespace['f'] ?? null;

        if (!\is_array($raw)) {
            return [];
        }

        $filters = [];

        foreach ($raw as $columnKey => $value) {
            if (!\is_string($columnKey) || !$region->hasColumn($columnKey)) {
                continue;
            }

            $filter = $region->column($columnKey)->filter;

            if ($filter === null) {
                continue;
            }

            $input = self::readFilterValue($columnKey, $filter->operator, $value);

            if ($input !== null) {
                $filters[] = $input;
            }
        }

        return $filters;
    }

    private static function readFilterValue(string $columnKey, FilterOperator $operator, mixed $value): ?FilterInput
    {
        $scalar = self::asTrimmedScalar($value);

        if ($scalar !== null) {
            return $scalar === '' ? null : new FilterInput($columnKey, $operator, $scalar);
        }

        if (!\is_array($value)) {
            return null;
        }

        if (array_is_list($value)) {
            $items = [];

            foreach ($value as $item) {
                $itemScalar = self::asTrimmedScalar($item);

                if ($itemScalar !== null && $itemScalar !== '') {
                    $items[] = $itemScalar;
                }
            }

            return $items === [] ? null : new FilterInput($columnKey, FilterOperator::In, $items);
        }

        $range = [];

        foreach (['from', 'to'] as $end) {
            if (!\array_key_exists($end, $value)) {
                continue;
            }

            $endScalar = self::asTrimmedScalar($value[$end]);

            if ($endScalar !== null && $endScalar !== '') {
                $range[$end] = $endScalar;
            }
        }

        return $range === [] ? null : new FilterInput($columnKey, FilterOperator::Between, $range);
    }

    private static function asTrimmedScalar(mixed $value): ?string
    {
        if (\is_string($value) || \is_int($value) || \is_float($value)) {
            return trim((string) $value);
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $namespace
     *
     * @return list<Sort>
     */
    private static function readSort(array $namespace, RegionDefinition $region): array
    {
        $raw = $namespace['sort'] ?? null;

        if (!\is_string($raw) || trim($raw) === '') {
            return $region->sort;
        }

        $sorts = [];

        foreach (explode(',', $raw) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $direction = SortDirection::Asc;

            if (str_starts_with($part, '-')) {
                $direction = SortDirection::Desc;
                $part = substr($part, 1);
            }

            if ($part === '' || !$region->hasColumn($part) || !$region->column($part)->sortable) {
                continue;
            }

            $sorts[] = new Sort($part, $direction);
        }

        return $sorts;
    }

    /** @param array<array-key, mixed> $namespace */
    private static function readPage(array $namespace): int
    {
        $raw = $namespace['page'] ?? null;

        if (\is_int($raw)) {
            $page = $raw;
        } elseif (\is_float($raw)) {
            $page = (int) $raw;
        } elseif (\is_string($raw) && is_numeric($raw)) {
            $page = (int) $raw;
        } else {
            return 1;
        }

        return $page < 1 ? 1 : $page;
    }

    /**
     * The query parameters this state would produce, for building links.
     *
     * @return array<array-key, mixed>
     */
    public function toQuery(string $regionKey): array
    {
        $namespace = [];

        if ($this->search !== '') {
            $namespace['q'] = $this->search;
        }

        if ($this->filters !== []) {
            $f = [];

            foreach ($this->filters as $filter) {
                $f[$filter->column] = $filter->value;
            }

            $namespace['f'] = $f;
        }

        if ($this->sort !== []) {
            $namespace['sort'] = implode(',', array_map(
                static fn (Sort $sort): string => ($sort->direction === SortDirection::Desc ? '-' : '') . $sort->column,
                $this->sort,
            ));
        }

        if ($this->page > 1) {
            $namespace['page'] = $this->page;
        }

        return $namespace === [] ? [] : [$regionKey => $namespace];
    }

    public function withPage(int $page): self
    {
        return new self($this->search, $this->filters, $this->sort, max(1, $page));
    }

    /** Toggles direction when the column is already the sort, otherwise replaces it, ascending. */
    public function withSort(string $column): self
    {
        $current = $this->sort[0] ?? null;

        $direction = $current !== null && $current->column === $column
            ? ($current->direction === SortDirection::Asc ? SortDirection::Desc : SortDirection::Asc)
            : SortDirection::Asc;

        return new self($this->search, $this->filters, [new Sort($column, $direction)], $this->page);
    }

    public function isEmpty(): bool
    {
        return $this->search === '' && $this->filters === [] && $this->page === 1;
    }
}
