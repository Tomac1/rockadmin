<?php

declare(strict_types=1);

namespace RockAdmin\Page;

/**
 * A column's data type.
 *
 * Not a true PHP enum because PHP doesn't allow overriding the from() method
 * on backed enums. This class provides enum-like behavior with static
 * instances and the ability to override from() with better error messages.
 */
final class ColumnType
{
    public static self $Text;
    public static self $Int;
    public static self $Money;
    public static self $Datetime;
    public static self $Bool;
    public static self $Enum;
    public static self $Json;

    public readonly string $value;

    private static bool $initialized = false;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    private static function ensureInitialized(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$Text = new self('text');
        self::$Int = new self('int');
        self::$Money = new self('money');
        self::$Datetime = new self('datetime');
        self::$Bool = new self('bool');
        self::$Enum = new self('enum');
        self::$Json = new self('json');

        self::$initialized = true;
    }

    /** @return list<self> */
    public static function cases(): array
    {
        self::ensureInitialized();

        return [
            self::$Text,
            self::$Int,
            self::$Money,
            self::$Datetime,
            self::$Bool,
            self::$Enum,
            self::$Json,
        ];
    }

    /**
     * Parse a column type from a string value.
     *
     * Provides better error messages than a generic \ValueError: throws
     * PageException naming the nearest case, or listing all seven when nothing
     * is close.
     *
     * @throws PageException
     */
    public static function from(string $value): self
    {
        self::ensureInitialized();

        // Try to match against all cases
        return match ($value) {
            'text' => self::$Text,
            'int' => self::$Int,
            'money' => self::$Money,
            'datetime' => self::$Datetime,
            'bool' => self::$Bool,
            'enum' => self::$Enum,
            'json' => self::$Json,
            default => self::throwForUnknown($value),
        };
    }

    /**
     * @throws PageException
     */
    private static function throwForUnknown(string $value): never
    {
        $candidates = ['text', 'int', 'money', 'datetime', 'bool', 'enum', 'json'];

        // Check for typos: only suggest if the match is very close (distance <= 1)
        // Otherwise list all valid types to avoid unhelpful suggestions
        $best = null;
        $shortest = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            $distance = levenshtein($value, $candidate);
            if ($distance < $shortest) {
                $shortest = $distance;
                $best = $candidate;
            }
        }

        // Only suggest if it's a likely typo (distance of 1 means one edit)
        if ($shortest <= 1) {
            throw new PageException("Unknown column type '{$value}'. Did you mean '{$best}'?");
        }

        $list = implode("', '", $candidates);
        throw new PageException("Unknown column type '{$value}'. Valid types are: '{$list}'.");
    }

    /**
     * The default display for this type when none is specified.
     */
    public function defaultDisplay(): Display
    {
        return match ($this) {
            self::$Text => Display::Plain,
            self::$Int => Display::Plain,
            self::$Money => Display::Plain,
            self::$Datetime => Display::Plain,
            self::$Bool => Display::Check,
            self::$Enum => Display::Badge,
            self::$Json => Display::Plain,
            default => throw new \LogicException('Unexpected column type.'),
        };
    }

    /**
     * Whether this type allows the given display style.
     */
    public function allows(Display $display): bool
    {
        return match ($this) {
            self::$Text => match ($display) {
                Display::Plain, Display::Badge, Display::Link => true,
                default => false,
            },
            self::$Int => match ($display) {
                Display::Plain, Display::Progress, Display::Percent, Display::Badge, Display::Link => true,
                default => false,
            },
            self::$Money => match ($display) {
                Display::Plain => true,
                default => false,
            },
            self::$Datetime => match ($display) {
                Display::Plain => true,
                default => false,
            },
            self::$Bool => match ($display) {
                Display::Check, Display::YesNo, Display::Badge => true,
                default => false,
            },
            self::$Enum => match ($display) {
                Display::Badge, Display::Plain => true,
                default => false,
            },
            self::$Json => match ($display) {
                Display::Plain => true,
                default => false,
            },
            default => throw new \LogicException('Unexpected column type.'),
        };
    }

    /**
     * The horizontal alignment for values of this type.
     *
     * Numbers align to the end so they can be compared down the column by eye.
     *
     * @return 'start'|'end'
     */
    public function defaultAlignment(): string
    {
        return match ($this) {
            self::$Money, self::$Int => 'end',
            default => 'start',
        };
    }
}
