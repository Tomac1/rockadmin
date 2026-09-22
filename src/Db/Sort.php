<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/** One ordering, naming a column by the alias the page selected it under. */
final class Sort
{
    public function __construct(
        public readonly string $column,
        public readonly SortDirection $direction = SortDirection::Asc,
    ) {
    }
}
