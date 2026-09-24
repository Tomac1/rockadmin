<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Placeholder;
use RockAdmin\Db\Connection;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Entity;
use RockAdmin\Db\MySqlDialect;
use RockAdmin\Db\SqlWriteHandler;
use RockAdmin\Db\WriteResult;
use RockAdmin\Tests\Support\FakePdo;

/**
 * Builds against a fake PDO wrapped in a real `Connection` -- `Connection`
 * is final, so nothing else stands in for it. These tests assert on the
 * SQL text and bindings `FakePdo::$executed` recorded, never on a real
 * database.
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

    public function testAnInsertNamesOnlyTheColumnsGiven(): void
    {
        $pdo = new FakePdo([[['id' => 1, 'title' => 'Horské kolo', 'price' => 12000]]]);

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
        $pdo = new FakePdo([[['id' => 1, 'title' => 'Horské kolo']]]);

        $this->handler($pdo)->insert($this->entity(), ['id' => 1, 'title' => 'Horské kolo']);

        $read = $pdo->executed[1];

        $this->assertSame('SELECT * FROM `ads` WHERE `id` = ?', $read->text);
        $this->assertSame(['1'], $read->bindings);
    }

    public function testAnInsertWithoutItsKeyAmongTheValuesIsRefused(): void
    {
        $pdo = new FakePdo();

        $this->expectException(DbException::class);

        $this->handler($pdo)->insert($this->entity(), ['title' => 'Horské kolo']);
    }

    public function testAnUpdateSetsOnlyTheColumnsGivenAndFiltersByTheKey(): void
    {
        $pdo = new FakePdo([
            [['id' => 1, 'title' => 'Old title', 'price' => 12000]],
            [['id' => 1, 'title' => 'New title', 'price' => 12000]],
        ]);

        $this->handler($pdo)->update($this->entity(), '1', ['title' => 'New title']);

        $update = $pdo->executed[1];

        $this->assertSame('UPDATE `ads` SET `title` = ? WHERE `id` = ?', $update->text);
        $this->assertSame(['New title', '1'], $update->bindings);
    }

    public function testADeleteFiltersByTheKey(): void
    {
        $pdo = new FakePdo([[['id' => 1, 'title' => 'Horské kolo']]]);

        $this->handler($pdo)->delete($this->entity(), '1');

        $delete = $pdo->executed[1];

        $this->assertSame('DELETE FROM `ads` WHERE `id` = ?', $delete->text);
        $this->assertSame(['1'], $delete->bindings);
    }

    public function testAPlaceholderValueIsBoundNeverInterpolated(): void
    {
        $placeholder = new Placeholder('user', 'id');
        $pdo = new FakePdo([[['id' => 1, 'author_id' => $placeholder]]]);

        $this->handler($pdo)->insert($this->entity(), ['id' => 1, 'author_id' => $placeholder]);

        $insert = $pdo->executed[0];

        $this->assertStringNotContainsString('{{user.id}}', $insert->text);
        $this->assertSame([1, $placeholder], $insert->bindings);
    }

    public function testEveryWriteRunsInsideATransaction(): void
    {
        $pdo = new FakePdo([[['id' => 1]]]);

        $this->handler($pdo)->insert($this->entity(), ['id' => 1]);

        $this->assertSame(1, $pdo->begins);
        $this->assertSame(1, $pdo->commits);
        $this->assertSame(0, $pdo->rollbacks);
    }

    public function testAnUpdateOfARowThatDoesNotExistIsRefused(): void
    {
        $pdo = new FakePdo([[]]);

        $this->expectException(DbException::class);

        $this->handler($pdo)->update($this->entity(), '999', ['title' => 'New title']);
    }
}
