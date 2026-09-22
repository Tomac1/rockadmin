<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Schema;
use RockAdmin\Config\SchemaKey;
use RockAdmin\Config\ValueType;

#[CoversClass(Schema::class)]
#[CoversClass(SchemaKey::class)]
#[CoversClass(ValueType::class)]
final class SchemaTest extends TestCase
{
    private function schema(): Schema
    {
        return new Schema([
            'per_page' => new SchemaKey(ValueType::Int, default: 50, description: 'Rows per page.'),
            'label' => new SchemaKey(ValueType::String, description: 'Shown in the header.'),
            'sortable' => new SchemaKey(
                ValueType::Bool,
                default: false,
                description: 'Allows sorting by this column.',
                example: true,
                performance: 'Sorting an unindexed column causes a filesort.',
            ),
        ]);
    }

    public function testLooksUpAKey(): void
    {
        $key = $this->schema()->key('per_page');

        $this->assertNotNull($key);
        $this->assertSame(ValueType::Int, $key->type);
        $this->assertSame(50, $key->default);
        $this->assertFalse($key->required);
    }

    public function testAnUnknownKeyIsNull(): void
    {
        $this->assertNull($this->schema()->key('nope'));
    }

    public function testNamesAreListedInDeclarationOrder(): void
    {
        $this->assertSame(['per_page', 'label', 'sortable'], $this->schema()->names());
    }

    /** @return array<string, array{string, ?string}> */
    public static function suggestions(): array
    {
        return [
            'one letter wrong'   => ['lable', 'label'],
            'one letter missing' => ['sortabl', 'sortable'],
            'one letter extra'   => ['per_pages', 'per_page'],
            'exact match'        => ['label', 'label'],
            'nothing close'      => ['description', null],
            'far too short'      => ['x', null],
        ];
    }

    #[DataProvider('suggestions')]
    public function testNearestOnlySuggestsWhenItIsClose(string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->schema()->nearest($input));
    }

    public function testAKeyCarriesItsDocumentation(): void
    {
        $key = $this->schema()->key('sortable');

        $this->assertNotNull($key);
        $this->assertSame('Allows sorting by this column.', $key->description);
        $this->assertTrue($key->example);
        $this->assertSame('Sorting an unindexed column causes a filesort.', $key->performance);
    }

    public function testAKeyCannotDeclareBothChildrenAndEach(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SchemaKey(
            ValueType::Array,
            children: new Schema([]),
            each: new Schema([]),
        );
    }

    public function testAKeyMayDeclareEitherAlone(): void
    {
        $children = new SchemaKey(ValueType::Array, children: new Schema([]));
        $each = new SchemaKey(ValueType::Array, each: new Schema([]));

        $this->assertNotNull($children->children);
        $this->assertNull($children->each);
        $this->assertNotNull($each->each);
        $this->assertNull($each->children);
    }

    public function testNearestOnAnEmptySchemaSuggestsNothing(): void
    {
        $this->assertNull((new Schema([]))->nearest('anything'));
    }
}
