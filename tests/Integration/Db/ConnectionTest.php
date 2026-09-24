<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Db\Connection;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Sql;
use RockAdmin\Tests\Support\DatabaseTestCase;

#[CoversClass(Connection::class)]
final class ConnectionTest extends DatabaseTestCase
{
    #[DataProvider('connections')]
    public function testSelectReturnsAssociativeRows(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $rows = $connection->select(new Sql('SELECT id, title FROM ra_test_ads WHERE id = ?', [1]));

        $this->assertCount(1, $rows);
        $this->assertSame('Horské kolo', $rows[0]['title']);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testScalarReturnsNullWhenNothingMatches(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $this->assertSame(
            null,
            $connection->scalar(new Sql('SELECT title FROM ra_test_ads WHERE id = ?', [999])),
        );

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testQuotedIdentifiersAreAcceptedByTheServer(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $dialect = $connection->dialect();
        $column = $dialect->qualify('ra_test_ads', 'title');
        $table = $dialect->quoteIdentifier('ra_test_ads');

        $rows = $connection->select(new Sql("SELECT {$column} FROM {$table} ORDER BY {$column}"));

        $this->assertCount(3, $rows);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testCaseInsensitiveLikeMatchesRegardlessOfCase(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $like = $connection->dialect()->caseInsensitiveLike();
        $rows = $connection->select(new Sql("SELECT id FROM ra_test_ads WHERE title {$like} ?", ['%KOLO%']));

        $this->assertCount(2, $rows, 'both bicycles match irrespective of case');

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testJsonPathReadsANestedValue(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $dialect = $connection->dialect();
        $expression = $dialect->jsonPath($dialect->qualify('ra_test_ads', 'stats'), ['daily', 'views']);

        $rows = $connection->select(new Sql(
            "SELECT {$expression->text} AS views FROM ra_test_ads WHERE id = ?",
            [...$expression->bindings, 1],
        ));

        $views = $rows[0]['views'];
        $this->assertSame('42', \is_scalar($views) ? (string) $views : get_debug_type($views));

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testEstimatedCountReturnsANumberForAKnownTable(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $estimate = $connection->scalar($connection->dialect()->estimatedCount('ra_test_ads'));

        // The value is approximate by definition — freshly created tables often
        // report zero — so this only proves the statement runs and returns a number.
        $this->assertIsNumeric($estimate);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testColumnsReturnsTheTablesOwnColumnsInDeclaredOrder(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $columns = $connection->columns('ra_test_ads');

        $this->assertSame(['id', 'user_id', 'title', 'price', 'state', 'stats'], $columns);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAFailingStatementRaisesDbExceptionCarryingTheSql(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        // The marker appears in the statement we sent and nowhere in either
        // server's own "table not found" text, so it can only reach the message
        // through the SQL the exception carries.
        $sql = new Sql('SELECT 1 AS ra_marker_xyz FROM ra_test_nonexistent');

        try {
            $connection->select($sql);
            $this->fail('Expected the missing table to raise a DbException.');
        } catch (DbException $e) {
            $this->assertStringContainsString('ra_marker_xyz', $e->getMessage());
            $this->assertStringContainsString($sql->text, $e->getMessage());
        }
    }

    #[DataProvider('connections')]
    public function testAFailingStatementCarriesTheDriversSqlState(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        // Undefined table: '42S02' on MySQL, '42P01' on PostgreSQL -- both
        // class '42' (syntax error or access rule violation), which is the
        // fact PreviewRegion and anything else reading sqlStateClass() cares
        // about, not the five-character code itself.
        try {
            $connection->select(new Sql('SELECT 1 FROM ra_test_nonexistent'));
            $this->fail('Expected the missing table to raise a DbException.');
        } catch (DbException $e) {
            $this->assertNotNull($e->sqlState, 'the driver always reports one for a failed statement');
            $this->assertSame('42', $e->sqlStateClass());
        }
    }

    #[DataProvider('connections')]
    public function testAnIdThatDoesNotFitTheKeyColumnsTypeCarriesADataExceptionSqlState(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        // MySQL coerces 'abc' to 0 and simply matches nothing -- no
        // exception, and nothing for this test to check beyond that fact
        // itself, which is why PreviewRegion never needs the SQLSTATE
        // check on that driver: an ordinary empty result already reads as
        // "no such row". PostgreSQL raises '22P02', which is SQLSTATE class
        // '22' (data exception): a value that does not fit where it was
        // put, not a broken connection or a missing table.
        try {
            $rows = $connection->select(new Sql('SELECT id FROM ra_test_ads WHERE id = ?', ['abc']));
            $this->assertSame([], $rows, "MySQL coerces 'abc' to 0 and matches nothing");
        } catch (DbException $e) {
            $this->assertSame('22', $e->sqlStateClass());
        }

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testColumnsOfATableThatDoesNotExistIsRefusedNamingTheTable(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        // No createFixtures(): the table genuinely does not exist, which is
        // the point -- MySQL's SHOW COLUMNS already throws for one, and
        // PostgreSQL's information_schema.columns silently matches zero
        // rows instead, so without Connection::columns() checking for that
        // itself, the same typo in entity.table fails loudly on one driver
        // and produces an empty '@all' preview on the other.
        try {
            $connection->columns('ra_test_nonexistent');
            $this->fail('Expected a nonexistent table to be refused.');
        } catch (DbException $e) {
            $this->assertStringContainsString('ra_test_nonexistent', $e->getMessage());
        }
    }
}
