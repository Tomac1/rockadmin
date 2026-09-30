<?php

declare(strict_types=1);

namespace RockAdmin\Form;

use RockAdmin\Config\Placeholder;
use RockAdmin\Page\FieldDefinition;
use RockAdmin\Page\FieldType;
use RockAdmin\Page\PageException;

/**
 * Resolves a field's declared `default` into the value a new row starts
 * with.
 *
 * A default is one of four shapes, and each leaves this class differently:
 * a literal passes through untouched, a `Placeholder` passes through
 * untouched too (binding it is the data layer's business, not this one's —
 * turning it into text here is exactly what milestone 3 exists to prevent),
 * and the two tokens `@now` and `@uuid` are expanded into a value right now.
 * Anything else beginning with `@` is refused, because a silent
 * pass-through would let a typo such as `@nwo` reach the database as the
 * literal four-character string.
 */
final class DefaultValue
{
    private const string TOKEN_NOW = '@now';

    private const string TOKEN_UUID = '@uuid';

    private const string DATE_FORMAT = 'Y-m-d';

    private const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * $now is injected rather than read from the clock so a test can pin
     * what `@now` produces; null means the current time.
     */
    public static function for(FieldDefinition $field, ?\DateTimeImmutable $now = null): mixed
    {
        $default = $field->default;

        if (!\is_string($default) || !str_starts_with($default, '@')) {
            // A literal, a Placeholder, or null: nothing to expand, and a
            // Placeholder in particular must leave exactly as it arrived —
            // binding it is the data layer's business.
            return $default;
        }

        return match ($default) {
            self::TOKEN_NOW => self::now($field->type, $now ?? new \DateTimeImmutable()),
            self::TOKEN_UUID => self::uuid(),
            default => self::refuse($default),
        };
    }

    /** Whether $value is one of the recognised `@` tokens, as opposed to a literal or a Placeholder. */
    public static function isToken(mixed $value): bool
    {
        return $value === self::TOKEN_NOW || $value === self::TOKEN_UUID;
    }

    private static function now(FieldType $type, \DateTimeImmutable $now): string
    {
        // A date field gets a date with no time; every other type -- a
        // datetime field, or one with no shaping of its own -- gets the
        // full timestamp, because that is the more complete answer and
        // matches the format the database layer round-trips (see
        // CellFormatter's DATETIME_FORMATS).
        $format = $type === FieldType::Date ? self::DATE_FORMAT : self::DATETIME_FORMAT;

        return $now->format($format);
    }

    /**
     * A version 4 (random) UUID: 122 random bits plus the four version bits
     * and two variant bits RFC 4122 fixes, built from random_bytes() rather
     * than a formula that merely looks like one.
     */
    private static function uuid(): string
    {
        $bytes = random_bytes(16);

        $bytes[6] = \chr((\ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3f) | 0x80);

        $hex = bin2hex($bytes);

        return implode('-', [
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        ]);
    }

    private static function refuse(string $value): never
    {
        throw new PageException(\sprintf(
            "Unknown default token '%s'. The tokens are '%s' and '%s'.",
            $value,
            self::TOKEN_NOW,
            self::TOKEN_UUID,
        ));
    }
}
