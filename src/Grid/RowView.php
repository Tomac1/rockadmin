<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

/**
 * One row of a grid, ready to render.
 *
 * `$key` is the row's own key value — whatever the entity's key column held —
 * so a template can identify the row (for selection, for `data-ra-*`
 * attributes) without reaching back into a database row array. `$url` is the
 * row's detail address, built once here rather than recomputed by every
 * template that wants it.
 */
final class RowView
{
    /** @param list<CellView> $cells in column order */
    public function __construct(
        public readonly mixed $key,
        public readonly array $cells,
        public readonly string $url,
        /** 'ra-grid-row' */
        public readonly string $classes,
    ) {
    }
}
