<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;

/**
 * One value as it will appear in a grid cell.
 *
 * The raw value travels alongside its formatted text so a template never has
 * to reach back into the database to know what it is showing; `$percent` and
 * `$variant` are filled only when the display needs them (progress/percent
 * and badge respectively) and are `null` otherwise.
 *
 * `$type` and `$display` are both here because neither answers the question
 * alone. A display says how something is drawn and is the right key when it
 * is distinctive — a badge, a checkbox, a progress bar. But four of the seven
 * types share `Display::Plain`, so a plain cell still needs its type to know
 * whether it is money, a date, JSON or text. Both are enums, not
 * configuration: a template may read what a value *is*, never the
 * `ColumnDefinition` that declared it.
 */
final class CellView
{
    /** @param array<string, scalar|null> $attributes */
    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
        public readonly string $text,
        public readonly ColumnType $type,
        public readonly Display $display,
        public readonly string $classes,
        public readonly ?string $url,
        public readonly array $attributes,
        public readonly ?int $percent,
        public readonly ?string $variant,
    ) {
    }
}
