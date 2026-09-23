<?php

declare(strict_types=1);

namespace RockAdmin\Page;

use RockAdmin\Config\EnumOption;
use RockAdmin\Db\FilterOperator;

/**
 * What makes a column filterable.
 *
 * `$options` is populated for `select` and `multiselect` filters: either the
 * filter's own `options` or, failing that, the column's own enum options, so
 * an enum column is filterable without repeating its values.
 */
final class FilterDefinition
{
    /** @param array<string, EnumOption> $options */
    public function __construct(
        public readonly string $type,
        public readonly FilterOperator $operator,
        public readonly string $label,
        public readonly array $options = [],
        public readonly ?string $placeholder = null,
    ) {
    }
}
