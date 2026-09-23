<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

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

            if ($region->column($columnKey)->filter === null) {
                continue;
            }

            $input = self::readFilterValue($columnKey, $value);

            if ($input !== null) {
                $filters[] = $input;
            }
        }

        return $filters;
    }

    private static function readFilterValue(string $columnKey, mixed $value): ?FilterInput
    {
        $scalar = self::asTrimmedScalar($value);

        if ($scalar !== null) {
            return $scalar === '' ? null : new FilterInput($columnKey, $scalar);
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

            return $items === [] ? null : new FilterInput($columnKey, $items);
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

        return $range === [] ? null : new FilterInput($columnKey, $range);
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
        $sorts = [];

        if (\is_string($raw)) {
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
        }

        // Drop what cannot be understood, then behave as though it was never
        // there: a bookmark whose sort names a since-renamed column has, once
        // filtered, carried no sort at all — the same as a URL that never
        // named one.
        return $sorts === [] ? $region->sort : $sorts;
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

    /**
     * Toggles direction when the column is already the sort, otherwise
     * replaces it, ascending.
     *
     * A third click on the same column cycles back to ascending rather than
     * clearing the sort — `SortDirection` only has two values, so that is
     * what toggling already does, with no third state to add. An unsorted
     * grid would also make paging unstable, and two clicks to get back to
     * where you started is what every other grid trains people to expect.
     */
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
