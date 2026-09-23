<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;
use RockAdmin\Page\PageException;

#[CoversClass(ColumnType::class)]
#[CoversClass(Display::class)]
final class ColumnTypeTest extends TestCase
{
    public function testEveryTypeHasADefaultDisplayItAllows(): void
    {
        // A default the type itself rejects would be unreachable nonsense.
        foreach (ColumnType::cases() as $type) {
            $this->assertTrue(
                $type->allows($type->defaultDisplay()),
                "{$type->value} defaults to a display it does not allow.",
            );
        }
    }

    /** @return array<string, array{string, string}> */
    public static function typeDefaults(): array
    {
        return [
            'text' => ['text', 'plain'],
            'int' => ['int', 'plain'],
            'money' => ['money', 'plain'],
            'datetime' => ['datetime', 'plain'],
            'bool' => ['bool', 'check'],
            'enum' => ['enum', 'badge'],
            'json' => ['json', 'plain'],
        ];
    }

    #[DataProvider('typeDefaults')]
    public function testATypeKnowsHowItLooksWhenNobodySays(string $type, string $display): void
    {
        $this->assertSame($display, ColumnType::parse($type)->defaultDisplay()->value);
    }

    public function testABooleanMayBeACheckOrTheWordsYesAndNo(): void
    {
        $bool = ColumnType::parse('bool');

        $this->assertTrue($bool->allows(Display::Check));
        $this->assertTrue($bool->allows(Display::YesNo));
        $this->assertTrue($bool->allows(Display::Badge));
    }

    public function testAMoneyColumnCannotBeAProgressBar(): void
    {
        // The pair means nothing, so it is refused rather than rendered as
        // something the writer did not intend.
        $this->assertFalse(ColumnType::parse('money')->allows(Display::Progress));
    }

    public function testNumbersAlignToTheEndAndEverythingElseToTheStart(): void
    {
        // A ragged right edge makes a column of numbers unreadable, which is
        // the whole reason anyone puts numbers in a grid.
        $this->assertSame('end', ColumnType::parse('money')->defaultAlignment());
        $this->assertSame('end', ColumnType::parse('int')->defaultAlignment());
        $this->assertSame('start', ColumnType::parse('text')->defaultAlignment());
        $this->assertSame('start', ColumnType::parse('datetime')->defaultAlignment());
    }

    public function testAnUnknownTypeIsRefusedAndSuggestsTheNearest(): void
    {
        // 'texte' is a typo somebody will make, and the list of seven is short
        // enough that naming the nearest one is always useful.
        $this->expectException(PageException::class);
        $this->expectExceptionMessage('text');

        ColumnType::parse('texte');
    }

    public function testTheRefusalListsEveryTypeWhenNothingIsClose(): void
    {
        try {
            ColumnType::parse('quantum');
            $this->fail('An unknown type should throw.');
        } catch (PageException $e) {
            foreach (ColumnType::cases() as $type) {
                $this->assertStringContainsString($type->value, $e->getMessage());
            }
        }
    }

    public function testADisplayIsParsedAndAnUnknownOneIsRefused(): void
    {
        $this->assertSame(Display::Badge, Display::parse('badge'));

        $this->expectException(PageException::class);
        $this->expectExceptionMessage('progress');

        Display::parse('progres');
    }

    public function testEveryCaseSurvivesARoundTripThroughItsOwnValue(): void
    {
        // Both are backed enums, so a value read out of configuration and a
        // value written back into a URL or a cache are the same string.
        foreach (ColumnType::cases() as $type) {
            $this->assertSame($type, ColumnType::parse($type->value));
        }

        foreach (Display::cases() as $display) {
            $this->assertSame($display, Display::parse($display->value));
        }
    }
}
