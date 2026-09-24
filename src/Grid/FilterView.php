<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use RockAdmin\Config\EnumOption;

/**
 * One filter control, with the value the URL already held.
 *
 * `$value` carries exactly the shape `RockAdmin\Grid\FilterInput` would have
 * held for this column: a scalar for a simple comparison, a list for a
 * multiselect, or a `from`/`to` pair for a range. An untouched filter carries
 * an empty string, never a guess at what "no value" should look like for its
 * type — the template already knows its own type and renders emptiness for
 * it.
 */
final class FilterView
{
    /**
     * @param array<string, EnumOption>                              $options
     * @param string|list<string>|array{from?: string, to?: string} $value
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type,
        public readonly ?string $placeholder,
        public readonly array $options,
        public readonly string|array $value,
    ) {
    }
}
