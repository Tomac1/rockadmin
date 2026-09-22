<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/** One term looked for across several columns at once. */
final class Search
{
    /** @param list<string> $columns aliases to look in */
    public function __construct(
        public readonly string $term,
        public readonly array $columns,
    ) {
    }
}
