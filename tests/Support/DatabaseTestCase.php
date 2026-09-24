<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Support;

use PDO;
use PHPUnit\Framework\TestCase;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Sql;

/**
 * Base for tests that need a real database.
 *
 * Every such test runs once per available server. A server whose environment
 * variables are absent is skipped rather than failing, so the suite stays
 * green on a machine that has neither.
 */
abstract class DatabaseTestCase extends TestCase
{
    /** @var array<string, Connection> */
    private static array $connections = [];

    /**
     * One case per configured server, or a single null case when there are none.
     *
     * A data provider that yields nothing makes PHPUnit report an error, not a
     * skip — so a contributor with no database would see a red suite. The null
     * case exists to give requireConnection() something to skip on.
     *
     * @return iterable<string, array{?Connection}>
     */
    public static function connections(): iterable
    {
        $any = false;

        foreach (['mysql' => 'MYSQL', 'pgsql' => 'PGSQL'] as $driver => $prefix) {
            $connection = self::connect($prefix);

            if ($connection !== null) {
                $any = true;

                yield $driver => [$connection];
            }
        }

        if (!$any) {
            yield 'no database configured' => [null];
        }
    }

    /** Skips the test when this case is the no-database sentinel. */
    protected function requireConnection(?Connection $connection): Connection
    {
        if ($connection === null) {
            $this->markTestSkipped(
                'No database configured. Set RA_TEST_MYSQL_DSN or RA_TEST_PGSQL_DSN.',
            );
        }

        return $connection;
    }

    private static function connect(string $prefix): ?Connection
    {
        if (isset(self::$connections[$prefix])) {
            return self::$connections[$prefix];
        }

        $dsn = getenv("RA_TEST_{$prefix}_DSN");

        if (!\is_string($dsn) || $dsn === '') {
            return null;
        }

        $user = getenv("RA_TEST_{$prefix}_USER");
        $password = getenv("RA_TEST_{$prefix}_PASSWORD");

        $pdo = new PDO(
            $dsn,
            \is_string($user) ? $user : null,
            \is_string($password) ? $password : null,
        );

        return self::$connections[$prefix] = Connection::fromPdo($pdo);
    }

    /**
     * Creates the fixture tables and fills them. Drops them first if present.
     *
     * Every one of these tables declares its key as a plain, non-generated
     * `INTEGER PRIMARY KEY`, and every insert below supplies it explicitly.
     * That is a property of these particular fixtures, not a constraint
     * `SqlWriteHandler` imposes elsewhere -- `WriteHandler::insert()` also
     * supports a generated key (`AUTO_INCREMENT` / `GENERATED ... AS
     * IDENTITY`), which is the far more common case for a real project's own
     * tables. See `tests/Integration/Db/WriteTest.php`, which exercises both.
     */
    protected function createFixtures(Connection $connection): void
    {
        $this->dropFixtures($connection);

        $json = $connection->dialect()->name() === 'mysql' ? 'JSON' : 'JSONB';

        foreach ([
            'CREATE TABLE ra_test_companies (id INTEGER PRIMARY KEY, name VARCHAR(100) NOT NULL)',
            'CREATE TABLE ra_test_users (id INTEGER PRIMARY KEY, company_id INTEGER, '
                . 'name VARCHAR(100) NOT NULL, email VARCHAR(100))',
            'CREATE TABLE ra_test_ads (id INTEGER PRIMARY KEY, user_id INTEGER, '
                . "title VARCHAR(200) NOT NULL, price INTEGER, state VARCHAR(20), stats {$json})",
            'CREATE TABLE ra_test_tags (id INTEGER PRIMARY KEY, ad_id INTEGER, label VARCHAR(50))',
        ] as $ddl) {
            $connection->execute(new Sql($ddl));
        }

        foreach ([
            ['INSERT INTO ra_test_companies (id, name) VALUES (?, ?)', [1, 'Velo s.r.o.']],
            ['INSERT INTO ra_test_companies (id, name) VALUES (?, ?)', [2, 'Moto a.s.']],
            ['INSERT INTO ra_test_users (id, company_id, name, email) VALUES (?, ?, ?, ?)',
                [1, 1, 'Jana', 'jana@example.com']],
            ['INSERT INTO ra_test_users (id, company_id, name, email) VALUES (?, ?, ?, ?)',
                [2, 2, 'Petr', 'petr@example.com']],
            ['INSERT INTO ra_test_ads (id, user_id, title, price, state, stats) VALUES (?, ?, ?, ?, ?, ?)',
                [1, 1, 'Horské kolo', 12000, 'active', '{"daily":{"views":42}}']],
            ['INSERT INTO ra_test_ads (id, user_id, title, price, state, stats) VALUES (?, ?, ?, ?, ?, ?)',
                [2, 1, 'Silniční kolo', 25000, 'draft', '{"daily":{"views":7}}']],
            ['INSERT INTO ra_test_ads (id, user_id, title, price, state, stats) VALUES (?, ?, ?, ?, ?, ?)',
                [3, 2, 'Skútr', 30000, 'active', '{"daily":{"views":13}}']],
            ['INSERT INTO ra_test_tags (id, ad_id, label) VALUES (?, ?, ?)', [1, 1, 'bazar']],
            ['INSERT INTO ra_test_tags (id, ad_id, label) VALUES (?, ?, ?)', [2, 1, 'sleva']],
            ['INSERT INTO ra_test_tags (id, ad_id, label) VALUES (?, ?, ?)', [3, 3, 'novinka']],
        ] as [$text, $bindings]) {
            $connection->execute(new Sql($text, $bindings));
        }
    }

    protected function dropFixtures(Connection $connection): void
    {
        foreach (['ra_test_tags', 'ra_test_ads', 'ra_test_users', 'ra_test_companies'] as $table) {
            $connection->execute(new Sql("DROP TABLE IF EXISTS {$table}"));
        }
    }
}
