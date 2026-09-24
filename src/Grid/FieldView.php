<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use RockAdmin\View\Classes;

/**
 * One label-and-value pair of a preview, or a wide row when the value is
 * long enough to want the full width of its own.
 *
 * `$cell` is the same `CellView` a grid cell would carry for the same
 * column — `PreviewRegion` and `ListRegion` both call `CellFormatter::format()`,
 * so a value looks identical wherever it is shown, which is the seam rule 3
 * of this project exists to protect.
 */
final class FieldView
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly CellView $cell,
        /** Long text and JSON span the full width rather than squeezing into a definition-list column. */
        public readonly bool $wide,
    ) {
    }

    public function classes(): string
    {
        return Classes::of('field', $this->key, $this->wide ? ['ra-field-wide'] : []);
    }
}
