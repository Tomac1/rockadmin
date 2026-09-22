<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * A statement and the values bound into it.
 *
 * Text and bindings travel together because separating them is how a value
 * ends up concatenated into SQL "just this once".
 */
final class Sql
{
    /** @param list<mixed> $bindings */
    public function __construct(
        public readonly string $text,
        public readonly array $bindings = [],
    ) {
    }
}
