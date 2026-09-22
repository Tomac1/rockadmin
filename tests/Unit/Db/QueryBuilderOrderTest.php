<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Entity;
use RockAdmin\Db\MySqlDialect;
use RockAdmin\Db\Page;
use RockAdmin\Db\Query;
use RockAdmin\Db\QueryBuilder;
use RockAdmin\Db\Relation;
use RockAdmin\Db\Sort;
use RockAdmin\Db\SortDirection;

#[CoversClass(QueryBuilder::class)]
#[CoversClass(Sort::class)]
#[CoversClass(Page::class)]
#[CoversClass(SortDirection::class)]
final class QueryBuilderOrderTest extends TestCase
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

    public function testSortUsesTheColumnsExpression(): void
    {
        $sql = $this->builder()->rows(new Query(
            $this->entity(),
            ['author' => 'user.name'],
            [],
            [],
            null,
            [new Sort('author', SortDirection::Desc)],
        ));

        $this->assertStringContainsString('ORDER BY `users`.`name` DESC', $sql->text);
    }

    public function testSeveralSortsKeepTheirOrder(): void
    {
        $sql = $this->builder()->rows(new Query(
            $this->entity(),
            ['state' => 'state', 'price' => 'price'],
            [],
            [],
            null,
            [new Sort('state', SortDirection::Asc), new Sort('price', SortDirection::Desc)],
        ));

        $this->assertStringContainsString('ORDER BY `ads`.`state` ASC, `ads`.`price` DESC', $sql->text);
    }

    public function testAnUnknownSortColumnIsDiscarded(): void
    {
        // Sorting arrives from a URL. A column the page does not expose is
        // dropped rather than raised, the same way an unknown filter is.
        $sql = $this->builder()->rows(new Query(
            $this->entity(),
            ['title' => 'title'],
            [],
            [],
            null,
            [new Sort('salary', SortDirection::Desc)],
        ));

        $this->assertStringNotContainsString('ORDER BY', $sql->text);
    }

    public function testOffsetPaging(): void
    {
        $sql = $this->builder()->rows(new Query(
            $this->entity(),
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            Page::of(3, 50),
        ));

        $this->assertStringContainsString('LIMIT 50 OFFSET 100', $sql->text);
    }

    public function testTheFirstPageHasNoOffset(): void
    {
        $sql = $this->builder()->rows(new Query(
            $this->entity(),
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            Page::of(1, 20),
        ));

        $this->assertStringContainsString('LIMIT 20', $sql->text);
        $this->assertStringNotContainsString('OFFSET', $sql->text);
    }

    public function testPageNumbersBelowOneAreTreatedAsTheFirstPage(): void
    {
        $this->assertSame(0, Page::of(0, 20)->offset);
        $this->assertSame(0, Page::of(-5, 20)->offset);
    }

    public function testAPerPageBelowOneIsRefused(): void
    {
        $this->expectException(DbException::class);

        Page::of(1, 0);
    }

    public function testKeysetPagingOrdersByTheKeyAndSeeksPastTheCursor(): void
    {
        $sql = $this->builder()->rows(new Query(
            $this->entity(),
            ['id' => 'id', 'title' => 'title'],
            [],
            [],
            null,
            [],
            Page::after(120, 50),
        ));

        $this->assertStringContainsString('`ads`.`id` < ?', $sql->text);
        $this->assertStringContainsString('ORDER BY `ads`.`id` DESC', $sql->text);
        $this->assertStringContainsString('LIMIT 50', $sql->text);
        $this->assertStringNotContainsString('OFFSET', $sql->text);
        $this->assertSame([120], $sql->bindings);
    }

    public function testKeysetPagingFromTheStartHasNoCursorCondition(): void
    {
        $sql = $this->builder()->rows(new Query(
            $this->entity(),
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            Page::after(null, 50),
        ));

        $this->assertStringNotContainsString('WHERE', $sql->text);
        $this->assertStringContainsString('ORDER BY `ads`.`id` DESC', $sql->text);
    }

    public function testKeysetPagingIgnoresAnyOtherSort(): void
    {
        // A cursor is only meaningful against the order it was taken from, and
        // that order is the key. Honouring another sort would skip rows.
        $sql = $this->builder()->rows(new Query(
            $this->entity(),
            ['id' => 'id', 'price' => 'price'],
            [],
            [],
            null,
            [new Sort('price', SortDirection::Asc)],
            Page::after(120, 50),
        ));

        $this->assertStringContainsString('ORDER BY `ads`.`id` DESC', $sql->text);
        $this->assertStringNotContainsString('`ads`.`price` ASC', $sql->text);
    }

    public function testBindingOrderIsSelectThenWhereThenCursor(): void
    {
        $sql = $this->builder()->rows(new Query(
            $this->entity(),
            ['views' => 'stats->daily->views'],
            ['site_id' => 7],
            [],
            null,
            [],
            Page::after(120, 50),
        ));

        $this->assertSame(['$."daily"."views"', 7, 120], $sql->bindings);
    }
}
