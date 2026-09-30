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
        /**
         * The row's edit address, with the grid's own state as the return
         * address so saving lands back on the page, filters and all.
         *
         * Null means the page declares no form, so this row draws no actions
         * cell at all; `''` means it draws one with no link in it, because
         * the row came back without a value for the entity's key and there is
         * no row to address. The distinction matters because `head.php`
         * decides the column's existence from `ListView::hasRowActions()`,
         * and a body that sometimes omitted the cell would shift every row.
         */
        public readonly ?string $editUrl = null,
    ) {
    }
}
