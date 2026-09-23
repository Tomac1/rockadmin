<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use DateTimeImmutable;
use RockAdmin\Config\EnumOption;
use RockAdmin\Page\ColumnDefinition;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;
use RockAdmin\View\Classes;
use Throwable;

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

    public function format(ColumnDefinition $column, mixed $value, ?string $url = null): CellView
    {
        if ($value === null) {
            return new CellView(
                key: $column->key,
                value: null,
                text: '',
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
        $extra = [];

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

        if (!\is_string($value) && !\is_int($value)) {
            return $this->stringify($value);
        }

        // An empty or blank string, and several other odd inputs, are valid
        // *relative* time strings to DateTimeImmutable and silently resolve
        // to "now" instead of failing — which would turn a missing value
        // into a plausible-looking today. A blank value stays blank text.
        if (\is_string($value) && trim($value) === '') {
            return $value;
        }

        try {
            $date = \is_int($value)
                ? new DateTimeImmutable('@' . $value)
                : new DateTimeImmutable($value);
        } catch (Throwable) {
            // A value that cannot be parsed passes through as text rather
            // than becoming an arbitrary date: a wrong-looking string makes
            // somebody investigate, a plausible wrong date does not.
            return (string) $value;
        }

        return $date->format($format);
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
