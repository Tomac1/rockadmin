<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Db\Collection;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Relation;
use RockAdmin\Db\Sort;
use RockAdmin\Db\SortDirection;
use RockAdmin\Db\SqlRowSource;
use RockAdmin\Grid\CellFormatter;
use RockAdmin\Grid\CellView;
use RockAdmin\Grid\GridState;
use RockAdmin\Grid\ListRegion;
use RockAdmin\Grid\QueryFactory;
use RockAdmin\Grid\RowView;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\ColumnDefinition;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\RegionDefinition;
use RockAdmin\Page\RegionType;
use RockAdmin\Tests\Support\CountingRowSource;
use RockAdmin\Tests\Support\DatabaseTestCase;

/**
 * A `ListRegion` that only ever ran against a fake `RowSource` is not proof
 * it works: this renders a real grid, with a joined column and a collection
 * column, over the fixture ads on every configured driver and reads the
 * `ListView` the way a template would.
 */
#[CoversClass(ListRegion::class)]
final class ListRegionTest extends DatabaseTestCase
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
        bool $link = false,
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
            link: $link,
            align: 'start',
            width: null,
            class: '',
        );
    }

    private function region(): RegionDefinition
    {
        $tags = new Collection('tags', 'ra_test_tags', 'ad_id', 'label');

        return new RegionDefinition(
            'grid',
            RegionType::List,
            20,
            [
                'id' => $this->column('id', link: true),
                'title' => $this->column('title'),
                'user_name' => $this->column('user_name', source: 'user.name'),
                'tags' => $this->column('tags', type: ColumnType::Json, collection: $tags),
            ],
            [new Sort('id', SortDirection::Asc)],
            [],
        );
    }

    #[DataProvider('connections')]
    public function testAGridOverTheFixtureAdsRendersItsRowsWithTheJoinAndTheCollection(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $rows = new CountingRowSource(new SqlRowSource($connection));
        $region = new ListRegion($rows, new QueryFactory(), new CellFormatter(), new UrlGenerator('/admin'));

        $view = $region->render($this->page(), $this->region(), GridState::fromQuery([], 'grid', $this->region()));

        $this->assertSame(1, $rows->calls, 'one RowSource::fetch() call for the whole page');
        $this->assertNotNull($rows->lastResult);
        $this->assertCount(
            3,
            $rows->lastResult->statements,
            'one for the rows, one for the count, one for the tags collection',
        );

        $this->assertCount(3, $view->rows, 'all three fixture ads fit on one page of 20');
        $this->assertSame(3, $view->pagination->total);
        $this->assertSame(1, $view->pagination->pageCount);

        [$first, $second, $third] = $view->rows;

        $this->assertSame(1, $first->key);
        $this->assertSame('/admin/p/ads/1', $first->url);

        $firstCells = $this->cellsByKey($first);
        $this->assertSame('Jana', $firstCells['user_name']->text, 'the joined value');
        $this->assertSame('["bazar","sleva"]', $firstCells['tags']->text, 'the one-to-many collection, fetched once for the page');

        $secondCells = $this->cellsByKey($second);
        // An ad with no tags reads as deliberately empty, not as the literal
        // text '[]' -- CellFormatter::format() now treats an empty array the
        // same way it already treats null (see CellFormatterTest).
        $this->assertSame('', $secondCells['tags']->text, 'an ad with no tags gets an empty list, not an error');
        $this->assertStringContainsString('ra-grid-cell-empty', $secondCells['tags']->classes);

        $thirdCells = $this->cellsByKey($third);
        $this->assertSame('["novinka"]', $thirdCells['tags']->text);

        $this->dropFixtures($connection);
    }

    /** @return array<string, CellView> */
    private function cellsByKey(RowView $row): array
    {
        $byKey = [];

        foreach ($row->cells as $cell) {
            $byKey[$cell->key] = $cell;
        }

        return $byKey;
    }

    #[DataProvider('connections')]
    public function testAPageBeyondTheEndOfARealResultShowsTheLastPage(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $rows = new CountingRowSource(new SqlRowSource($connection));
        $smallPage = new RegionDefinition(
            'grid',
            RegionType::List,
            2,
            ['id' => $this->column('id')],
            [new Sort('id', SortDirection::Asc)],
            [],
        );
        $region = new ListRegion($rows, new QueryFactory(), new CellFormatter(), new UrlGenerator('/admin'));

        $view = $region->render(
            $this->page(),
            $smallPage,
            GridState::fromQuery(['grid' => ['page' => '9']], 'grid', $smallPage),
        );

        $this->assertSame(2, $rows->calls, 'the first, out-of-range page is re-run once for the real last page');
        $this->assertSame(2, $view->pagination->currentPage, '3 rows at 2 per page is 2 pages');
        $this->assertCount(1, $view->rows, 'the second page holds the one remaining row');

        $this->dropFixtures($connection);
    }
}
