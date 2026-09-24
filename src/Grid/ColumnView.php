<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

/**
 * One grid header, decided once and read many times.
 *
 * `ColumnView` exists so a template never sees a `RockAdmin\Page\ColumnDefinition`
 * — that would hand it configuration, forbidden by rule 4 of this project — and
 * never has to compute a sort link while rendering, which would force it to
 * hold a `UrlGenerator` and the grid's state. `ListRegion` decides all of this
 * once, from the region and the state, and this object only carries the
 * answer.
 */
final class ColumnView
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        /** 'ra-grid-head ra-grid-head-<key> ...' */
        public readonly string $classes,
        public readonly string $align,
        public readonly ?string $width,
        /** null when the column is not sortable. */
        public readonly ?string $sortUrl,
        /** 'ascending', 'descending', or null when this is not the current sort. */
        public readonly ?string $sortDirection,
    ) {
    }
}
