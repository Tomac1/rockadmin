<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\ValueType;
use RockAdmin\Page\ColumnSchema;
use RockAdmin\Page\PageSchema;

#[CoversClass(PageSchema::class)]
#[CoversClass(ColumnSchema::class)]
final class PageSchemaTest extends TestCase
{
    public function testPageSchemaDeclaresTitleLayoutEntityHeaderAndRegions(): void
    {
        $schema = PageSchema::create();

        $this->assertNotNull($schema->key('title'));
        $this->assertNotNull($schema->key('layout'));
        $this->assertNotNull($schema->key('entity'));
        $this->assertNotNull($schema->key('header'));
        $this->assertNotNull($schema->key('regions'));
    }

    public function testTitleIsARequiredString(): void
    {
        $schema = PageSchema::create();
        $key = $schema->key('title');

        $this->assertNotNull($key);
        $this->assertSame(ValueType::String, $key->type);
        $this->assertTrue($key->required);
    }

    public function testLayoutIsAStringWithDefaultSingle(): void
    {
        $schema = PageSchema::create();
        $key = $schema->key('layout');

        $this->assertNotNull($key);
        $this->assertSame(ValueType::String, $key->type);
        $this->assertSame('single', $key->default);
    }

    public function testEntityHasTableRequiredAndKeyDefaultingToId(): void
    {
        $schema = PageSchema::create();
        $key = $schema->key('entity');

        $this->assertNotNull($key);
        $this->assertSame(ValueType::Array, $key->type);
        $this->assertNotNull($key->children);

        $tableKey = $key->children->key('table');
        $this->assertNotNull($tableKey);
        $this->assertTrue($tableKey->required);

        $keyKey = $key->children->key('key');
        $this->assertNotNull($keyKey);
        $this->assertSame('id', $keyKey->default);
    }

    public function testRegionsUsesEachSoEveryRegionIsValidatedAlike(): void
    {
        $schema = PageSchema::create();
        $key = $schema->key('regions');

        $this->assertNotNull($key);
        $this->assertSame(ValueType::Array, $key->type);
        $this->assertNotNull($key->each);
    }

    public function testARegionDeclareTypePerPageSortSearchAndColumns(): void
    {
        $schema = PageSchema::create();
        $regionsKey = $schema->key('regions');

        $this->assertNotNull($regionsKey);
        $this->assertNotNull($regionsKey->each);

        $regionSchema = $regionsKey->each;
        $this->assertNotNull($regionSchema->key('type'));
        $this->assertNotNull($regionSchema->key('per_page'));
        $this->assertNotNull($regionSchema->key('sort'));
        $this->assertNotNull($regionSchema->key('search'));
        $this->assertNotNull($regionSchema->key('columns'));
    }

    public function testColumnsUsesEachWithColumnSchema(): void
    {
        $schema = PageSchema::create();
        $regionsKey = $schema->key('regions');

        $this->assertNotNull($regionsKey);
        $this->assertNotNull($regionsKey->each);

        $columnsKey = $regionsKey->each->key('columns');
        $this->assertNotNull($columnsKey);
        $this->assertSame(ValueType::Array, $columnsKey->type);
        $this->assertNotNull($columnsKey->each);
    }

    public function testEveryKeyInPageSchemaCarriesADescriptionAndExample(): void
    {
        $schema = PageSchema::create();

        foreach ($this->everyKey($schema) as $path => $key) {
            $this->assertNotSame('', $key->description, "The page schema key {$path} has no description.");

            if ($key->children === null && $key->each === null && $key->type->value !== 'bool') {
                $this->assertNotNull($key->example, "The page schema key {$path} has no example.");
            }
        }
    }

    public function testEveryKeyInColumnSchemaCarriesADescriptionAndExample(): void
    {
        $schema = ColumnSchema::create();

        foreach ($this->everyKey($schema) as $path => $key) {
            $this->assertNotSame('', $key->description, "The column schema key {$path} has no description.");

            if ($key->children === null && $key->each === null && $key->type->value !== 'bool') {
                $this->assertNotNull($key->example, "The column schema key {$path} has no example.");
            }
        }
    }

    /**
     * @return array<string, \RockAdmin\Config\SchemaKey>
     */
    private function everyKey(\RockAdmin\Config\Schema $schema, string $prefix = ''): array
    {
        $found = [];

        foreach ($schema->names() as $name) {
            $key = $schema->key($name);

            if ($key === null) {
                continue;
            }

            $path = $prefix === '' ? $name : $prefix . '.' . $name;
            $found[$path] = $key;

            foreach ([$key->children, $key->each] as $nested) {
                if ($nested !== null) {
                    $found = [...$found, ...$this->everyKey($nested, $path)];
                }
            }
        }

        return $found;
    }
}
