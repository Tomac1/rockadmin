<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Db\Entity;
use RockAdmin\Page\ColumnDefinition;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\PageException;
use RockAdmin\Page\RegionDefinition;

#[CoversClass(PageDefinition::class)]
#[CoversClass(RegionDefinition::class)]
final class PageDefinitionTest extends TestCase
{
    private function column(string $key): ColumnDefinition
    {
        return new ColumnDefinition(
            filter: null,
            collection: null,
            key: $key,
            label: ucfirst($key),
            source: $key,
            type: ColumnType::Text,
            display: Display::Plain,
            sortable: false,
            link: false,
            align: 'start',
            width: null,
            class: '',
        );
    }

    private function region(string $key, string ...$columnKeys): RegionDefinition
    {
        $columns = [];

        foreach ($columnKeys as $columnKey) {
            $columns[$columnKey] = $this->column($columnKey);
        }

        return new RegionDefinition($key, 'list', 25, $columns, [], []);
    }

    private function page(RegionDefinition ...$regions): PageDefinition
    {
        $keyed = [];

        foreach ($regions as $region) {
            $keyed[$region->key] = $region;
        }

        return new PageDefinition('ads', 'Ads', 'single', '', new Entity('ads'), $keyed);
    }

    public function testRegionReturnsTheNamedRegion(): void
    {
        $grid = $this->region('grid', 'title');
        $page = $this->page($grid);

        $this->assertSame($grid, $page->region('grid'));
    }

    public function testHasRegionAnswersWithoutThrowing(): void
    {
        $page = $this->page($this->region('grid', 'title'));

        $this->assertTrue($page->hasRegion('grid'));
        $this->assertFalse($page->hasRegion('form'));
    }

    public function testAnUnknownRegionSuggestsTheNearest(): void
    {
        $page = $this->page($this->region('grid', 'title'));

        try {
            $page->region('gridd');
            $this->fail('An unknown region should throw.');
        } catch (PageException $e) {
            $this->assertStringContainsString("Did you mean 'grid'", $e->getMessage());
        }
    }

    public function testColumnReturnsTheNamedColumn(): void
    {
        $region = $this->region('grid', 'title', 'id');

        $this->assertSame('title', $region->column('title')->key);
    }

    public function testHasColumnAnswersWithoutThrowing(): void
    {
        $region = $this->region('grid', 'title');

        $this->assertTrue($region->hasColumn('title'));
        $this->assertFalse($region->hasColumn('id'));
    }

    public function testAnUnknownColumnSuggestsTheNearest(): void
    {
        $region = $this->region('grid', 'title');

        try {
            $region->column('titel');
            $this->fail('An unknown column should throw.');
        } catch (PageException $e) {
            $this->assertStringContainsString("Did you mean 'title'", $e->getMessage());
        }
    }
}
