<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Db\Connection;
use RockAdmin\Db\DbException;
use RockAdmin\Db\MigrationConflictException;
use RockAdmin\Db\Migrator;
use RockAdmin\Db\MySqlDialect;
use RockAdmin\Db\PgDialect;
use RockAdmin\Db\Sql;
use RockAdmin\Tests\Support\DatabaseTestCase;

/**
 * This test creates and drops the SDK's own `ra_` tables, not the shared
 * read-only fixtures of `DatabaseTestCase`. Like `WriteTest`, it cleans up on
 * every configured connection in both `setUp()` and `tearDown()`, regardless
 * of which one the running case happens to use: a failing assertion here must
 * not leave `ra_users` behind for the next test.
 *
 * Migrations that are meant to fail, and the pair used for the
 * continue-where-it-stopped case, live in a throwaway directory created per
 * test rather than in `migrations/`, so the shipped set stays the shipped set.
 */
#[CoversClass(Migrator::class)]
#[CoversClass(MigrationConflictException::class)]
#[CoversClass(MySqlDialect::class)]
#[CoversClass(PgDialect::class)]
final class MigratorTest extends DatabaseTestCase
{
    private const TABLES = [
        'ra_activity_log',
        'ra_password_resets',
        'ra_login_attempts',
        'ra_users',
        'ra_migrations',
        'ra_test_migrate_a',
        'ra_test_migrate_b',
    ];

    private string $scratch = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropEverywhere();

        $scratch = tempnam(sys_get_temp_dir(), 'ra_mig');

        if ($scratch === false) {
            $this->fail('Could not create a scratch directory for migration fixtures.');
        }

        unlink($scratch);
        mkdir($scratch);

        $this->scratch = $scratch;
    }

    protected function tearDown(): void
    {
        $this->dropEverywhere();

        if ($this->scratch !== '') {
            foreach (glob($this->scratch . '/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($this->scratch);
            $this->scratch = '';
        }

        parent::tearDown();
    }

    #[DataProvider('connections')]
    public function testAFreshDatabaseGetsAllFiveTables(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $applied = (new Migrator($connection))->migrate();

        self::assertNotSame([], $applied, 'The shipped migration set applied nothing.');

        foreach ([
            'ra_users' => [
                'id', 'email', 'password', 'name', 'is_active', 'roles', 'workspaces',
                'auth_provider', 'auth_subject', 'last_login_at', 'created_at', 'founder',
            ],
            'ra_login_attempts' => ['id', 'email', 'ip', 'at', 'kind'],
            'ra_password_resets' => ['id', 'email', 'token_hash', 'expires_at', 'used_at'],
            'ra_activity_log' => ['id', 'user_id', 'page', 'action', 'row_id', 'at', 'diff'],
            'ra_migrations' => ['name', 'checksum', 'applied_at'],
        ] as $table => $columns) {
            self::assertSame($columns, $connection->columns($table), "Columns of {$table}");
        }
    }

    #[DataProvider('connections')]
    public function testRunningTwiceAppliesNothingTheSecondTime(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $migrator = new Migrator($connection);

        $first = $migrator->migrate();
        $second = $migrator->migrate();

        self::assertNotSame([], $first);
        self::assertSame([], $second, 'The second run applied something.');
        self::assertSame($first, $this->ledger($connection));
    }

    #[DataProvider('connections')]
    public function testAPartiallyAppliedSetContinuesFromWhereItStopped(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->write('001_a.sql', 'CREATE TABLE ra_test_migrate_a (id INTEGER PRIMARY KEY);');

        $migrator = new Migrator($connection, $this->scratch);

        self::assertSame(['001_a.sql'], $migrator->migrate());

        $this->write('002_b.sql', 'CREATE TABLE ra_test_migrate_b (id INTEGER PRIMARY KEY);');

        self::assertSame(['002_b.sql'], (new Migrator($connection, $this->scratch))->migrate());
        self::assertSame(['001_a.sql', '002_b.sql'], $this->ledger($connection));
        self::assertSame(['id'], $connection->columns('ra_test_migrate_a'));
        self::assertSame(['id'], $connection->columns('ra_test_migrate_b'));
    }

    /**
     * The honest answer to the DDL-rollback question, asserted per server
     * rather than assumed.
     *
     * The ledger row is safe on both, because the runner inserts it only
     * after every statement of the migration has succeeded — ordering, not
     * the transaction, is what guarantees that.
     *
     * The tables are not. PostgreSQL is transactional for DDL, so the
     * `CREATE TABLE` that ran before the failure is rolled back with
     * everything else. MySQL commits implicitly on every DDL statement, so
     * the transaction the runner opened has already ended by the time the
     * second statement fails and the created table survives. That is not a
     * bug in the runner; it is what MySQL does, and a migration file that
     * fails halfway on MySQL has to be cleaned up by hand before a retry.
     */
    #[DataProvider('connections')]
    public function testAMigrationThatThrowsRecordsNothing(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->write(
            '001_broken.sql',
            "CREATE TABLE ra_test_migrate_a (id INTEGER PRIMARY KEY);\n"
                . "CREATE TABLE ra_test_migrate_b (this is not sql);\n",
        );

        try {
            (new Migrator($connection, $this->scratch))->migrate();
            $this->fail('A broken migration was applied without complaint.');
        } catch (DbException $e) {
            self::assertStringContainsString('001_broken.sql', $e->getMessage());
        }

        self::assertSame([], $this->ledger($connection), 'A failed migration was recorded.');
        self::assertFalse($this->tableExists($connection, 'ra_test_migrate_b'));

        self::assertSame(
            $connection->dialect()->name() === 'mysql',
            $this->tableExists($connection, 'ra_test_migrate_a'),
            'MySQL commits DDL implicitly and keeps the table; PostgreSQL rolls it back.',
        );
    }

    /**
     * The failure this refuses is a silent one: the name is recorded, so an
     * installation that already ran the file never reads it again, and two
     * installations from the same commit end up with different schemas.
     */
    #[DataProvider('connections')]
    public function testAnAppliedMigrationThatHasBeenEditedIsRefused(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->write('001_a.sql', 'CREATE TABLE ra_test_migrate_a (id INTEGER PRIMARY KEY);');

        self::assertSame(['001_a.sql'], (new Migrator($connection, $this->scratch))->migrate());

        $this->write('001_a.sql', 'CREATE TABLE ra_test_migrate_a (id INTEGER PRIMARY KEY, extra INTEGER);');
        $this->write('002_b.sql', 'CREATE TABLE ra_test_migrate_b (id INTEGER PRIMARY KEY);');

        try {
            (new Migrator($connection, $this->scratch))->migrate();
            $this->fail('An edited migration was passed over in silence.');
        } catch (DbException $e) {
            // The type is the assertion a deploy script would make: it has to
            // tell "stop, somebody edited these" from "the database is
            // unreachable, retry", and both arrive as a DbException. The
            // message is checked only for the one thing a person reading it
            // needs first, which is which file.
            self::assertInstanceOf(MigrationConflictException::class, $e);
            self::assertStringContainsString('001_a.sql', $e->getMessage());
        }

        // The pending migration behind it is not applied either: a run that has
        // found the schema and the ledger disagreeing must stop, not carry on
        // adding to the difference.
        self::assertSame(['001_a.sql'], $this->ledger($connection));
        self::assertFalse($this->tableExists($connection, 'ra_test_migrate_b'));
    }

    /** Line endings are not schema, so a CRLF checkout is not an edit. */
    #[DataProvider('connections')]
    public function testAChangeOfLineEndingsIsNotAnEdit(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->write('001_a.sql', "CREATE TABLE ra_test_migrate_a (\nid INTEGER PRIMARY KEY\n);\n");

        self::assertSame(['001_a.sql'], (new Migrator($connection, $this->scratch))->migrate());

        $this->write('001_a.sql', "CREATE TABLE ra_test_migrate_a (\r\nid INTEGER PRIMARY KEY\r\n);\r\n");

        self::assertSame([], (new Migrator($connection, $this->scratch))->migrate());
    }

    /**
     * A row exists in this table only because somebody with database access
     * put it there, so the default is the useful one rather than the one that
     * locks the first administrator out.
     */
    #[DataProvider('connections')]
    public function testAUserIsActiveUnlessSaidOtherwise(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        (new Migrator($connection))->migrate();

        $connection->execute(new Sql(
            'INSERT INTO ra_users (email, name, roles, workspaces, created_at) VALUES (?, ?, ?, ?, ?)',
            ['jana@example.com', 'Jana', '[]', '[]', gmdate('Y-m-d H:i:s')],
        ));

        $active = $connection->scalar(new Sql(
            'SELECT is_active FROM ra_users WHERE email = ?',
            ['jana@example.com'],
        ));

        // PostgreSQL hands a BOOLEAN back as 't', MySQL a TINYINT(1) as 1 or
        // '1' depending on the driver's own settings.
        self::assertContains($this->text($active), ['1', 't', 'true']);
    }

    #[DataProvider('connections')]
    public function testEmailIsUniqueOnUsers(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        (new Migrator($connection))->migrate();

        $this->insertUser($connection, 'jana@example.com');

        $this->expectException(DbException::class);

        $this->insertUser($connection, 'jana@example.com');
    }

    /**
     * The same address may not name two accounts on either server.
     *
     * `UNIQUE (email)` alone does not give that: MySQL's default collation
     * compares without regard to case and refuses the variant, PostgreSQL's
     * does not and accepts it. Two rows for one human's address is not a
     * cosmetic difference — each carries its own roles and, until
     * `SqlAuthenticator` folded the case, its own throttle budget. So the
     * property is asserted as behaviour rather than as an index: what matters
     * is that both servers answer the same way, not how each one gets there.
     */
    #[DataProvider('connections')]
    public function testEmailIsUniqueOnUsersWithoutRegardToCase(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        (new Migrator($connection))->migrate();

        $this->insertUser($connection, 'jana@example.com');

        $this->expectException(DbException::class);

        $this->insertUser($connection, 'JANA@Example.com');
    }

    #[DataProvider('connections')]
    public function testLoginAttemptsAreIndexedByEmailAndByIp(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        (new Migrator($connection))->migrate();

        $columns = $this->indexedLeadingColumns($connection, 'ra_login_attempts');

        self::assertContains('email', $columns, 'No index leads with email.');
        self::assertContains('ip', $columns, 'No index leads with ip.');
    }

    /** Argon2id hashes are about 97 characters; bcrypt's 60 is not the bar. */
    #[DataProvider('connections')]
    public function testThePasswordColumnHoldsAnArgon2idHash(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        (new Migrator($connection))->migrate();

        $hash = password_hash('correct horse battery staple', PASSWORD_ARGON2ID);

        self::assertGreaterThan(60, \strlen($hash));

        $this->insertUser($connection, 'jana@example.com', $hash);

        self::assertSame(
            $hash,
            $connection->scalar(new Sql('SELECT password FROM ra_users WHERE email = ?', ['jana@example.com'])),
        );
    }

    /** Either driver may hand a column back as something other than a string. */
    private function text(mixed $value): string
    {
        if (!\is_scalar($value)) {
            $this->fail('Expected a scalar, got ' . get_debug_type($value));
        }

        return (string) $value;
    }

    private function write(string $name, string $sql): void
    {
        file_put_contents($this->scratch . '/' . $name, $sql);
    }

    private function insertUser(Connection $connection, string $email, string $password = 'x'): void
    {
        $connection->execute(new Sql(
            'INSERT INTO ra_users (email, password, name, is_active, roles, workspaces, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$email, $password, 'Jana', true, '[]', '[]', gmdate('Y-m-d H:i:s')],
        ));
    }

    /** @return list<string> */
    private function ledger(Connection $connection): array
    {
        $names = [];

        foreach ($connection->select(new Sql('SELECT name FROM ra_migrations ORDER BY name')) as $row) {
            $names[] = $this->text($row['name']);
        }

        return $names;
    }

    private function tableExists(Connection $connection, string $table): bool
    {
        try {
            $connection->columns($table);

            return true;
        } catch (DbException) {
            return false;
        }
    }

    /**
     * The first column of every index on a table, whichever server is
     * answering. MySQL keeps them in `information_schema.STATISTICS`,
     * PostgreSQL in `pg_indexes`, and neither can read the other's.
     *
     * @return list<string>
     */
    private function indexedLeadingColumns(Connection $connection, string $table): array
    {
        if ($connection->dialect()->name() === 'mysql') {
            $rows = $connection->select(new Sql(
                'SELECT COLUMN_NAME FROM information_schema.STATISTICS '
                    . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND SEQ_IN_INDEX = 1',
                [$table],
            ));

            return array_map(fn (array $row): string => $this->text($row['COLUMN_NAME']), $rows);
        }

        $rows = $connection->select(new Sql(
            'SELECT indexdef FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ?',
            [$table],
        ));

        $columns = [];

        foreach ($rows as $row) {
            if (preg_match('/\(\s*"?(\w+)/', $this->text($row['indexdef']), $match) === 1) {
                $columns[] = $match[1];
            }
        }

        return $columns;
    }

    private function dropEverywhere(): void
    {
        foreach (self::connections() as [$connection]) {
            if ($connection === null) {
                continue;
            }

            foreach (self::TABLES as $table) {
                $connection->execute(new Sql("DROP TABLE IF EXISTS {$table}"));
            }
        }
    }
}
