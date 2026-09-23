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
        $this->assertSame($display, ColumnType::from($type)->defaultDisplay()->value);
    }

    public function testABooleanMayBeACheckOrTheWordsYesAndNo(): void
    {
        $bool = ColumnType::from('bool');

        $this->assertTrue($bool->allows(Display::Check));
        $this->assertTrue($bool->allows(Display::YesNo));
        $this->assertTrue($bool->allows(Display::Badge));
    }

    public function testAMoneyColumnCannotBeAProgressBar(): void
    {
        // The pair means nothing, so it is refused rather than rendered as
        // something the writer did not intend.
        $this->assertFalse(ColumnType::from('money')->allows(Display::Progress));
    }

    public function testNumbersAlignToTheEndAndEverythingElseToTheStart(): void
    {
        // A ragged right edge makes a column of numbers unreadable, which is
        // the whole reason anyone puts numbers in a grid.
        $this->assertSame('end', ColumnType::from('money')->defaultAlignment());
        $this->assertSame('end', ColumnType::from('int')->defaultAlignment());
        $this->assertSame('start', ColumnType::from('text')->defaultAlignment());
        $this->assertSame('start', ColumnType::from('datetime')->defaultAlignment());
    }

    public function testAnUnknownTypeIsRefusedAndSuggestsTheNearest(): void
    {
        // 'texte' is a typo somebody will make, and the list of seven is short
        // enough that naming the nearest one is always useful.
        $this->expectException(PageException::class);
        $this->expectExceptionMessage('text');

        ColumnType::from('texte');
    }

    public function testTheRefusalListsEveryTypeWhenNothingIsClose(): void
    {
        try {
            ColumnType::from('quantum');
            $this->fail('An unknown type should throw.');
        } catch (PageException $e) {
            foreach (ColumnType::cases() as $type) {
                $this->assertStringContainsString($type->value, $e->getMessage());
            }
        }
    }
}
