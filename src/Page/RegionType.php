<?php

declare(strict_types=1);

namespace RockAdmin\Page;

/**
 * What a region does.
 *
 * A closed set, for the same reason ColumnType is one: a region typed
 * 'lsit' should fail at load, naming the alternatives, rather than render as
 * nothing later. `nav` and `stat` arrive in later milestones — adding one is
 * adding a case here, not editing a condition at every place that checks a
 * region's type.
 */
enum RegionType: string
{
    case List = 'list';
    case Preview = 'preview';
    case Form = 'form';

    /** @throws PageException when the value names no region type */
    public static function parse(string $value): self
    {
        $type = self::tryFrom($value);

        if ($type !== null) {
            return $type;
        }

        $names = array_map(static fn (self $case): string => $case->value, self::cases());
        $nearest = null;
        $distance = PHP_INT_MAX;

        foreach ($names as $name) {
            $candidate = levenshtein($value, $name);

            if ($candidate < $distance) {
                $distance = $candidate;
                $nearest = $name;
            }
        }

        // One edit away is a typo worth naming. Anything further is a guess,
        // and a confident wrong guess costs more time than the full list —
        // which is short enough to read.
        if ($nearest !== null && $distance <= 1) {
            throw new PageException("Unknown region type '{$value}'. Did you mean '{$nearest}'?");
        }

        throw new PageException(
            "Unknown region type '{$value}'. The types are: '" . implode("', '", $names) . "'.",
        );
    }
}
