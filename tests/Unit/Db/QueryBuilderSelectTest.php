<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Entity;
use RockAdmin\Db\JoinType;
use RockAdmin\Db\MySqlDialect;
use RockAdmin\Db\PgDialect;
use RockAdmin\Db\Query;
use RockAdmin\Db\QueryBuilder;
use RockAdmin\Db\Relation;

#[CoversClass(QueryBuilder::class)]
#[CoversClass(Query::class)]
final class QueryBuilderSelectTest extends TestCase
{
    private function entity(): Entity
    {
        return new Entity('ads', 'id', [
            'user' => new Relation('user', 'users', 'users.id = ads.user_id'),
            'user.company' => new Relation(
                'user.company',
                'companies',
                'companies.id = users.company_id',
                JoinType::Inner,
            ),
        ]);
    }

    private function builder(): QueryBuilder
    {
        return new QueryBuilder(new MySqlDialect());
    }

    public function testSelectsOnlyTheColumnsAsked(): void
    {
        $sql = $this->builder()->rows(new Query($this->entity(), ['id' => 'id', 'title' => 'title']));

        $this->assertStringContainsString('SELECT `ads`.`id` AS `id`, `ads`.`title` AS `title`', $sql->text);
        $this->assertStringContainsString('FROM `ads`', $sql->text);
        $this->assertStringNotContainsString('*', $sql->text, 'never SELECT *');
    }

    public function testAColumnThroughARelationAddsItsJoin(): void
    {
        $sql = $this->builder()->rows(new Query($this->entity(), ['author' => 'user.name']));

        $this->assertStringContainsString('`users`.`name` AS `author`', $sql->text);
        $this->assertStringContainsString('LEFT JOIN `users` ON users.id = ads.user_id', $sql->text);
    }

    public function testAChainedRelationJoinsEveryPrefixInOrder(): void
    {
        $sql = $this->builder()->rows(new Query($this->entity(), ['company' => 'user.company.name']));

        $user = strpos($sql->text, 'JOIN `users`');
        $company = strpos($sql->text, 'JOIN `companies`');

        $this->assertIsInt($user);
        $this->assertIsInt($company);
        $this->assertLessThan($company, $user, 'users must be joined before companies');
        $this->assertStringContainsString('INNER JOIN `companies`', $sql->text);
    }

    public function testTheSameRelationIsJoinedOnceHoweverManyColumnsUseIt(): void
    {
        $sql = $this->builder()->rows(new Query($this->entity(), [
            'author' => 'user.name',
            'email' => 'user.email',
            'company' => 'user.company.name',
        ]));

        $this->assertSame(1, substr_count($sql->text, 'JOIN `users`'));
        $this->assertSame(1, substr_count($sql->text, 'JOIN `companies`'));
    }

    public function testAJsonColumnBindsItsPointerAndAddsNoJoin(): void
    {
        $sql = $this->builder()->rows(new Query($this->entity(), ['views' => 'stats->daily->views']));

        $this->assertStringContainsString('AS `views`', $sql->text);
        $this->assertStringNotContainsString('JOIN', $sql->text);
        $this->assertSame(['$."daily"."views"'], $sql->bindings);
    }

    public function testBindingsFollowTheOrderOfTheSelectList(): void
    {
        $sql = $this->builder()->rows(new Query($this->entity(), [
            'views' => 'stats->daily->views',
            'title' => 'title',
            'locale' => 'stats->locale',
        ]));

        $this->assertSame(['$."daily"."views"', '$."locale"'], $sql->bindings);
    }

    public function testPostgresProducesItsOwnQuoting(): void
    {
        $sql = (new QueryBuilder(new PgDialect()))
            ->rows(new Query($this->entity(), ['author' => 'user.name']));

        $this->assertStringContainsString('"users"."name" AS "author"', $sql->text);
        $this->assertStringContainsString('FROM "ads"', $sql->text);
    }

    public function testAQueryWithNoColumnsIsRefused(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('at least one column');

        $this->builder()->rows(new Query($this->entity(), []));
    }

    public function testAnUnknownRelationIsRefusedWithASuggestion(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('user');

        $this->builder()->rows(new Query($this->entity(), ['x' => 'usr.name']));
    }
}
