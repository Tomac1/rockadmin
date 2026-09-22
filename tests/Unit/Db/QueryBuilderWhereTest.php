<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Placeholder;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Filter;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\MySqlDialect;
use RockAdmin\Db\PgDialect;
use RockAdmin\Db\Query;
use RockAdmin\Db\QueryBuilder;
use RockAdmin\Db\Relation;
use RockAdmin\Db\Search;

#[CoversClass(QueryBuilder::class)]
#[CoversClass(Filter::class)]
#[CoversClass(FilterOperator::class)]
#[CoversClass(Search::class)]
final class QueryBuilderWhereTest extends TestCase
{
    private function entity(): Entity
    {
        return new Entity('ads', 'id', [
            'user' => new Relation('user', 'users', 'users.id = ads.user_id'),
        ]);
    }

    /**
     * @param array<string, string> $columns
     * @param array<string, mixed>  $scope
     * @param list<Filter>          $filters
     */
    private function query(
        array $columns,
        array $scope = [],
        array $filters = [],
        ?Search $search = null,
    ): Query {
        return new Query($this->entity(), $columns, $scope, $filters, $search);
    }

    private function builder(): QueryBuilder
    {
        return new QueryBuilder(new MySqlDialect());
    }

    public function testNoConditionsMeansNoWhereClause(): void
    {
        $sql = $this->builder()->rows($this->query(['id' => 'id']));

        $this->assertStringNotContainsString('WHERE', $sql->text);
    }

    public function testScopeIsBoundAndAppliedToTheEntitysOwnTable(): void
    {
        $sql = $this->builder()->rows($this->query(['id' => 'id'], ['site_id' => 7]));

        $this->assertStringContainsString('WHERE `ads`.`site_id` = ?', $sql->text);
        $this->assertSame([7], $sql->bindings);
    }

    public function testAFilterUsesTheColumnsSourceNotItsAlias(): void
    {
        $sql = $this->builder()->rows($this->query(
            ['author' => 'user.name'],
            [],
            [new Filter('author', FilterOperator::Equals, 'Jana')],
        ));

        $this->assertStringContainsString('`users`.`name` = ?', $sql->text);
        $this->assertSame(['Jana'], $sql->bindings);
    }

    public function testContainsWrapsTheTermAndUsesTheDialectsOperator(): void
    {
        $sql = $this->builder()->rows($this->query(
            ['title' => 'title'],
            [],
            [new Filter('title', FilterOperator::Contains, 'kolo')],
        ));

        $this->assertStringContainsString('`ads`.`title` LIKE ?', $sql->text);
        $this->assertSame(['%kolo%'], $sql->bindings);

        $pg = (new QueryBuilder(new PgDialect()))->rows($this->query(
            ['title' => 'title'],
            [],
            [new Filter('title', FilterOperator::Contains, 'kolo')],
        ));

        $this->assertStringContainsString('ILIKE ?', $pg->text);
    }

    public function testStartsWithAndEndsWithAnchorTheirTerm(): void
    {
        $starts = $this->builder()->rows($this->query(
            ['title' => 'title'],
            [],
            [new Filter('title', FilterOperator::StartsWith, 'Hor')],
        ));
        $ends = $this->builder()->rows($this->query(
            ['title' => 'title'],
            [],
            [new Filter('title', FilterOperator::EndsWith, 'kolo')],
        ));

        $this->assertSame(['Hor%'], $starts->bindings);
        $this->assertSame(['%kolo'], $ends->bindings);
    }

    public function testAWildcardInTheTermIsEscapedSoItMatchesLiterally(): void
    {
        // Without escaping, searching for "50%" would match everything.
        $sql = $this->builder()->rows($this->query(
            ['title' => 'title'],
            [],
            [new Filter('title', FilterOperator::Contains, '50%_x')],
        ));

        $this->assertSame(['%50\\%\\_x%'], $sql->bindings);
    }

    public function testInBindsEveryValue(): void
    {
        $sql = $this->builder()->rows($this->query(
            ['state' => 'state'],
            [],
            [new Filter('state', FilterOperator::In, ['active', 'draft'])],
        ));

        $this->assertStringContainsString('`ads`.`state` IN (?, ?)', $sql->text);
        $this->assertSame(['active', 'draft'], $sql->bindings);
    }

    public function testAnEmptyInMatchesNothingRatherThanEverything(): void
    {
        $sql = $this->builder()->rows($this->query(
            ['state' => 'state'],
            [],
            [new Filter('state', FilterOperator::In, [])],
        ));

        $this->assertStringContainsString('1 = 0', $sql->text);
        $this->assertSame([], $sql->bindings);
    }

    public function testBetweenTakesTwoValues(): void
    {
        $sql = $this->builder()->rows($this->query(
            ['price' => 'price'],
            [],
            [new Filter('price', FilterOperator::Between, [1000, 20000])],
        ));

        $this->assertStringContainsString('BETWEEN ? AND ?', $sql->text);
        $this->assertSame([1000, 20000], $sql->bindings);
    }

    public function testNullChecksBindNothing(): void
    {
        $sql = $this->builder()->rows($this->query(
            ['price' => 'price'],
            [],
            [new Filter('price', FilterOperator::IsNull)],
        ));

        $this->assertStringContainsString('`ads`.`price` IS NULL', $sql->text);
        $this->assertSame([], $sql->bindings);
    }

    public function testSearchIsOneGroupOfAlternativesAcrossItsColumns(): void
    {
        $sql = $this->builder()->rows($this->query(
            ['title' => 'title', 'author' => 'user.name'],
            [],
            [],
            new Search('kolo', ['title', 'author']),
        ));

        $this->assertStringContainsString(
            '(`ads`.`title` LIKE ? OR `users`.`name` LIKE ?)',
            $sql->text,
        );
        $this->assertSame(['%kolo%', '%kolo%'], $sql->bindings);
    }

    public function testConditionsAreJoinedWithAnd(): void
    {
        $sql = $this->builder()->rows($this->query(
            ['title' => 'title'],
            ['site_id' => 7],
            [new Filter('title', FilterOperator::Contains, 'kolo')],
            new Search('bazar', ['title']),
        ));

        $this->assertSame(2, substr_count($sql->text, ' AND '));
        $this->assertSame([7, '%kolo%', '%bazar%'], $sql->bindings, 'scope, then filters, then search');
    }

    public function testAFilterOnAnUnselectedColumnIsDiscarded(): void
    {
        // Filters arrive from a URL, so one naming a column the page does not
        // expose is dropped rather than raised — it is not the project's bug.
        $sql = $this->builder()->rows($this->query(
            ['title' => 'title'],
            [],
            [new Filter('secret_flag', FilterOperator::Equals, 1)],
        ));

        $this->assertStringNotContainsString('WHERE', $sql->text);
        $this->assertSame([], $sql->bindings);
    }

    public function testAnUnboundPlaceholderIsRefused(): void
    {
        // A workspace value must be bound to the request before it reaches a
        // query. Reaching SQL as an object means nobody bound it.
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('workspace.site_id');

        $this->builder()->rows($this->query(['id' => 'id'], [
            'site_id' => new Placeholder('workspace', 'site_id'),
        ]));
    }

    public function testScopeBindingsComeBeforeSelectListBindings(): void
    {
        $sql = $this->builder()->rows($this->query(
            ['views' => 'stats->daily->views'],
            ['site_id' => 7],
        ));

        $this->assertSame(['$."daily"."views"', 7], $sql->bindings, 'select list first, then where');
    }
}
