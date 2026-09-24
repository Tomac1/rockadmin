<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Result;
use RockAdmin\Db\Sql;
use RockAdmin\Grid\CellFormatter;
use RockAdmin\Grid\ListRegion;
use RockAdmin\Grid\PreviewRegion;
use RockAdmin\Grid\QueryFactory;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\ColumnDefinition;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\RegionDefinition;
use RockAdmin\Page\RegionType;
use RockAdmin\Tests\Support\FakeRowSource;

/**
 * Everything above `PreviewRegion` describes a preview or executes one; this
 * tests assembly — that a fixed `Result` from a fake `RowSource` turns into
 * the right `PreviewView`, never that any particular SQL ran (that is
 * `tests/Integration/Grid/PreviewTest.php`'s job).
 */
#[CoversClass(PreviewRegion::class)]
final class PreviewRegionTest extends TestCase
{
    private function page(): PageDefinition
    {
        return new PageDefinition('ads', 'Ads', 'default', 'Ads', new Entity('ra_test_ads', 'id'), [], []);
    }

    /** @param list<ColumnDefinition> $fields */
    private function region(array $fields): RegionDefinition
    {
        return new RegionDefinition('preview', RegionType::Preview, 25, [], [], [], $fields);
    }

    private function column(
        string $key,
        ColumnType $type = ColumnType::Text,
        Display $display = Display::Plain,
        string $source = '',
    ): ColumnDefinition {
        return new ColumnDefinition(
            filter: null,
            collection: null,
            key: $key,
            label: ucfirst($key),
            source: $source === '' ? $key : $source,
            type: $type,
            display: $display,
            sortable: false,
            link: false,
            align: 'start',
            width: null,
            class: '',
        );
    }

    /** @param list<array<string, mixed>> $rows */
    private function fetchResult(array $rows): Result
    {
        return new Result($rows, null, [new Sql('SELECT 1')]);
    }

    private function previewRegion(FakeRowSource $rows): PreviewRegion
    {
        return new PreviewRegion($rows, new CellFormatter());
    }

    public function testAPreviewRendersOneFieldPerColumn(): void
    {
        $region = $this->region([$this->column('title'), $this->column('price', ColumnType::Money)]);
        $rows = new FakeRowSource([$this->fetchResult([['id' => 1, 'title' => 'Horské kolo', 'price' => 12000]])]);

        $view = $this->previewRegion($rows)->render($this->page(), $region, '1');

        $this->assertNotNull($view);
        $this->assertCount(2, $view->fields);
        $this->assertSame('title', $view->fields[0]->key);
        $this->assertSame('price', $view->fields[1]->key);
    }

    public function testFieldsDefaultToTheGridsColumns(): void
    {
        // PageRepository is what actually resolves "omitted -> the grid's
        // columns" (spec 8.5); by the time a RegionDefinition reaches
        // PreviewRegion, that resolution has already happened and
        // $region->fields simply holds the result. This proves the region
        // renders whatever fields it was handed, in that order — the load
        // time behaviour is PageRepositoryTest's job.
        $fields = [$this->column('title'), $this->column('user_name', source: 'user.name')];
        $region = $this->region($fields);
        $rows = new FakeRowSource([$this->fetchResult([['id' => 1, 'title' => 'A', 'user_name' => 'Jana']])]);

        $view = $this->previewRegion($rows)->render($this->page(), $region, '1');

        $this->assertNotNull($view);
        $this->assertSame(['title', 'user_name'], array_map(static fn ($f) => $f->key, $view->fields));
    }

    public function testAnExplicitFieldListIsUsedInItsOwnOrder(): void
    {
        $region = $this->region([$this->column('price', ColumnType::Money), $this->column('title')]);
        $rows = new FakeRowSource([$this->fetchResult([['id' => 1, 'title' => 'A', 'price' => 100]])]);

        $view = $this->previewRegion($rows)->render($this->page(), $region, '1');

        $this->assertNotNull($view);
        $this->assertSame(['price', 'title'], array_map(static fn ($f) => $f->key, $view->fields));
    }

    public function testAJsonFieldIsWide(): void
    {
        $region = $this->region([$this->column('title'), $this->column('stats', ColumnType::Json)]);
        $rows = new FakeRowSource([$this->fetchResult([
            ['id' => 1, 'title' => 'A', 'stats' => '{"views":1}'],
        ])]);

        $view = $this->previewRegion($rows)->render($this->page(), $region, '1');

        $this->assertNotNull($view);
        $this->assertFalse($view->fields[0]->wide, 'a short text field is not wide');
        $this->assertTrue($view->fields[1]->wide, 'a json field is always wide');
    }

    public function testALongTextFieldIsWide(): void
    {
        $region = $this->region([$this->column('description')]);
        $rows = new FakeRowSource([$this->fetchResult([
            ['id' => 1, 'description' => str_repeat('x', 200)],
        ])]);

        $view = $this->previewRegion($rows)->render($this->page(), $region, '1');

        $this->assertNotNull($view);
        $this->assertTrue($view->fields[0]->wide);
    }

    public function testAMissingRowIsNull(): void
    {
        $region = $this->region([$this->column('title')]);
        $rows = new FakeRowSource([$this->fetchResult([])]);

        $view = $this->previewRegion($rows)->render($this->page(), $region, '999999');

        $this->assertNull($view);
    }

    public function testThePreviewIssuesOneStatementForOneRow(): void
    {
        $region = $this->region([$this->column('title')]);
        $rows = new FakeRowSource([$this->fetchResult([['id' => 1, 'title' => 'A']])]);

        $this->previewRegion($rows)->render($this->page(), $region, '1');

        $this->assertSame(1, $rows->calls, 'one RowSource::fetch() call for one row');

        $query = $rows->queries[0];
        $this->assertNotNull($query->page);
        $this->assertSame(1, $query->page->limit);
        $this->assertCount(1, $query->filters);
        $this->assertSame('id', $query->filters[0]->column);
        $this->assertSame('1', $query->filters[0]->value);
    }

    public function testThePreviewAndTheGridFormatTheSameValueIdentically(): void
    {
        // The seam: format the same column through ListRegion and through
        // PreviewRegion and assert the two CellViews are equal. Two
        // renderers over one CellFormatter is exactly the shape that
        // drifts.
        $column = $this->column('price', ColumnType::Money);
        $row = ['id' => 1, 'price' => 12345];

        $listRegion = new ListRegion(
            new FakeRowSource([new Result([$row], 1, [])]),
            new QueryFactory(),
            new CellFormatter(),
            new UrlGenerator('/admin'),
        );
        $listView = $listRegion->render(
            $this->page(),
            new RegionDefinition('grid', RegionType::List, 25, ['price' => $column], [], []),
            \RockAdmin\Grid\GridState::fromQuery([], 'grid', new RegionDefinition('grid', RegionType::List, 25, ['price' => $column], [], [])),
        );

        $previewRows = new FakeRowSource([$this->fetchResult([$row])]);
        $previewView = $this->previewRegion($previewRows)->render($this->page(), $this->region([$column]), '1');

        $this->assertNotNull($previewView);
        $gridCell = $listView->rows[0]->cells[0];
        $previewCell = $previewView->fields[0]->cell;

        $this->assertEquals($gridCell, $previewCell);
    }
}
