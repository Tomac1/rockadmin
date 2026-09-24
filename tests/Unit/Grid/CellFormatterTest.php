<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\EnumOption;
use RockAdmin\Db\Collection;
use RockAdmin\Grid\CellFormatter;
use RockAdmin\Page\ColumnDefinition;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;

#[CoversClass(CellFormatter::class)]
final class CellFormatterTest extends TestCase
{
    private CellFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new CellFormatter();
    }

    /** @param array<string, mixed> $options */
    private function column(
        string $key = 'value',
        ColumnType $type = ColumnType::Text,
        Display $display = Display::Plain,
        array $options = [],
        string $class = '',
        ?Collection $collection = null,
    ): ColumnDefinition {
        return new ColumnDefinition(
            filter: null,
            collection: $collection,
            key: $key,
            label: ucfirst($key),
            source: $key,
            type: $type,
            display: $display,
            sortable: false,
            link: false,
            align: $type->defaultAlignment(),
            width: null,
            class: $class,
            options: $options,
        );
    }

    // -- null is a first-class value ----------------------------------

    #[DataProvider('typeProvider')]
    public function testANullValueIsAnEmptyCellThatSaysSoInItsClasses(ColumnType $type, Display $display): void
    {
        $column = $this->column(type: $type, display: $display);

        $cell = $this->formatter->format($column, null);

        $this->assertSame('', $cell->text);
        $this->assertNull($cell->value);
        $this->assertStringContainsString('ra-grid-cell-empty', $cell->classes);
        $this->assertStringNotContainsStringIgnoringCase('null', $cell->text);
    }

    /** @return array<string, array{ColumnType, Display}> */
    public static function typeProvider(): array
    {
        return [
            'text' => [ColumnType::Text, Display::Plain],
            'int' => [ColumnType::Int, Display::Plain],
            'money' => [ColumnType::Money, Display::Plain],
            'datetime' => [ColumnType::Datetime, Display::Plain],
            'bool check' => [ColumnType::Bool, Display::Check],
            'bool yesno' => [ColumnType::Bool, Display::YesNo],
            'enum' => [ColumnType::Enum, Display::Badge],
            'json' => [ColumnType::Json, Display::Plain],
        ];
    }

    public function testAnEmptyArrayIsAnEmptyCellNotTheLiteralTextBracketBracket(): void
    {
        // A one-to-many column with no related rows arrives as []. Past this
        // guard it would reach formatJson(), whose honest encoding of an
        // empty array is the literal text '[]' -- correct for a value that
        // holds an item, misleading for a value that holds none: a shipped
        // grid showed it verbatim in every cell for an ad with no tags.
        //
        // It has to be a collection column. The sibling test below covers the
        // other half: an empty array in an ordinary json column is a value
        // the row holds, and hiding it would repeat the mistake this class
        // has already been corrected for twice.
        $column = $this->column(
            type: ColumnType::Json,
            display: Display::Plain,
            collection: new Collection('tags', 'ra_test_tags', 'ad_id', 'label'),
        );

        $cell = $this->formatter->format($column, []);

        $this->assertSame('', $cell->text);
        $this->assertStringNotContainsString('[]', $cell->text);
        $this->assertStringContainsString('ra-grid-cell-empty', $cell->classes);
    }

    // -- zero and false are values, not absences -----------------------

    public function testZeroIsNotTreatedAsEmpty(): void
    {
        $column = $this->column(type: ColumnType::Int, display: Display::Plain);

        foreach ([0, '0', 0.0] as $zero) {
            $cell = $this->formatter->format($column, $zero);

            $this->assertSame('0', $cell->text);
            $this->assertStringNotContainsString('ra-grid-cell-empty', $cell->classes);
        }
    }

    public function testAFalseBooleanIsNotTreatedAsEmpty(): void
    {
        $column = $this->column(type: ColumnType::Bool, display: Display::YesNo);

        $cell = $this->formatter->format($column, false);

        $this->assertSame('No', $cell->text);
        $this->assertStringNotContainsString('ra-grid-cell-empty', $cell->classes);
    }

    public function testAFalseBooleanCheckboxRendersNothingButIsNotEmpty(): void
    {
        $column = $this->column(type: ColumnType::Bool, display: Display::Check);

        $cell = $this->formatter->format($column, false);

        $this->assertSame('', $cell->text);
        $this->assertStringNotContainsString('ra-grid-cell-empty', $cell->classes);
    }

    // -- text -------------------------------------------------------------

    public function testATextValueIsUsedAsIs(): void
    {
        $column = $this->column(type: ColumnType::Text, display: Display::Plain);

        $cell = $this->formatter->format($column, 'Bicycle');

        $this->assertSame('Bicycle', $cell->text);
    }

    // -- int ----------------------------------------------------------------

    public function testAnIntIsGroupedByThousandsWithANarrowNoBreakSpace(): void
    {
        $column = $this->column(type: ColumnType::Int, display: Display::Plain);

        $cell = $this->formatter->format($column, 1234567);

        $this->assertSame("1\u{202F}234\u{202F}567", $cell->text);
    }

    public function testAProgressValueAboveItsMaximumIsClampedToFull(): void
    {
        $column = $this->column(type: ColumnType::Int, display: Display::Progress, options: ['max' => 100]);

        $cell = $this->formatter->format($column, 250);

        $this->assertSame(100, $cell->percent);
    }

    public function testANegativeProgressValueIsClampedToEmpty(): void
    {
        $column = $this->column(type: ColumnType::Int, display: Display::Progress, options: ['max' => 100]);

        $cell = $this->formatter->format($column, -10);

        $this->assertSame(0, $cell->percent);
    }

    public function testAProgressValueWithinRangeIsItsOwnPercentage(): void
    {
        $column = $this->column(type: ColumnType::Int, display: Display::Progress, options: ['max' => 200]);

        $cell = $this->formatter->format($column, 50);

        $this->assertSame(25, $cell->percent);
    }

    public function testAPercentDisplayReadsTheValueAsAPercentage(): void
    {
        $column = $this->column(type: ColumnType::Int, display: Display::Percent);

        $cell = $this->formatter->format($column, 42);

        $this->assertSame(42, $cell->percent);
        $this->assertSame('42 %', $cell->text);
    }

    // -- money ----------------------------------------------------------

    public function testAMoneyValueKeepsItsAmountAndCurrencyTogether(): void
    {
        $column = $this->column(type: ColumnType::Money, display: Display::Plain, options: ['currency' => 'EUR']);

        $cell = $this->formatter->format($column, 1234.5);

        $this->assertSame("1\u{202F}234.50\u{202F}EUR", $cell->text);
    }

    public function testAZeroMoneyValueIsShownNotEmpty(): void
    {
        $column = $this->column(type: ColumnType::Money, display: Display::Plain, options: ['currency' => 'EUR']);

        $cell = $this->formatter->format($column, 0);

        $this->assertSame("0.00\u{202F}EUR", $cell->text);
        $this->assertStringNotContainsString('ra-grid-cell-empty', $cell->classes);
    }

    // -- datetime -----------------------------------------------------------

    public function testADatetimeUsesTheColumnsFormat(): void
    {
        $column = $this->column(type: ColumnType::Datetime, display: Display::Plain, options: ['format' => 'Y-m-d']);

        $cell = $this->formatter->format($column, '2024-03-15 10:30:00');

        $this->assertSame('2024-03-15', $cell->text);
    }

    public function testADatetimeDefaultsToYmdHi(): void
    {
        $column = $this->column(type: ColumnType::Datetime, display: Display::Plain);

        $cell = $this->formatter->format($column, '2024-03-15 10:30:00');

        $this->assertSame('2024-03-15 10:30', $cell->text);
    }

    public function testADatetimeThatCannotBeParsedIsShownAsItIs(): void
    {
        $column = $this->column(type: ColumnType::Datetime, display: Display::Plain);

        $cell = $this->formatter->format($column, 'not a date');

        $this->assertSame('not a date', $cell->text);
    }

    public function testAnEmptyDatetimeStringDoesNotSilentlyBecomeNow(): void
    {
        $column = $this->column(type: ColumnType::Datetime, display: Display::Plain);

        $cell = $this->formatter->format($column, '');

        $this->assertSame('', $cell->text);
        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $this->assertStringNotContainsString($today, $cell->text);
    }

    #[DataProvider('silentlyAcceptedRelativeStringsProvider')]
    public function testARelativeTimeStringIsNotSilentlyTreatedAsADate(string $raw): void
    {
        $column = $this->column(type: ColumnType::Datetime, display: Display::Plain);

        $cell = $this->formatter->format($column, $raw);

        // A relative string genuinely parses against DateTimeImmutable's own
        // constructor, so passing it through as text is only convincing
        // proof if the result does not also happen to be today's (or
        // tomorrow's, or next week's) date rendered as text.
        $this->assertSame($raw, $cell->text);
    }

    /** @return array<string, array{string}> */
    public static function silentlyAcceptedRelativeStringsProvider(): array
    {
        return [
            'now' => ['now'],
            'tomorrow' => ['tomorrow'],
            'relative offset' => ['+1 week'],
        ];
    }

    public function testARolledOverInvalidDateIsNotSilentlyAccepted(): void
    {
        $column = $this->column(type: ColumnType::Datetime, display: Display::Plain, options: ['format' => 'Y-m-d']);

        // 30 February does not exist; DateTimeImmutable "corrects" it to
        // 2 March without throwing, which is a wrong date that looks right.
        $cell = $this->formatter->format($column, '2026-02-30');

        $this->assertSame('2026-02-30', $cell->text);
    }

    public function testADateTimeObjectIsFormattedDirectlyRatherThanEncoded(): void
    {
        $column = $this->column(type: ColumnType::Datetime, display: Display::Plain, options: ['format' => 'Y-m-d']);

        $cell = $this->formatter->format($column, new \DateTimeImmutable('2024-06-01 08:00:00'));

        $this->assertSame('2024-06-01', $cell->text);
    }

    public function testAUnixTimestampIntIsFormattedAsADatetime(): void
    {
        $column = $this->column(type: ColumnType::Datetime, display: Display::Plain, options: ['format' => 'Y-m-d']);

        $cell = $this->formatter->format($column, 1717228800); // 2024-06-01 UTC

        $this->assertSame('2024-06-01', $cell->text);
    }

    public function testAMysqlDatetimeWithMicrosecondsIsParsed(): void
    {
        $column = $this->column(type: ColumnType::Datetime, display: Display::Plain, options: ['format' => 'Y-m-d H:i:s']);

        $cell = $this->formatter->format($column, '2024-01-15 10:30:45.123456');

        $this->assertSame('2024-01-15 10:30:45', $cell->text);
    }

    public function testAPostgresTimestampWithWholeHourOffsetIsParsed(): void
    {
        $column = $this->column(type: ColumnType::Datetime, display: Display::Plain, options: ['format' => 'Y-m-d H:i:s']);

        $cell = $this->formatter->format($column, '2024-01-15 10:30:45.123456+01');

        $this->assertSame('2024-01-15 10:30:45', $cell->text);
    }

    public function testAPostgresTimestampWithHalfHourOffsetIsParsed(): void
    {
        $column = $this->column(type: ColumnType::Datetime, display: Display::Plain, options: ['format' => 'Y-m-d H:i:s']);

        $cell = $this->formatter->format($column, '2024-01-15 16:00:45.123456+05:30');

        $this->assertSame('2024-01-15 16:00:45', $cell->text);
    }

    // -- bool -----------------------------------------------------------

    #[DataProvider('truthySpellingsProvider')]
    public function testABooleanReadsEveryDatabasesSpellingOfTrue(mixed $raw): void
    {
        $column = $this->column(type: ColumnType::Bool, display: Display::YesNo);

        $cell = $this->formatter->format($column, $raw);

        $this->assertSame('Yes', $cell->text);
    }

    /** @return array<string, array{mixed}> */
    public static function truthySpellingsProvider(): array
    {
        return [
            'php true' => [true],
            'mysql int 1' => [1],
            'mysql string 1' => ['1'],
            'postgres t' => ['t'],
            'postgres true' => ['true'],
        ];
    }

    public function testABooleanCheckDisplayRendersATickForTrue(): void
    {
        $column = $this->column(type: ColumnType::Bool, display: Display::Check);

        $cell = $this->formatter->format($column, true);

        $this->assertSame('✓', $cell->text);
    }

    // -- enum -------------------------------------------------------------

    public function testAnEnumValueUsesItsOptionsLabelAndColour(): void
    {
        $column = $this->column(type: ColumnType::Enum, display: Display::Badge, options: [
            'enum' => [
                'open' => new EnumOption('open', 'Open', 'success'),
                'closed' => new EnumOption('closed', 'Closed', 'secondary'),
            ],
        ]);

        $cell = $this->formatter->format($column, 'open');

        $this->assertSame('Open', $cell->text);
        $this->assertSame('success', $cell->variant);
    }

    public function testAnEnumValueWithNoMatchingOptionKeepsItsRawValue(): void
    {
        $column = $this->column(type: ColumnType::Enum, display: Display::Badge, options: [
            'enum' => [
                'open' => new EnumOption('open', 'Open', 'success'),
            ],
        ]);

        $cell = $this->formatter->format($column, 'archived');

        $this->assertSame('archived', $cell->text);
        $this->assertNull($cell->variant);
    }

    // -- json -------------------------------------------------------------

    public function testAJsonValueIsTruncatedAndCarriesTheWholeThingInItsTitle(): void
    {
        $column = $this->column(type: ColumnType::Json, display: Display::Plain);
        $value = ['names' => ['Alice', 'Bob', 'Carol', 'Dave', 'Erin', 'Frank', 'Grace', 'Heidi', 'Ivan', 'Judy']];

        $cell = $this->formatter->format($column, $value);

        $this->assertStringEndsWith('…', $cell->text);
        $this->assertLessThan(\strlen((string) $cell->attributes['title']), \strlen($cell->text));
        $this->assertSame(json_encode($value), $cell->attributes['title']);
    }

    public function testAShortJsonValueIsNotTruncated(): void
    {
        $column = $this->column(type: ColumnType::Json, display: Display::Plain);

        $cell = $this->formatter->format($column, ['a' => 1]);

        $this->assertSame('{"a":1}', $cell->text);
        $this->assertArrayNotHasKey('title', $cell->attributes);
    }

    public function testAJsonValueAtExactlyTheLimitIsNotTruncated(): void
    {
        $column = $this->column(type: ColumnType::Json, display: Display::Plain);
        // json_encode() wraps a string in quotes, so 58 characters of payload
        // encode to exactly 60 — the truncation limit itself.
        $value = str_repeat('a', 58);
        $encoded = (string) json_encode($value);
        $this->assertSame(60, \strlen($encoded), 'fixture must sit exactly on the limit');

        $cell = $this->formatter->format($column, $value);

        $this->assertSame($encoded, $cell->text);
        $this->assertArrayNotHasKey('title', $cell->attributes);
    }

    public function testAJsonValueOneCharacterPastTheLimitIsTruncated(): void
    {
        $column = $this->column(type: ColumnType::Json, display: Display::Plain);
        $value = str_repeat('a', 59);
        $encoded = (string) json_encode($value);
        $this->assertSame(61, \strlen($encoded), 'fixture must sit one character past the limit');

        $cell = $this->formatter->format($column, $value);

        $this->assertSame(mb_substr($encoded, 0, 60) . '…', $cell->text);
        $this->assertSame($encoded, $cell->attributes['title']);
    }

    // -- classes ------------------------------------------------------------

    public function testTheCellCarriesItsColumnsIdentityClass(): void
    {
        $column = $this->column(key: 'unit_price', type: ColumnType::Text, display: Display::Plain);

        $cell = $this->formatter->format($column, 'anything');

        $this->assertStringContainsString('ra-grid-cell', $cell->classes);
        $this->assertStringContainsString('ra-grid-cell-unit-price', $cell->classes);
    }

    public function testAnEmptyCollectionReadsAsEmptyButAnEmptyJsonValueDoesNot(): void
    {
        // This class has been corrected twice for conflating an absence with
        // a value -- once for a false boolean, once for an empty string. An
        // empty array is the third case, and the distinction is the column:
        // a collection with no related rows genuinely has nothing, while a
        // json column holding [] holds something, as distinct from NULL as an
        // empty string is from a missing one.
        $collection = $this->column('tags', ColumnType::Json, collection: new Collection('tags', 'ra_test_tags', 'ad_id', 'label'));
        $json = $this->column('stats', ColumnType::Json);

        $empty = $this->formatter->format($collection, []);
        $held = $this->formatter->format($json, []);

        $this->assertNull($empty->value);
        $this->assertSame('', $empty->text);
        $this->assertStringContainsString('ra-grid-cell-empty', $empty->classes);

        $this->assertSame([], $held->value);
        $this->assertSame('[]', $held->text);
        $this->assertStringNotContainsString('ra-grid-cell-empty', $held->classes);
    }
}
