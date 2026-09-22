<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Db\CountStrategy;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Filter;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\MySqlDialect;
use RockAdmin\Db\Page;
use RockAdmin\Db\Query;
use RockAdmin\Db\QueryBuilder;
use RockAdmin\Db\Relation;
use RockAdmin\Db\Search;
use RockAdmin\Db\Sort;
use RockAdmin\Db\SortDirection;

#[CoversClass(QueryBuilder::class)]
#[CoversClass(CountStrategy::class)]
final class QueryBuilderCountTest extends TestCase
{
    private function entity(): Entity
    {
        return new Entity('ads', 'id', [
            'user' => new Relation('user', 'users', 'users.id = ads.user_id'),
        ]);
    }

    private function builder(): QueryBuilder
    {
        return new QueryBuilder(new MySqlDialect());
    }

    public function testExactCountsOverTheSameFromAndWhere(): void
    {
        $sql = $this->builder()->count(new Query(
            $this->entity(),
            ['id' => 'id', 'author' => 'user.name'],
            ['site_id' => 7],
            [new Filter('author', FilterOperator::Equals, 'Jana')],
            null,
            [],
            null,
            CountStrategy::Exact,
        ));

        $this->assertNotNull($sql);
        $this->assertStringStartsWith('SELECT COUNT(*) FROM `ads`', $sql->text);
        $this->assertStringContainsString('LEFT JOIN `users`', $sql->text);
        $this->assertStringContainsString('`ads`.`site_id` = ?', $sql->text);
        $this->assertSame([7, 'Jana'], $sql->bindings);
    }

    public function testACountHasNoSelectListOrderOrLimit(): void
    {
        $sql = $this->builder()->count(new Query(
            $this->entity(),
            ['views' => 'stats->daily->views'],
            [],
            [],
            null,
            [new Sort('views', SortDirection::Desc)],
            Page::of(3, 50),
        ));

        $this->assertNotNull($sql);
        $this->assertStringNotContainsString('ORDER BY', $sql->text);
        $this->assertStringNotContainsString('LIMIT', $sql->text);
        $this->assertSame([], $sql->bindings, 'the select list bindings are gone with it');
    }

    public function testNoneAsksForNoCountAtAll(): void
    {
        $this->assertNull($this->builder()->count(new Query(
            $this->entity(),
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            null,
            CountStrategy::None,
        )));
    }

    public function testEstimateUsesTableMetadata(): void
    {
        $sql = $this->builder()->count(new Query(
            $this->entity(),
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            null,
            CountStrategy::Estimate,
        ));

        $this->assertNotNull($sql);
        $this->assertStringContainsString('information_schema', $sql->text);
        $this->assertSame(['ads'], $sql->bindings);
    }

    public function testEstimateFallsBackToExactWhenAFilterNarrowsTheResult(): void
    {
        // A metadata estimate counts the whole table. Returning it for a
        // filtered query would report a number with no relation to the result.
        $sql = $this->builder()->count(new Query(
            $this->entity(),
            ['title' => 'title'],
            [],
            [new Filter('title', FilterOperator::Contains, 'kolo')],
            null,
            [],
            null,
            CountStrategy::Estimate,
        ));

        $this->assertNotNull($sql);
        $this->assertStringStartsWith('SELECT COUNT(*)', $sql->text);
        $this->assertSame(['%kolo%'], $sql->bindings);
    }

    public function testEstimateAlsoFallsBackForAScopeOrASearch(): void
    {
        $scoped = $this->builder()->count(new Query(
            $this->entity(),
            ['id' => 'id'],
            ['site_id' => 7],
            [],
            null,
            [],
            null,
            CountStrategy::Estimate,
        ));

        $searched = $this->builder()->count(new Query(
            $this->entity(),
            ['title' => 'title'],
            [],
            [],
            new \RockAdmin\Db\Search('kolo', ['title']),
            [],
            null,
            CountStrategy::Estimate,
        ));

        $this->assertNotNull($scoped);
        $this->assertNotNull($searched);
        $this->assertStringStartsWith('SELECT COUNT(*)', $scoped->text);
        $this->assertStringStartsWith('SELECT COUNT(*)', $searched->text);
    }

    public function testCachedIsRefusedRatherThanSilentlyDowngraded(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('milestone 10');

        $this->builder()->count(new Query(
            $this->entity(),
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            null,
            CountStrategy::Cached,
        ));
    }

    public function testExactIsTheDefault(): void
    {
        $sql = $this->builder()->count(new Query($this->entity(), ['id' => 'id']));

        $this->assertNotNull($sql);
        $this->assertStringStartsWith('SELECT COUNT(*)', $sql->text);
    }

    public function testACountJoinsOnlyTheRelationsItsConditionsUse(): void
    {
        // A count selects nothing, so a join no condition needs is wasted work --
        // and a LEFT JOIN to a to-many relation would multiply rows and inflate
        // COUNT(*) past the number of rows the grid shows.
        $sql = $this->builder()->count(new Query(
            $this->entity(),
            ['id' => 'id', 'author' => 'user.name'],
            [],
            [new Filter('id', FilterOperator::Equals, 3)],
            null,
            [],
            null,
            CountStrategy::Exact,
        ));

        $this->assertNotNull($sql);
        $this->assertStringNotContainsString('JOIN', $sql->text);
        $this->assertSame('SELECT COUNT(*) FROM `ads` WHERE `ads`.`id` = ?', $sql->text);
        $this->assertSame([3], $sql->bindings);
    }

    public function testACountStillJoinsForARelationItSearches(): void
    {
        $sql = $this->builder()->count(new Query(
            $this->entity(),
            ['id' => 'id', 'author' => 'user.name'],
            [],
            [],
            new Search('Jana', ['author']),
            [],
            null,
            CountStrategy::Exact,
        ));

        $this->assertNotNull($sql);
        $this->assertStringContainsString('LEFT JOIN `users`', $sql->text);
    }
}
