<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Db\Connection;
use RockAdmin\Db\CountStrategy;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Filter;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\Query;
use RockAdmin\Db\QueryBuilder;
use RockAdmin\Tests\Support\DatabaseTestCase;

#[CoversClass(QueryBuilder::class)]
final class QueryBuilderCountTest extends DatabaseTestCase
{
    #[DataProvider('connections')]
    public function testAnExactCountMatchesTheRowsReturned(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $builder = new QueryBuilder($connection->dialect());
        $query = new Query(
            new Entity('ra_test_ads', 'id'),
            ['id' => 'id', 'title' => 'title'],
            [],
            [new Filter('title', FilterOperator::Contains, 'kolo')],
        );

        $rows = $connection->select($builder->rows($query));
        $count = $builder->count($query);

        $this->assertNotNull($count);
        $scalar = $connection->scalar($count);
        $this->assertSame(\count($rows), (int) (\is_scalar($scalar) ? $scalar : 0));
        $this->assertSame(2, (int) (\is_scalar($scalar) ? $scalar : 0));

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testACountIgnoresPagingSoItReportsTheWholeResult(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $builder = new QueryBuilder($connection->dialect());
        $query = new Query(
            new Entity('ra_test_ads', 'id'),
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            \RockAdmin\Db\Page::of(1, 1),
        );

        $this->assertCount(1, $connection->select($builder->rows($query)));

        $count = $builder->count($query);
        $this->assertNotNull($count);
        $scalar = $connection->scalar($count);
        $this->assertSame(3, (int) (\is_scalar($scalar) ? $scalar : 0));

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAnEstimateRunsOnBothServers(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $count = (new QueryBuilder($connection->dialect()))->count(new Query(
            new Entity('ra_test_ads', 'id'),
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            null,
            CountStrategy::Estimate,
        ));

        $this->assertNotNull($count);
        // Approximate by definition; this proves the statement is accepted.
        $this->assertIsNumeric($connection->scalar($count));

        $this->dropFixtures($connection);
    }
}
