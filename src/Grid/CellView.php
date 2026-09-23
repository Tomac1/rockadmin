<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use RockAdmin\Page\Display;

/**
 * One value as it will appear in a grid cell.
 *
 * The raw value travels alongside its formatted text so a template never has
 * to reach back into the database to know what it is showing; `$percent` and
 * `$variant` are filled only when the display needs them (progress/percent
 * and badge respectively) and are `null` otherwise.
 */
final class CellView
{
    /** @param array<string, scalar|null> $attributes */
    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
        public readonly string $text,
        public readonly Display $display,
        public readonly string $classes,
        public readonly ?string $url,
        public readonly array $attributes,
        public readonly ?int $percent,
        public readonly ?string $variant,
    ) {
    }
}
