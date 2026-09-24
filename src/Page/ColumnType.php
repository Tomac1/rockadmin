<?php

declare(strict_types=1);

namespace RockAdmin\Page;

/**
 * What a column's value is.
 *
 * A closed set, because a type nobody declared is a typo rather than a
 * feature: a column typed `texte` should say so at load rather than quietly
 * rendering as something else. The parsing lives in parse() instead of the
 * enum's own from(), which cannot be redeclared and whose \ValueError names
 * no alternatives.
 *
 * What a value *looks* like is a Display, and the two are chosen separately —
 * see allows(), which says which pairs mean anything.
 */
enum ColumnType: string
{
    case Text = 'text';
    case Int = 'int';
    case Money = 'money';
    case Datetime = 'datetime';
    case Bool = 'bool';
    case Enum = 'enum';
    case Json = 'json';

    /** @throws PageException when the value names no type */
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
            throw new PageException("Unknown column type '{$value}'. Did you mean '{$nearest}'?");
        }

        throw new PageException(
            "Unknown column type '{$value}'. The types are: '" . implode("', '", $names) . "'.",
        );
    }

    /** How this type looks when the column does not say. */
    public function defaultDisplay(): Display
    {
        return match ($this) {
            self::Bool => Display::Check,
            self::Enum => Display::Badge,
            self::Text, self::Int, self::Money, self::Datetime, self::Json => Display::Plain,
        };
    }

    /**
     * Whether this type and that display mean anything together.
     *
     * A money column rendered as a progress bar is not a look, it is a
     * mistake, so the pair is refused at load rather than rendered as
     * something nobody intended.
     */
    public function allows(Display $display): bool
    {
        return match ($this) {
            self::Text => \in_array($display, [Display::Plain, Display::Badge], true),
            self::Int => \in_array(
                $display,
                [Display::Plain, Display::Progress, Display::Percent, Display::Badge],
                true,
            ),
            self::Bool => \in_array($display, [Display::Check, Display::YesNo, Display::Badge], true),
            self::Enum => \in_array($display, [Display::Badge, Display::Plain], true),
            self::Money, self::Datetime, self::Json => $display === Display::Plain,
        };
    }

    /**
     * Numbers align to the end of the cell so a column of them can be
     * compared down the page by eye, which is most of why anyone puts numbers
     * in a grid. Everything else aligns to the start.
     *
     * @return 'start'|'end'
     */
    public function defaultAlignment(): string
    {
        return match ($this) {
            self::Money, self::Int => 'end',
            self::Text, self::Datetime, self::Bool, self::Enum, self::Json => 'start',
        };
    }
}
