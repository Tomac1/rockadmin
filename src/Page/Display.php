<?php

declare(strict_types=1);

namespace RockAdmin\Page;

/**
 * How a column's value looks.
 *
 * Separate from ColumnType because the same value has several honest
 * renderings: a boolean is a tick or the word "no", an integer is a number or
 * a progress bar. Which pairs mean anything is ColumnType::allows().
 */
enum Display: string
{
    case Plain = 'plain';
    case Badge = 'badge';
    case Check = 'check';
    case YesNo = 'yesno';
    case Progress = 'progress';
    case Percent = 'percent';

    /** @throws PageException when the value names no display */
    public static function parse(string $value): self
    {
        $display = self::tryFrom($value);

        if ($display !== null) {
            return $display;
        }

        $names = array_map(static fn (self $case): string => $case->value, self::cases());

        throw new PageException(
            "Unknown display '{$value}'. The displays are: '" . implode("', '", $names) . "'.",
        );
    }
}
