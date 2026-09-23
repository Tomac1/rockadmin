<?php

declare(strict_types=1);

namespace RockAdmin\Page;

use RockAdmin\Db\Collection;

/**
 * One column of a region, built from its configuration.
 *
 * `$source` always names something: either what the page wrote, or, by
 * convention, a column of the entity's own table named like the key. A
 * column carrying `$collection` never carries a meaningful `$source` — the
 * two ways of getting a value are mutually exclusive, and PageRepository
 * refuses a page that tries to declare both.
 */
final class ColumnDefinition
{
    /** @param array<string, mixed> $options currency, format, max, enum options */
    public function __construct(
        public readonly ?FilterDefinition $filter,
        public readonly ?Collection $collection,
        public readonly string $key,
        public readonly string $label,
        public readonly string $source,
        public readonly ColumnType $type,
        public readonly Display $display,
        public readonly bool $sortable,
        public readonly bool $link,
        public readonly string $align,
        public readonly ?string $width,
        public readonly string $class,
        public readonly array $options = [],
    ) {
    }
}
