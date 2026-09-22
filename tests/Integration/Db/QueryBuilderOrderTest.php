<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Page;
use RockAdmin\Db\Query;
use RockAdmin\Db\QueryBuilder;
use RockAdmin\Db\Relation;
use RockAdmin\Db\Sort;
use RockAdmin\Db\SortDirection;
use RockAdmin\Tests\Support\DatabaseTestCase;

#[CoversClass(QueryBuilder::class)]
final class QueryBuilderOrderTest extends DatabaseTestCase
{
    #[DataProvider('connections')]
    public function testSortingAndPagingWalkTheWholeTableExactlyOnce(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $builder = new QueryBuilder($connection->dialect());
        $entity = new Entity('ra_test_ads', 'id');
        $seen = [];

        foreach ([1, 2] as $number) {
            $sql = $builder->rows(new Query(
                $entity,
                ['id' => 'id'],
                [],
                [],
                null,
                [new Sort('id', SortDirection::Asc)],
                Page::of($number, 2),
            ));

            foreach ($connection->select($sql) as $row) {
                $id = $row['id'];
                $seen[] = (int) (\is_scalar($id) ? $id : '0');
            }
        }

        $this->assertSame([1, 2, 3], $seen, 'every row once, in order');

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testKeysetPagingWalksBackwardsFromTheCursor(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $builder = new QueryBuilder($connection->dialect());
        $entity = new Entity('ra_test_ads', 'id');

        $first = $connection->select($builder->rows(new Query(
            $entity,
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            Page::after(null, 2),
        )));

        $firstIds = array_map(static function (array $r): int {
            $id = $r['id'];
            return (int) (\is_scalar($id) ? $id : '0');
        }, $first);
        $this->assertSame([3, 2], $firstIds);

        $secondCursor = $first[1]['id'];
        $secondCursorInt = (int) (\is_scalar($secondCursor) ? $secondCursor : '0');

        $second = $connection->select($builder->rows(new Query(
            $entity,
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            Page::after($secondCursorInt, 2),
        )));

        $secondIds = array_map(static function (array $r): int {
            $id = $r['id'];
            return (int) (\is_scalar($id) ? $id : '0');
        }, $second);
        $this->assertSame([1], $secondIds);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testSortingThroughARelation(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $entity = new Entity('ra_test_ads', 'id', [
            'user' => new Relation(
                'user',
                'ra_test_users',
                'ra_test_users.id = ra_test_ads.user_id',
            ),
        ]);

        $sql = (new QueryBuilder($connection->dialect()))->rows(new Query(
            $entity,
            ['id' => 'id', 'author' => 'user.name'],
            [],
            [],
            null,
            [new Sort('author', SortDirection::Desc), new Sort('id', SortDirection::Asc)],
        ));

        $authors = array_column($connection->select($sql), 'author');

        $this->assertSame(['Petr', 'Jana', 'Jana'], $authors);

        $this->dropFixtures($connection);
    }
}
