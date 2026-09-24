<?php

declare(strict_types=1);

namespace RockAdmin\Page;

/**
 * What a form field's value is, and what control it renders.
 *
 * A closed set, for the reason ColumnType is one: a field typed `numbr`
 * should say so at load rather than quietly falling back to text. The
 * parsing lives in parse() instead of the enum's own from(), which cannot be
 * redeclared and whose \ValueError names no alternatives.
 */
enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Checkbox = 'checkbox';
    case Radio = 'radio';
    case Date = 'date';
    case Datetime = 'datetime';
    case Hidden = 'hidden';
    case Password = 'password';

    /** @throws PageException when the value names no field type */
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
            throw new PageException("Unknown field type '{$value}'. Did you mean '{$nearest}'?");
        }

        throw new PageException(
            "Unknown field type '{$value}'. The types are: '" . implode("', '", $names) . "'.",
        );
    }

    /** Whether this type is chosen from a declared set of options. */
    public function takesOptions(): bool
    {
        return match ($this) {
            self::Select, self::Multiselect, self::Radio => true,
            self::Text, self::Textarea, self::Number, self::Checkbox,
            self::Date, self::Datetime, self::Hidden, self::Password => false,
        };
    }

    /** Whether a submission may carry more than one value for this field. */
    public function isMultiple(): bool
    {
        return $this === self::Multiselect;
    }

    /** The template that renders this field's control, under `field/`. */
    public function template(): string
    {
        return 'field/' . $this->value;
    }
}
