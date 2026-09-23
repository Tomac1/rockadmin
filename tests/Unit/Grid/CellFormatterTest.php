<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\EnumOption;
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
    ): ColumnDefinition {
        return new ColumnDefinition(
            filter: null,
            collection: null,
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

    // -- classes ------------------------------------------------------------

    public function testTheCellCarriesItsColumnsIdentityClass(): void
    {
        $column = $this->column(key: 'unit_price', type: ColumnType::Text, display: Display::Plain);

        $cell = $this->formatter->format($column, 'anything');

        $this->assertStringContainsString('ra-grid-cell', $cell->classes);
        $this->assertStringContainsString('ra-grid-cell-unit-price', $cell->classes);
    }
}
