<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Db\Collection;
use RockAdmin\Db\Connection;
use RockAdmin\Db\CountStrategy;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Page;
use RockAdmin\Db\Query;
use RockAdmin\Db\Result;
use RockAdmin\Db\RowSource;
use RockAdmin\Db\Sort;
use RockAdmin\Db\SortDirection;
use RockAdmin\Db\SqlRowSource;
use RockAdmin\Tests\Support\DatabaseTestCase;

#[CoversClass(SqlRowSource::class)]
#[CoversClass(Result::class)]
#[CoversClass(Collection::class)]
final class SqlRowSourceTest extends DatabaseTestCase
{
    #[DataProvider('connections')]
    public function testItReturnsRowsAndATotal(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $result = (new SqlRowSource($connection))->fetch(new Query(
            new Entity('ra_test_ads', 'id'),
            ['id' => 'id', 'title' => 'title'],
            [],
            [],
            null,
            [new Sort('id', SortDirection::Asc)],
            Page::of(1, 2),
        ));

        $this->assertInstanceOf(Result::class, $result);
        $this->assertCount(2, $result->rows, 'the page holds two');
        $this->assertSame(3, $result->total, 'the total counts them all');
        $this->assertCount(2, $result->statements, 'one for the rows, one for the count');

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testNoCountMeansNoTotalAndNoSecondStatement(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $result = (new SqlRowSource($connection))->fetch(new Query(
            new Entity('ra_test_ads', 'id'),
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            null,
            CountStrategy::None,
        ));

        $this->assertNull($result->total);
        $this->assertCount(1, $result->statements);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAOneToManyCostsExactlyOneExtraQueryForTheWholePage(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $result = (new SqlRowSource($connection))->fetch(new Query(
            new Entity('ra_test_ads', 'id'),
            ['id' => 'id', 'title' => 'title'],
            [],
            [],
            null,
            [new Sort('id', SortDirection::Asc)],
            null,
            CountStrategy::None,
            [new Collection('tags', 'ra_test_tags', 'ad_id', 'label')],
        ));

        $this->assertCount(
            2,
            $result->statements,
            'one for the rows and one for every tag on the page — never one per row',
        );

        $byId = array_column($result->rows, null, 'id');

        $this->assertSame(['bazar', 'sleva'], $byId[1]['tags']);
        $this->assertSame([], $byId[2]['tags'], 'a row with no children gets an empty list');
        $this->assertSame(['novinka'], $byId[3]['tags']);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAnEmptyPageSkipsTheSupplementaryQueryAltogether(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $result = (new SqlRowSource($connection))->fetch(new Query(
            new Entity('ra_test_ads', 'id'),
            ['id' => 'id'],
            ['id' => -1],
            [],
            null,
            [],
            null,
            CountStrategy::None,
            [new Collection('tags', 'ra_test_tags', 'ad_id', 'label')],
        ));

        $this->assertSame([], $result->rows);
        $this->assertCount(1, $result->statements, 'nothing to fetch children for');

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testItIsARowSource(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->assertInstanceOf(RowSource::class, new SqlRowSource($connection));
    }
}
