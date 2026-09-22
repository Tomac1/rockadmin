<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Defaults;
use RockAdmin\Config\Schema;
use RockAdmin\Config\SchemaKey;
use RockAdmin\Config\ValueType;

#[CoversClass(Defaults::class)]
final class DefaultsTest extends TestCase
{
    private function schema(): Schema
    {
        return new Schema([
            'url_mode' => new SchemaKey(ValueType::String, default: 'path'),
            'brand' => new SchemaKey(ValueType::String, default: 'RockAdmin'),
            'host' => new SchemaKey(ValueType::String, nullable: true),
            'mail' => new SchemaKey(
                ValueType::Array,
                children: new Schema([
                    'driver' => new SchemaKey(ValueType::String, default: 'log'),
                    'port' => new SchemaKey(ValueType::Int, default: 587),
                ]),
            ),
            'columns' => new SchemaKey(
                ValueType::Array,
                each: new Schema([
                    'type' => new SchemaKey(ValueType::String, default: 'text'),
                    'sortable' => new SchemaKey(ValueType::Bool, default: false),
                ]),
            ),
        ]);
    }

    public function testAnAbsentKeyGetsItsDeclaredDefault(): void
    {
        $applied = (new Defaults())->apply([], $this->schema());

        $this->assertSame('path', $applied['url_mode']);
    }

    public function testAKeyTheProjectWroteIsLeftAlone(): void
    {
        $applied = (new Defaults())->apply(['url_mode' => 'query'], $this->schema());

        $this->assertSame('query', $applied['url_mode']);
    }

    public function testAnExplicitNullStaysNull(): void
    {
        $applied = (new Defaults())->apply(['brand' => null], $this->schema());

        $this->assertArrayHasKey('brand', $applied);
        $this->assertNull($applied['brand'], 'an explicit null is an instruction, not an omission');
    }

    public function testAKeyWithoutADefaultIsNotInvented(): void
    {
        $applied = (new Defaults())->apply([], $this->schema());

        $this->assertArrayNotHasKey('host', $applied);
    }

    public function testDefaultsAreFilledInsideAWrittenParent(): void
    {
        $applied = (new Defaults())->apply(['mail' => ['driver' => 'smtp']], $this->schema());

        $this->assertSame(['driver' => 'smtp', 'port' => 587], $applied['mail']);
    }

    public function testAnAbsentParentIsNotSynthesisedFromItsChildren(): void
    {
        $applied = (new Defaults())->apply([], $this->schema());

        $this->assertArrayNotHasKey('mail', $applied, 'an absent parent stays absent');
    }

    public function testEveryEntryOfAMapGetsItsDefaults(): void
    {
        $applied = (new Defaults())->apply(
            ['columns' => ['id' => ['type' => 'int'], 'title' => []]],
            $this->schema(),
        );

        $this->assertSame(
            ['id' => ['type' => 'int', 'sortable' => false], 'title' => ['type' => 'text', 'sortable' => false]],
            $applied['columns'],
        );
    }
}
