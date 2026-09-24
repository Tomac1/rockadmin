<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Db;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Placeholder;
use RockAdmin\Db\Connection;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Entity;
use RockAdmin\Db\MySqlDialect;
use RockAdmin\Db\PgDialect;
use RockAdmin\Db\SqlWriteHandler;
use RockAdmin\Db\WriteResult;
use RockAdmin\Tests\Support\FakePdo;

/**
 * Builds against a fake PDO wrapped in a real `Connection` -- `Connection`
 * is final, so nothing else stands in for it. These tests assert on the
 * SQL text and bindings `FakePdo::$executed` recorded, never on a real
 * database. `FakePdo`'s row queue has one entry per statement executed, in
 * order -- `[]` for a plain INSERT/UPDATE/DELETE that returns no rows, a
 * real row for every `SELECT` this handler issues to read a row back.
 */
#[CoversClass(SqlWriteHandler::class)]
#[CoversClass(WriteResult::class)]
final class SqlWriteHandlerTest extends TestCase
{
    private function entity(): Entity
    {
        return new Entity('ads', 'id');
    }

    private function handler(FakePdo $pdo): SqlWriteHandler
    {
        return new SqlWriteHandler(new Connection($pdo, new MySqlDialect()));
    }

    private function pgHandler(FakePdo $pdo): SqlWriteHandler
    {
        return new SqlWriteHandler(new Connection($pdo, new PgDialect()));
    }

    public function testAnInsertNamesOnlyTheColumnsGiven(): void
    {
        $pdo = new FakePdo([[], [['id' => 1, 'title' => 'Horské kolo', 'price' => 12000]]]);

        $this->handler($pdo)->insert($this->entity(), ['id' => 1, 'title' => 'Horské kolo']);

        $insert = $pdo->executed[0];

        $this->assertSame(
            'INSERT INTO `ads` (`id`, `title`) VALUES (?, ?)',
            $insert->text,
        );
        $this->assertSame([1, 'Horské kolo'], $insert->bindings);
    }

    public function testAnInsertReadsTheRowBackForItsKey(): void
    {
        $pdo = new FakePdo([[], [['id' => 1, 'title' => 'Horské kolo']]]);

        $this->handler($pdo)->insert($this->entity(), ['id' => 1, 'title' => 'Horské kolo']);

        $read = $pdo->executed[1];

        $this->assertSame('SELECT * FROM `ads` WHERE `id` = ?', $read->text);
        $this->assertSame(['1'], $read->bindings);
    }

    public function testAnInsertWithNoValuesIsRefused(): void
    {
        $pdo = new FakePdo();

        $this->expectException(DbException::class);

        $this->handler($pdo)->insert($this->entity(), []);
    }

    public function testAnInsertWithoutItsKeyAsksMysqlForTheGeneratedOne(): void
    {
        $pdo = new FakePdo([[], [['id' => 7, 'title' => 'Horské kolo']]]);
        $pdo->nextInsertId = '7';

        $result = $this->handler($pdo)->insert($this->entity(), ['title' => 'Horské kolo']);

        $insert = $pdo->executed[0];

        $this->assertSame('INSERT INTO `ads` (`title`) VALUES (?)', $insert->text);
        $this->assertSame(['Horské kolo'], $insert->bindings);
        $this->assertSame('7', $result->key);
    }

    public function testAnInsertWithoutItsKeyUsesReturningOnPostgres(): void
    {
        $pdo = new FakePdo([
            [['id' => 7]],
            [['id' => 7, 'title' => 'Horské kolo']],
        ]);

        $result = $this->pgHandler($pdo)->insert($this->entity(), ['title' => 'Horské kolo']);

        $insert = $pdo->executed[0];

        $this->assertSame('INSERT INTO "ads" ("title") VALUES (?) RETURNING "id"', $insert->text);
        $this->assertSame(['Horské kolo'], $insert->bindings);
        $this->assertSame('7', $result->key);
    }

    public function testAnInsertWithAnExplicitKeyNeverAsksForAGeneratedOne(): void
    {
        $pdo = new FakePdo([[], [['id' => 1, 'title' => 'Horské kolo']]]);
        $pdo->nextInsertId = '999';

        $result = $this->handler($pdo)->insert($this->entity(), ['id' => 1, 'title' => 'Horské kolo']);

        $this->assertSame('1', $result->key, 'the given key wins, not whatever lastInsertId() answers');
    }

    /**
     * A hidden key field on a create form sends `''` (and could just as
     * well send `null`) when it has nothing to offer, not an absent key --
     * both must reach the same generated-key path an absent key does.
     */
    public function testAnInsertWithAnEmptyStringKeyLetsTheDatabaseAssignOne(): void
    {
        $pdo = new FakePdo([[], [['id' => 7, 'title' => 'Horské kolo']]]);
        $pdo->nextInsertId = '7';

        $result = $this->handler($pdo)->insert($this->entity(), ['id' => '', 'title' => 'Horské kolo']);

        $insert = $pdo->executed[0];

        $this->assertSame(
            'INSERT INTO `ads` (`title`) VALUES (?)',
            $insert->text,
            'the empty-string key must not be inserted as a column value',
        );
        $this->assertSame('7', $result->key);
    }

    public function testAnInsertWithANullKeyLetsTheDatabaseAssignOne(): void
    {
        $pdo = new FakePdo([[], [['id' => 7, 'title' => 'Horské kolo']]]);
        $pdo->nextInsertId = '7';

        $result = $this->handler($pdo)->insert($this->entity(), ['id' => null, 'title' => 'Horské kolo']);

        $this->assertSame('7', $result->key);
    }

    public function testAGeneratedKeyOfZeroIsRefused(): void
    {
        $pdo = new FakePdo();
        $pdo->nextInsertId = '0'; // PDO::lastInsertId()'s own "nothing was generated"

        $this->expectException(DbException::class);

        $this->handler($pdo)->insert($this->entity(), ['title' => 'Horské kolo']);
    }

    public function testAnUpdateSetsOnlyTheColumnsGivenAndFiltersByTheKey(): void
    {
        $pdo = new FakePdo([
            [['id' => 1, 'title' => 'Old title', 'price' => 12000]],
            [],
            [['id' => 1, 'title' => 'New title', 'price' => 12000]],
        ]);

        $this->handler($pdo)->update($this->entity(), '1', ['title' => 'New title']);

        $update = $pdo->executed[1];

        $this->assertSame('UPDATE `ads` SET `title` = ? WHERE `id` = ?', $update->text);
        $this->assertSame(['New title', '1'], $update->bindings);
    }

    /**
     * C2: an update that changes the key column itself -- an editable
     * natural key such as `code` is ordinary admin configuration -- must
     * read `after` back by the new key, and report the new key as
     * `WriteResult::$key`, not the one the row no longer has.
     */
    public function testAnUpdateThatChangesTheKeyColumnReadsBackByTheNewKey(): void
    {
        $pdo = new FakePdo([
            [['id' => 1, 'title' => 'Old title']],
            [],
            [['id' => 2, 'title' => 'New title']],
        ]);

        $result = $this->handler($pdo)->update($this->entity(), '1', ['id' => 2, 'title' => 'New title']);

        $read = $pdo->executed[2];

        $this->assertSame('SELECT * FROM `ads` WHERE `id` = ?', $read->text);
        $this->assertSame(['2'], $read->bindings, 'must read back by the new key, not the old one');
        $this->assertSame('2', $result->key);
        $this->assertSame(1, $result->before['id']);
        $this->assertSame(2, $result->after['id']);
    }

    /**
     * The old implementation fell back to `$before` when the read-back
     * after a successful write found nothing, which would have made this
     * report no change at all rather than raise. Guard it explicitly.
     */
    public function testAnUpdateThatCannotBeReadBackAfterwardsRaisesRatherThanFallingBackToBefore(): void
    {
        $pdo = new FakePdo([
            [['id' => 1, 'title' => 'Old title']],
            [],
            [], // the read-back after the write finds nothing
        ]);

        $this->expectException(DbException::class);

        $this->handler($pdo)->update($this->entity(), '1', ['id' => 2, 'title' => 'New title']);
    }

    public function testADeleteFiltersByTheKey(): void
    {
        $pdo = new FakePdo([[['id' => 1, 'title' => 'Horské kolo']], []]);

        $this->handler($pdo)->delete($this->entity(), '1');

        $delete = $pdo->executed[1];

        $this->assertSame('DELETE FROM `ads` WHERE `id` = ?', $delete->text);
        $this->assertSame(['1'], $delete->bindings);
    }

    public function testAPlaceholderValueIsBoundNeverInterpolated(): void
    {
        $placeholder = new Placeholder('user', 'id');
        $pdo = new FakePdo([[], [['id' => 1, 'author_id' => $placeholder]]]);

        $this->handler($pdo)->insert($this->entity(), ['id' => 1, 'author_id' => $placeholder]);

        $insert = $pdo->executed[0];

        $this->assertStringNotContainsString('{{user.id}}', $insert->text);
        $this->assertSame([1, $placeholder], $insert->bindings);
    }

    /**
     * C1: `Connection::run()` binds each value with an inferred
     * `PDO::PARAM_*` type rather than handing the array straight to
     * `execute()`, which would bind everything as `PARAM_STR` and turn
     * `false` into `''` before it ever reaches the driver.
     */
    public function testEveryValueIsBoundWithATypeInferredFromItsPhpType(): void
    {
        $pdo = new FakePdo([[], [['id' => 1]]]);

        $this->handler($pdo)->insert($this->entity(), [
            'id' => 1,
            'active' => false,
            'note' => null,
            'title' => 'Horské kolo',
        ]);

        $types = $pdo->statements[0]->boundTypes;

        $this->assertSame(PDO::PARAM_INT, $types[1]);
        $this->assertSame(PDO::PARAM_BOOL, $types[2]);
        $this->assertSame(PDO::PARAM_NULL, $types[3]);
        $this->assertSame(PDO::PARAM_STR, $types[4]);

        // The value itself must survive as a real bool, not a stringified one.
        $insert = $pdo->executed[0];
        $this->assertFalse($insert->bindings[1]);
    }

    public function testEveryWriteRunsInsideATransaction(): void
    {
        $pdo = new FakePdo([[], [['id' => 1]]]);

        $this->handler($pdo)->insert($this->entity(), ['id' => 1]);

        $this->assertSame(1, $pdo->begins);
        $this->assertSame(1, $pdo->commits);
        $this->assertSame(0, $pdo->rollbacks);
    }

    /**
     * I3: a bulk action (rule 7) opens one transaction around a loop of
     * writes, and each write opens its own -- the inner ones must join
     * the outer one rather than being refused, or a bulk action could
     * never be atomic through this same write path.
     */
    public function testAWriteInsideAnAlreadyOpenTransactionJoinsItRatherThanBeingRefused(): void
    {
        $pdo = new FakePdo([[], [['id' => 1]], [], [['id' => 2]]]);
        $connection = new Connection($pdo, new MySqlDialect());
        $handler = new SqlWriteHandler($connection);

        $connection->transaction(function () use ($handler): void {
            $handler->insert($this->entity(), ['id' => 1]);
            $handler->insert($this->entity(), ['id' => 2]);
        });

        $this->assertSame(1, $pdo->begins, 'only the outer transaction() call begins one');
        $this->assertSame(1, $pdo->commits);
    }

    public function testAnUpdateOfARowThatDoesNotExistIsRefused(): void
    {
        $pdo = new FakePdo([[]]);

        $this->expectException(DbException::class);

        $this->handler($pdo)->update($this->entity(), '999', ['title' => 'New title']);
    }
}
