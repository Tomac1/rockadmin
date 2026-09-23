<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use RockAdmin\Db\FilterOperator;

/**
 * One filter as read from the URL, naming a column by its region key rather
 * than the source alias `RockAdmin\Db\Filter` uses — turning this into a
 * `Filter` needs the region, so that step happens where the region is
 * available, not here.
 *
 * `$value` is a `string` for a scalar comparison, a `list<string>` for `in`,
 * or an `array{from?: string, to?: string}` for a range.
 */
final class FilterInput
{
    /** @param string|list<string>|array{from?: string, to?: string} $value */
    public function __construct(
        public readonly string $column,
        public readonly FilterOperator $operator,
        public readonly string|array $value,
    ) {
    }
}
