<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

/**
 * One filter as read from the URL, naming a column by its region key rather
 * than the source alias `RockAdmin\Db\Filter` uses.
 *
 * This carries no operator. Only the shape of `$value` is genuinely what the
 * URL told us; which `FilterOperator` that shape means is `QueryFactory`'s
 * job (task 4), decided from the column's `FilterDefinition` together with
 * this shape — one place, not two, because a range with a single bound is
 * not the same comparison as one with both, and only the code that reads the
 * `FilterDefinition` can tell the difference.
 *
 * `$value` is a `string` for a scalar comparison, a `list<string>` for `in`,
 * or an `array{from?: string, to?: string}` for a range — the same shape for
 * a one-ended and a two-ended range, differing only in which keys are
 * present.
 */
final class FilterInput
{
    /** @param string|list<string>|array{from?: string, to?: string} $value */
    public function __construct(
        public readonly string $column,
        public readonly string|array $value,
    ) {
    }
}
