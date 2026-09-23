<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Db\Collection;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Entity;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\Relation;
use RockAdmin\Db\Sort;
use RockAdmin\Db\SortDirection;
use RockAdmin\Db\SqlRowSource;
use RockAdmin\Grid\GridState;
use RockAdmin\Grid\QueryFactory;
use RockAdmin\Page\ColumnDefinition;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;
use RockAdmin\Page\FilterDefinition;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\RegionDefinition;
use RockAdmin\Page\RegionType;
use RockAdmin\Tests\Support\DatabaseTestCase;

/**
 * A Query that looks right and never executes is worth nothing: these run
 * the QueryFactory's output through SqlRowSource against the fixture tables
 * on every configured driver.
 */
#[CoversClass(QueryFactory::class)]
final class QueryFactoryTest extends DatabaseTestCase
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
        bool $sortable = false,
        ?FilterDefinition $filter = null,
        ?Collection $collection = null,
    ): ColumnDefinition {
        return new ColumnDefinition(
            filter: $filter,
            collection: $collection,
            key: $key,
            label: ucfirst($key),
            source: $source === '' ? $key : $source,
            type: ColumnType::Text,
            display: Display::Plain,
            sortable: $sortable,
            link: false,
            align: 'start',
            width: null,
            class: '',
        );
    }

    /**
     * @param array<string, ColumnDefinition> $columns
     * @param list<string>                    $searchable
     */
    private function region(array $columns, array $searchable = []): RegionDefinition
    {
        return new RegionDefinition(
            'grid',
            RegionType::List,
            2,
            $columns,
            [new Sort('id', SortDirection::Asc)],
            $searchable,
        );
    }

    #[DataProvider('connections')]
    public function testAGridOverTheFixtureAdsReturnsItsRows(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $region = $this->region(['id' => $this->column('id'), 'title' => $this->column('title')]);
        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $result = (new SqlRowSource($connection))->fetch($query);

        $this->assertCount(2, $result->rows, 'per_page is 2');
        $this->assertSame(3, $result->total);
        $this->assertSame(['id' => 1, 'title' => 'Horské kolo'], $result->rows[0]);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAJoinedColumnReturnsTheRelatedValue(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $region = $this->region([
            'id' => $this->column('id'),
            'user_name' => $this->column('user_name', source: 'user.name'),
        ]);
        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $result = (new SqlRowSource($connection))->fetch($query);

        $this->assertSame('Jana', $result->rows[0]['user_name']);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAJsonSourcePathReturnsTheNestedValue(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $region = $this->region([
            'id' => $this->column('id'),
            'views' => $this->column('views', source: 'stats->daily->views'),
        ]);
        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $result = (new SqlRowSource($connection))->fetch($query);

        $views = $result->rows[0]['views'];
        $this->assertIsScalar($views);
        $this->assertSame(42, (int) $views);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAFilterNarrowsTheRows(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $region = $this->region([
            'id' => $this->column('id'),
            'state' => $this->column('state', filter: new FilterDefinition('select', FilterOperator::Equals, 'State')),
        ]);
        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['state' => 'active']]], 'grid', $region),
        );

        $result = (new SqlRowSource($connection))->fetch($query);

        $this->assertSame(2, $result->total, 'only the two active ads');

        foreach ($result->rows as $row) {
            $this->assertSame('active', $row['state']);
        }

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testSearchMatchesAcrossSearchableColumns(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $region = $this->region(
            ['id' => $this->column('id'), 'title' => $this->column('title')],
            searchable: ['title'],
        );
        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['q' => 'Skútr']], 'grid', $region),
        );

        $result = (new SqlRowSource($connection))->fetch($query);

        $this->assertSame(1, $result->total);
        $this->assertSame('Skútr', $result->rows[0]['title']);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testSortingReversesTheOrder(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $region = $this->region(['id' => $this->column('id', sortable: true)]);
        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['sort' => '-id']], 'grid', $region),
        );

        $result = (new SqlRowSource($connection))->fetch($query);

        $this->assertSame([3, 2], array_column($result->rows, 'id'));

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testTheSecondPageContinuesWhereTheFirstStopped(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $region = $this->region(['id' => $this->column('id')]);

        $firstPage = (new SqlRowSource($connection))->fetch((new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery([], 'grid', $region),
        ));
        $secondPage = (new SqlRowSource($connection))->fetch((new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['page' => '2']], 'grid', $region),
        ));

        $this->assertSame([1, 2], array_column($firstPage->rows, 'id'));
        $this->assertSame([3], array_column($secondPage->rows, 'id'));

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAGridWithThreeJoinedColumnsIssuesTwoStatements(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $entity = new Entity('ra_test_ads', 'id', [
            'user' => new Relation('user', 'ra_test_users', 'ra_test_users.id = ra_test_ads.user_id'),
            'user.company' => new Relation(
                'user.company',
                'ra_test_companies',
                'ra_test_companies.id = ra_test_users.company_id',
            ),
        ]);
        $page = new PageDefinition('ads', 'Ads', 'default', 'Ads', $entity, [], []);

        $region = $this->region([
            'id' => $this->column('id'),
            'user_name' => $this->column('user_name', source: 'user.name'),
            'user_email' => $this->column('user_email', source: 'user.email'),
            'company_name' => $this->column('company_name', source: 'user.company.name'),
        ]);
        $query = (new QueryFactory())->build(
            $page,
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $result = (new SqlRowSource($connection))->fetch($query);

        $this->assertCount(
            2,
            $result->statements,
            'one for the rows, one for the count — however many relations the columns cross',
        );

        $this->dropFixtures($connection);
    }
}
