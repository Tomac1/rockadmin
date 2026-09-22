<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Entity;
use RockAdmin\Db\JoinType;
use RockAdmin\Db\Query;
use RockAdmin\Db\QueryBuilder;
use RockAdmin\Db\Relation;
use RockAdmin\Tests\Support\DatabaseTestCase;

#[CoversClass(QueryBuilder::class)]
final class QueryBuilderSelectTest extends DatabaseTestCase
{
    private function entity(): Entity
    {
        return new Entity('ra_test_ads', 'id', [
            'user' => new Relation('user', 'ra_test_users', 'ra_test_users.id = ra_test_ads.user_id'),
            'user.company' => new Relation(
                'user.company',
                'ra_test_companies',
                'ra_test_companies.id = ra_test_users.company_id',
                JoinType::Inner,
            ),
        ]);
    }

    #[DataProvider('connections')]
    public function testAChainedRelationAndAJsonColumnRunOnARealServer(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $sql = (new QueryBuilder($connection->dialect()))->rows(new Query($this->entity(), [
            'id' => 'id',
            'title' => 'title',
            'author' => 'user.name',
            'company' => 'user.company.name',
            'views' => 'stats->daily->views',
        ]));

        $rows = $connection->select($sql);

        $this->assertCount(3, $rows);

        $byId = array_column($rows, null, 'id');

        $views = $byId[1]['views'];

        $this->assertSame('Jana', $byId[1]['author']);
        $this->assertSame('Velo s.r.o.', $byId[1]['company']);
        $this->assertSame('42', \is_scalar($views) ? (string) $views : get_debug_type($views));
        $this->assertSame('Moto a.s.', $byId[3]['company']);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testOneJoinIsEmittedForSeveralColumnsOfTheSameRelation(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $sql = (new QueryBuilder($connection->dialect()))->rows(new Query($this->entity(), [
            'author' => 'user.name',
            'email' => 'user.email',
        ]));

        $this->assertSame(1, substr_count($sql->text, 'JOIN'));
        $this->assertCount(3, $connection->select($sql), 'a duplicated join would multiply the rows');

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testALeftJoinKeepsRowsWithoutARelatedRecord(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $connection->execute(new \RockAdmin\Db\Sql(
            'INSERT INTO ra_test_ads (id, user_id, title) VALUES (?, ?, ?)',
            [4, null, 'Orphan'],
        ));

        $sql = (new QueryBuilder($connection->dialect()))->rows(
            new Query($this->entity(), ['id' => 'id', 'author' => 'user.name']),
        );

        $this->assertCount(4, $connection->select($sql), 'the orphan survives a left join');

        $this->dropFixtures($connection);
    }
}
