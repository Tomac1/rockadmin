<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Filter;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\Query;
use RockAdmin\Db\QueryBuilder;
use RockAdmin\Db\Relation;
use RockAdmin\Db\Search;
use RockAdmin\Tests\Support\DatabaseTestCase;

#[CoversClass(QueryBuilder::class)]
final class QueryBuilderWhereTest extends DatabaseTestCase
{
    private function entity(): Entity
    {
        return new Entity('ra_test_ads', 'id', [
            'user' => new Relation('user', 'ra_test_users', 'ra_test_users.id = ra_test_ads.user_id'),
        ]);
    }

    #[DataProvider('connections')]
    public function testContainsMatchesIrrespectiveOfCaseOnBothServers(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $sql = (new QueryBuilder($connection->dialect()))->rows(new Query(
            $this->entity(),
            ['id' => 'id', 'title' => 'title'],
            [],
            [new Filter('title', FilterOperator::Contains, 'KOLO')],
        ));

        $this->assertCount(2, $connection->select($sql));

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAWildcardInTheTermMatchesLiterally(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $connection->execute(new \RockAdmin\Db\Sql(
            'INSERT INTO ra_test_ads (id, user_id, title) VALUES (?, ?, ?)',
            [5, 1, 'Sleva 50% dnes'],
        ));

        $builder = new QueryBuilder($connection->dialect());

        $literal = $builder->rows(new Query(
            $this->entity(),
            ['id' => 'id', 'title' => 'title'],
            [],
            [new Filter('title', FilterOperator::Contains, '50%')],
        ));

        $this->assertCount(1, $connection->select($literal), 'the percent is a character, not a wildcard');

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAFilterThroughARelation(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $sql = (new QueryBuilder($connection->dialect()))->rows(new Query(
            $this->entity(),
            ['id' => 'id', 'author' => 'user.name'],
            [],
            [new Filter('author', FilterOperator::Equals, 'Petr')],
        ));

        $rows = $connection->select($sql);

        $this->assertCount(1, $rows);
        $this->assertSame('Petr', $rows[0]['author']);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testSearchAcrossTwoColumns(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $sql = (new QueryBuilder($connection->dialect()))->rows(new Query(
            $this->entity(),
            ['id' => 'id', 'title' => 'title', 'author' => 'user.name'],
            [],
            [],
            new Search('Petr', ['title', 'author']),
        ));

        $this->assertCount(1, $connection->select($sql), 'matched on the author, not the title');

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAnEmptyInReturnsNothing(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $sql = (new QueryBuilder($connection->dialect()))->rows(new Query(
            $this->entity(),
            ['id' => 'id'],
            [],
            [new Filter('id', FilterOperator::In, [])],
        ));

        $this->assertSame([], $connection->select($sql));

        $this->dropFixtures($connection);
    }
}
