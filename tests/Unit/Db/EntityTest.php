<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Entity;
use RockAdmin\Db\JoinType;
use RockAdmin\Db\Relation;

#[CoversClass(Entity::class)]
#[CoversClass(Relation::class)]
#[CoversClass(JoinType::class)]
final class EntityTest extends TestCase
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

    public function testDefaultsToAnIdKeyAndNoRelations(): void
    {
        $entity = new Entity('ads');

        $this->assertSame('ads', $entity->table);
        $this->assertSame('id', $entity->key);
        $this->assertSame([], $entity->relations);
    }

    public function testARelationIsFoundByItsFullPath(): void
    {
        $relation = $this->entity()->relation('user.company');

        $this->assertSame('companies', $relation->table);
        $this->assertSame(JoinType::Inner, $relation->type);
    }

    public function testARelationDefaultsToALeftJoin(): void
    {
        // A left join is the safe default: an inner join silently hides rows
        // whose related record is missing, which in a grid reads as data loss.
        $this->assertSame(JoinType::Left, $this->entity()->relation('user')->type);
    }

    public function testAnUnknownRelationSuggestsTheNearestOne(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('user.company');

        $this->entity()->relation('user.compny');
    }

    public function testAnUnknownRelationWithNothingCloseJustSaysSo(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('Unknown relation');

        $this->entity()->relation('x');
    }

    public function testHasRelation(): void
    {
        $this->assertTrue($this->entity()->hasRelation('user'));
        $this->assertFalse($this->entity()->hasRelation('nope'));
    }

    public function testARelationsNameMustMatchItsKey(): void
    {
        // The key is what a source path refers to, so a mismatch would make
        // the relation unreachable while looking perfectly fine.
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('user');

        new Entity('ads', 'id', ['user' => new Relation('author', 'users', 'users.id = ads.user_id')]);
    }
}
