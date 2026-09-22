<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/** One condition, naming a column by the alias the page selected it under. */
final class Filter
{
    public function __construct(
        public readonly string $column,
        public readonly FilterOperator $operator,
        public readonly mixed $value = null,
    ) {
    }
}
