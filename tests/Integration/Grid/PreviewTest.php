<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Db\Collection;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Relation;
use RockAdmin\Db\SqlRowSource;
use RockAdmin\Grid\CellFormatter;
use RockAdmin\Grid\PreviewRegion;
use RockAdmin\Page\ColumnDefinition;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\RegionDefinition;
use RockAdmin\Page\RegionType;
use RockAdmin\Tests\Support\CountingRowSource;
use RockAdmin\Tests\Support\DatabaseTestCase;

/**
 * A `PreviewRegion` that only ever ran against a fake `RowSource` is not
 * proof it works: this renders a real preview, with a joined column and a
 * collection column, over the fixture ads on every configured driver, and
 * checks the one thing a unit test cannot -- that it takes exactly one
 * statement to read one row, the same way `ListRegionTest` checks the grid's
 * own statement count.
 */
#[CoversClass(PreviewRegion::class)]
final class PreviewTest extends DatabaseTestCase
{
    private function entity(): Entity
    {
        return new Entity('ra_test_ads', 'id', [
            'user' => new Relation('user', 'ra_test_users', 'ra_test_users.id = ra_test_ads.user_id'),
        ]);
    }

    private function page(): PageDefinition
    {
        return new PageDefinition('ads', 'Ads', 'default', 'Ads', $this->entity(), [], []);
    }

    private function column(
        string $key,
        string $source = '',
        ColumnType $type = ColumnType::Text,
        ?Collection $collection = null,
    ): ColumnDefinition {
        return new ColumnDefinition(
            filter: null,
            collection: $collection,
            key: $key,
            label: ucfirst($key),
            source: $source === '' ? $key : $source,
            type: $type,
            display: Display::Plain,
            sortable: false,
            link: false,
            align: 'start',
            width: null,
            class: '',
        );
    }

    private function region(): RegionDefinition
    {
        return new RegionDefinition(
            'preview',
            RegionType::Preview,
            25,
            [],
            [],
            [],
            [
                $this->column('title'),
                $this->column('user_name', source: 'user.name'),
                $this->column('price', type: ColumnType::Money),
            ],
        );
    }

    #[DataProvider('connections')]
    public function testAPreviewOverTheFixtureAdsRendersItsFieldsWithTheJoin(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $rows = new CountingRowSource(new SqlRowSource($connection));
        $region = new PreviewRegion($rows, new CellFormatter());

        $view = $region->render($this->page(), $this->region(), '1');

        $this->assertNotNull($view);
        $this->assertSame('Horské kolo', $view->title, "the row's own label, its title field, not the page's");
        $this->assertSame(['title', 'user_name', 'price'], array_map(static fn ($f) => $f->key, $view->fields));

        $byKey = [];

        foreach ($view->fields as $field) {
            $byKey[$field->key] = $field;
        }

        $this->assertSame('Horské kolo', $byKey['title']->cell->text);
        $this->assertSame('Jana', $byKey['user_name']->cell->text, 'the joined value, read the same way the grid reads it');
        $this->assertNotSame('', $byKey['price']->cell->text);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAPreviewIssuesOneStatementForOneRowOverARealDatabase(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $rows = new CountingRowSource(new SqlRowSource($connection));
        $region = new PreviewRegion($rows, new CellFormatter());

        $region->render($this->page(), $this->region(), '1');

        $this->assertSame(1, $rows->calls, 'one RowSource::fetch() call for one row');
        $this->assertNotNull($rows->lastResult);
        $this->assertCount(
            1,
            $rows->lastResult->statements,
            'one statement for the row -- no count, no collection, on a region with none',
        );
        $this->assertCount(1, $rows->lastResult->rows, 'exactly one row came back');

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAPreviewWithACollectionFieldFetchesItOnceAlongsideTheRow(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $tags = new Collection('tags', 'ra_test_tags', 'ad_id', 'label');
        $region = new RegionDefinition(
            'preview',
            RegionType::Preview,
            25,
            [],
            [],
            [],
            [$this->column('title'), $this->column('tags', type: ColumnType::Json, collection: $tags)],
        );

        $rows = new CountingRowSource(new SqlRowSource($connection));
        $preview = new PreviewRegion($rows, new CellFormatter());

        $view = $preview->render($this->page(), $region, '1');

        $this->assertNotNull($view);
        $this->assertNotNull($rows->lastResult);
        $this->assertCount(2, $rows->lastResult->statements, 'one for the row, one for the tags collection');

        $byKey = [];

        foreach ($view->fields as $field) {
            $byKey[$field->key] = $field;
        }

        $this->assertSame('["bazar","sleva"]', $byKey['tags']->cell->text);
        $this->assertTrue($byKey['tags']->wide, 'a json field is always wide');

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAPreviewForAnIdThatNamesNoRowIsNull(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $rows = new CountingRowSource(new SqlRowSource($connection));
        $region = new PreviewRegion($rows, new CellFormatter());

        $view = $region->render($this->page(), $this->region(), '999999');

        $this->assertNull($view);

        $this->dropFixtures($connection);
    }
}
