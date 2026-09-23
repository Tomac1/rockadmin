<?php

declare(strict_types=1);

namespace RockAdmin\Page;

use RockAdmin\Config\Schema;
use RockAdmin\Db\Sort;

/** One independently renderable part of a page: a grid, a form, a panel. */
final class RegionDefinition
{
    /**
     * @param array<string, ColumnDefinition> $columns keyed by the column's own key
     * @param list<Sort>                      $sort
     * @param list<string>                    $searchable column keys the region's search box looks at
     */
    public function __construct(
        public readonly string $key,
        public readonly RegionType $type,
        public readonly int $perPage,
        public readonly array $columns,
        public readonly array $sort,
        public readonly array $searchable,
    ) {
    }

    public function column(string $key): ColumnDefinition
    {
        if (isset($this->columns[$key])) {
            return $this->columns[$key];
        }

        $nearest = Schema::nearestOf(array_keys($this->columns), $key);
        $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

        throw new PageException("Unknown column '{$key}' in region '{$this->key}'.{$suffix}");
    }

    public function hasColumn(string $key): bool
    {
        return isset($this->columns[$key]);
    }
}
