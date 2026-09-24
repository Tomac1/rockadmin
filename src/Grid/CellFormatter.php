<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use DateTimeImmutable;
use DateTimeInterface;
use RockAdmin\Config\EnumOption;
use RockAdmin\Page\ColumnDefinition;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;
use RockAdmin\View\Classes;

/**
 * Turns a raw database value into a `CellView`: what it says, how it is
 * classed, and the few extra facts a display needs to render it.
 *
 * Every type shares one rule ahead of its own: `null` always renders as an
 * empty cell carrying `ra-grid-cell-empty`, because a column that is mostly
 * null must look deliberately empty rather than broken. Past that, nothing
 * here treats a falsy value as an absent one — `empty()` and a bare `!$value`
 * both swallow `0`, `'0'` and `false`, and a price of zero, a count of zero
 * and a false flag are all things a grid has to show honestly.
 */
final class CellFormatter
{
    /** A narrow no-break space groups thousands the Czech and general European way. */
    private const string THOUSANDS_SEPARATOR = "\u{202F}";

    private const string DEFAULT_DATETIME_FORMAT = 'Y-m-d H:i';

    private const float DEFAULT_PROGRESS_MAX = 100.0;

    private const int JSON_MAX_LENGTH = 60;

    private const string CHECK_MARK = '✓';

    /**
     * Values a boolean column spells "true" across the databases this SDK
     * supports: MySQL's TINYINT gives `1` (int) or `'1'` (string, depending
     * on the driver's fetch mode), PostgreSQL's native boolean gives `true`
     * or the string `'t'` depending on the driver, and a value built by the
     * host application may simply be the PHP boolean `true`. Anything not in
     * this list — including `0`, `'0'`, `false`, `'f'`, `'false'` — is false.
     */
    private const array TRUE_VALUES = [true, 1, '1', 't', 'true'];

    /**
     * The shapes MySQL and PostgreSQL actually hand back through PDO for a
     * DATE, DATETIME/TIMESTAMP and TIMESTAMPTZ column, checked by hand
     * against both rather than assumed:
     *
     * - MySQL DATE:                  '2024-01-15'
     * - MySQL DATETIME/TIMESTAMP:    '2024-01-15 10:30:45' (no fractional
     *   seconds unless the column declares one, e.g. DATETIME(6))
     * - MySQL DATETIME(6)/TIMESTAMP(6): '2024-01-15 10:30:45.123456'
     * - PostgreSQL DATE:             '2024-01-15'
     * - PostgreSQL TIMESTAMP:        '2024-01-15 10:30:45.123456' (fractional
     *   part present whenever non-zero)
     * - PostgreSQL TIMESTAMPTZ:      '2024-01-15 09:30:45.123456+01' (a bare
     *   two-digit offset when the offset is a whole hour) or
     *   '2024-01-15 16:00:45.123456+05:30' (with minutes otherwise) — PDO
     *   converts to the session time zone, so this is the connection's own
     *   offset rather than one the developer chose.
     *
     * `parseDatetime()` normalises the whole-hour offset to `+01:00` before
     * matching against these, so one `P`-suffixed format covers both.
     *
     * ISO 8601's `T` separator is included even though neither database
     * produces it, because a host application building its own RowSource is
     * free to hand back an ISO string instead of a raw driver row.
     */
    private const array DATETIME_FORMATS = [
        'Y-m-d\TH:i:s.uP',
        'Y-m-d\TH:i:sP',
        'Y-m-d H:i:s.uP',
        'Y-m-d H:i:sP',
        'Y-m-d\TH:i:s.u',
        'Y-m-d\TH:i:s',
        'Y-m-d H:i:s.u',
        'Y-m-d H:i:s',
        'Y-m-d',
    ];

    public function format(ColumnDefinition $column, mixed $value, ?string $url = null): CellView
    {
        // An empty collection -- a one-to-many column with no related rows --
        // reads as deliberately empty for the same reason null does, rather
        // than as the literal text '[]'.
        //
        // The collection check is what makes that honest. An empty array in a
        // *json column* is a value the row really holds, as distinct from NULL
        // as an empty string is from a missing one, and this class has just
        // been corrected twice for conflating the two -- once for a false
        // boolean, once for an empty string. A collection is the one case
        // where an empty array means there is nothing rather than that
        // nothing is there.
        if ($value === null || ($value === [] && $column->collection !== null)) {
            return new CellView(
                key: $column->key,
                value: null,
                text: '',
                type: $column->type,
                display: $column->display,
                classes: $this->classes($column, true),
                url: $url,
                attributes: [],
                percent: null,
                variant: null,
            );
        }

        [$text, $percent, $variant, $attributes] = match ($column->type) {
            ColumnType::Text => [$this->stringify($value), null, null, []],
            ColumnType::Int => $this->formatInt($column, $value),
            ColumnType::Money => [$this->formatMoney($column, $value), null, null, []],
            ColumnType::Datetime => [$this->formatDatetime($column, $value), null, null, []],
            ColumnType::Bool => $this->formatBool($column, $value),
            ColumnType::Enum => $this->formatEnum($column, $value),
            ColumnType::Json => $this->formatJson($value),
        };

        return new CellView(
            key: $column->key,
            value: $value,
            text: $text,
            type: $column->type,
            display: $column->display,
            classes: $this->classes($column, false),
            url: $url,
            attributes: $attributes,
            percent: $percent,
            variant: $variant,
        );
    }

    private function classes(ColumnDefinition $column, bool $isEmpty): string
    {
        $extra = ['text-' . $column->align];

        if ($column->class !== '') {
            $extra[] = $column->class;
        }

        if ($isEmpty) {
            $extra[] = 'ra-grid-cell-empty';
        }

        return Classes::of('grid-cell', $column->key, $extra);
    }

    private function stringify(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '' : $encoded;
    }

    /** @return array{string, ?int, ?string, array<string, scalar|null>} */
    private function formatInt(ColumnDefinition $column, mixed $value): array
    {
        $number = is_numeric($value) ? (float) $value : 0.0;

        return match ($column->display) {
            Display::Progress => $this->formatProgress($column, $number),
            Display::Percent => $this->formatPercent($number),
            default => [$this->groupThousands($number), null, null, []],
        };
    }

    /** @return array{string, int, null, array<string, scalar|null>} */
    private function formatProgress(ColumnDefinition $column, float $value): array
    {
        $max = is_numeric($column->options['max'] ?? null)
            ? (float) $column->options['max']
            : self::DEFAULT_PROGRESS_MAX;

        $ratio = $max > 0.0 ? $value / $max : 0.0;
        $percent = (int) round(max(0.0, min(1.0, $ratio)) * 100);

        return [$this->groupThousands($value), $percent, null, []];
    }

    /** @return array{string, int, null, array<string, scalar|null>} */
    private function formatPercent(float $value): array
    {
        $percent = (int) round(max(0.0, min(100.0, $value)));

        return ["{$percent} %", $percent, null, []];
    }

    private function groupThousands(float $value): string
    {
        return number_format($value, 0, '.', self::THOUSANDS_SEPARATOR);
    }

    private function formatMoney(ColumnDefinition $column, mixed $value): string
    {
        $amount = is_numeric($value) ? (float) $value : 0.0;
        $formatted = number_format($amount, 2, '.', self::THOUSANDS_SEPARATOR);

        $currency = $column->options['currency'] ?? null;

        if (!\is_string($currency) || $currency === '') {
            return $formatted;
        }

        // A narrow no-break space also joins the amount and the currency, so
        // they cannot be split across a line break the way an ordinary space
        // could — the amount and its currency are one fact, not two.
        return $formatted . self::THOUSANDS_SEPARATOR . $currency;
    }

    private function formatDatetime(ColumnDefinition $column, mixed $value): string
    {
        $format = \is_string($column->options['format'] ?? null)
            ? $column->options['format']
            : self::DEFAULT_DATETIME_FORMAT;

        // A driver or a hand-built RowSource may already hand back a date
        // object; format it directly rather than falling through to json_encode().
        if ($value instanceof DateTimeInterface) {
            return $value->format($format);
        }

        // An int in a datetime column is read as a Unix timestamp. The
        // column's own type is what supplies the "this is a datetime"
        // context here — a database driver never returns a bare integer
        // for a date/time column on its own, so an int reaching this point
        // was put there deliberately, by a RowSource or a computed column
        // such as UNIX_TIMESTAMP(), and a Unix timestamp is the one
        // unambiguous way to encode a datetime as an integer.
        if (\is_int($value)) {
            return (new DateTimeImmutable('@' . $value))->format($format);
        }

        if (!\is_string($value)) {
            return $this->stringify($value);
        }

        $date = $this->parseDatetime($value);

        // A value that does not genuinely look like a date — including one
        // that merely parses, such as 'now', 'tomorrow' or a rolled-over
        // '2026-02-30' — passes through as text rather than becoming a
        // plausible wrong date: an odd string makes somebody investigate,
        // a wrong-looking-right date does not.
        return $date === null ? $value : $date->format($format);
    }

    /**
     * Accepts a value only when it matches one of the shapes MySQL or
     * PostgreSQL actually produce (see DATETIME_FORMATS), and only when
     * DateTimeImmutable parsed every field of that shape without a warning.
     *
     * createFromFormat() is deliberately used in place of the ordinary
     * constructor: the constructor accepts anything strtotime() accepts,
     * including relative phrases like 'now' or '+1 week' and an
     * out-of-range date like '2026-02-30', which it silently rolls over to
     * 2 March instead of rejecting. createFromFormat() against an exact
     * format rejects the relative phrases outright (they simply do not
     * match 'Y-m-d H:i:s'), and getLastErrors() exposes the rollover as a
     * warning even though the call itself still "succeeds".
     */
    private function parseDatetime(string $value): ?DateTimeImmutable
    {
        // PostgreSQL's timestamptz gives a bare two-digit offset ('+01')
        // when the offset is a whole hour, and only adds the minutes
        // ('+05:30') otherwise. PHP's 'P' format code requires the colon
        // and minutes in every case, so a whole-hour offset is normalised
        // to match before parsing. The pattern only fires right after a
        // two-digit seconds field (optionally with a fractional part), so
        // it cannot mistake the '-15' at the end of a plain '2024-01-15'
        // date for a timezone offset.
        $normalized = preg_replace('/(:\d{2}(?:\.\d+)?)([+-]\d{2})$/', '$1$2:00', $value) ?? $value;

        foreach (self::DATETIME_FORMATS as $format) {
            // The '!' prefix resets every field DATETIME_FORMATS does not
            // mention to the Unix epoch instead of the current moment, so a
            // date-only value such as '2024-01-15' becomes midnight on that
            // day rather than "today, but at whatever time it is now".
            $date = DateTimeImmutable::createFromFormat('!' . $format, $normalized);

            if ($date === false) {
                continue;
            }

            $errors = DateTimeImmutable::getLastErrors();

            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }

            return $date;
        }

        return null;
    }

    /** @return array{string, null, ?string, array<string, scalar|null>} */
    private function formatBool(ColumnDefinition $column, mixed $value): array
    {
        $truthy = \in_array($value, self::TRUE_VALUES, true);

        return match ($column->display) {
            Display::Check => [$truthy ? self::CHECK_MARK : '', null, null, []],
            Display::Badge => [$truthy ? 'Yes' : 'No', null, $truthy ? 'success' : 'secondary', []],
            default => [$truthy ? 'Yes' : 'No', null, null, []],
        };
    }

    /** @return array{string, null, ?string, array<string, scalar|null>} */
    private function formatEnum(ColumnDefinition $column, mixed $value): array
    {
        /** @var array<string, EnumOption> $options */
        $options = \is_array($column->options['enum'] ?? null) ? $column->options['enum'] : [];
        $key = $this->stringify($value);
        $option = $options[$key] ?? null;

        if ($option instanceof EnumOption) {
            return [$option->label, null, $option->color, []];
        }

        // A value with no matching option keeps its raw value and gets no
        // variant, so an unmapped state is visible in the grid rather than
        // silently disappearing.
        return [$key, null, null, []];
    }

    /** @return array{string, null, null, array<string, scalar|null>} */
    private function formatJson(mixed $value): array
    {
        $data = $value;

        if (\is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            }
        }

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $encoded = $encoded === false ? '' : $encoded;

        if (mb_strlen($encoded) <= self::JSON_MAX_LENGTH) {
            return [$encoded, null, null, []];
        }

        $truncated = mb_substr($encoded, 0, self::JSON_MAX_LENGTH) . '…';

        return [$truncated, null, null, ['title' => $encoded]];
    }
}
