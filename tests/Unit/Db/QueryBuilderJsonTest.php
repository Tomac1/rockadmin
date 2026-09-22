<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Db\CountStrategy;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Filter;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\MySqlDialect;
use RockAdmin\Db\Query;
use RockAdmin\Db\QueryBuilder;
use RockAdmin\Db\Search;
use RockAdmin\Db\Sort;
use RockAdmin\Db\SortDirection;
use RockAdmin\Db\Sql;

/**
 * A JSON source resolves to an expression that carries a bound pointer, so
 * reusing it outside the select list has to carry the pointer with it. The
 * property every test here asserts is the one that was broken: a statement
 * has exactly as many values as it has placeholders.
 */
#[CoversClass(QueryBuilder::class)]
#[CoversClass(MySqlDialect::class)]
final class QueryBuilderJsonTest extends TestCase
{
    private const POINTER = '$."daily"."views"';

    private function entity(): Entity
    {
        return new Entity('ads', 'id');
    }

    private function builder(): QueryBuilder
    {
        return new QueryBuilder(new MySqlDialect());
    }

    private function placeholders(Sql $sql): int
    {
        return substr_count($sql->text, '?');
    }

    public function testAJsonColumnCarriesItsPointerIntoAFilter(): void
    {
        $sql = $this->builder()->rows(new Query(
            $this->entity(),
            ['views' => 'stats->daily->views'],
            [],
            [new Filter('views', FilterOperator::Equals, 42)],
        ));

        $this->assertSame(\count($sql->bindings), $this->placeholders($sql));
        $this->assertSame([self::POINTER, self::POINTER, 42], $sql->bindings);
    }

    public function testAJsonColumnCarriesItsPointerIntoASort(): void
    {
        $sql = $this->builder()->rows(new Query(
            $this->entity(),
            ['views' => 'stats->daily->views'],
            [],
            [],
            null,
            [new Sort('views', SortDirection::Desc)],
        ));

        $this->assertSame(\count($sql->bindings), $this->placeholders($sql));
        $this->assertSame([self::POINTER, self::POINTER], $sql->bindings);
    }

    public function testAJsonColumnCarriesItsPointerIntoASearch(): void
    {
        $sql = $this->builder()->rows(new Query(
            $this->entity(),
            ['views' => 'stats->daily->views'],
            [],
            [],
            new Search('42', ['views']),
        ));

        $this->assertSame(\count($sql->bindings), $this->placeholders($sql));
        $this->assertSame([self::POINTER, self::POINTER, '%42%'], $sql->bindings);
    }

    public function testAJsonColumnCarriesItsPointerIntoACount(): void
    {
        $sql = $this->builder()->count(new Query(
            $this->entity(),
            ['views' => 'stats->daily->views'],
            [],
            [new Filter('views', FilterOperator::Equals, 42)],
            null,
            [],
            null,
            CountStrategy::Exact,
        ));

        $this->assertNotNull($sql);
        $this->assertSame(\count($sql->bindings), $this->placeholders($sql));
        $this->assertSame([self::POINTER, 42], $sql->bindings);
    }
}
