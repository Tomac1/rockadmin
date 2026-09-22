# RockAdmin Milestone 3 — Data Layer — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn a description of what a grid needs — an entity, some columns,
filters, a sort and a page — into one SQL statement that runs identically on
MySQL and PostgreSQL, with the structure that makes an N+1 query impossible.

**Architecture:** A `Dialect` holds every difference between the two databases
and nothing else. A `QueryBuilder` composes a `Sql` value object from small
description objects; it never executes. A `Connection` executes and never
composes. A `RowSource` puts the two together and is the seam a project
replaces when it wants its own reader.

**Tech Stack:** PHP 8.4, PDO, PHPUnit 11, PHPStan level max, PHP-CS-Fixer.
Integration tests run against real MySQL and PostgreSQL servers.

**Spec:** `docs/design/2026-09-21-rockadmin-design.md` — section 7, and 6.5 for
the database-backed enumerations deferred here from milestone 2.

## Global Constraints

- **PHP 8.4 minimum.**
- **Composer runtime dependencies are `php`, `ext-pdo`, `ext-json`,
  `ext-mbstring` and nothing else.** `ComposerConstraintsTest` fails the build
  otherwise.
- **No framework references.** Nothing in `src/` may mention Laravel, Symfony
  or Illuminate, including in comments.
- **`declare(strict_types=1);` in every PHP file.**
- **PHPStan at level max.** Never add `@phpstan-ignore`, a baseline entry, or
  an inline `@var` to silence an error. Four inherited from the milestone 2
  plan were tested by deletion: one was redundant, three were needed and carry
  a comment naming the error each answers. Do the same here.
- **PSR-12 via PHP-CS-Fixer**, short arrays, single quotes, ordered imports,
  trailing commas in multi-line calls.
- **Naming:** `PascalCase` classes, `camelCase` methods, `snake_case`
  configuration keys and database columns.
- **English everywhere.**
- **Namespaces:** `RockAdmin\` maps to `src/`, `RockAdmin\Tests\` to `tests/`.
- **Commit messages leave a blank line** between the subject and any trailers.
- **Verification before any completion claim:** run `composer run check`.

## Decisions this plan makes

**This milestone reads; it does not write.** Spec 7.6 (defaults for new rows)
and 7.7 (writes) belong to the form region in milestone 7, where something
actually saves a row. Building a `WriteHandler` now would be building against
an imagined caller.

**Nothing here reads page configuration.** Column, filter and entity schemas
arrive in milestone 6 with the regions that give them meaning. This milestone
takes small description objects built directly — `Entity`, `Query`, `Filter`,
`Sort`, `Page` — so it is testable today and milestone 6 only has to map
configuration onto them.

**A chained relation is declared, not inferred.** Spec 7.2 shows
`'source' => 'user.company.name'`. Rather than inferring that `company` is a
relation of whatever `user` points at, every step is declared by its full
path:

```php
'relations' => [
    'user' => ['table' => 'users', 'on' => 'users.id = ads.user_id'],
    'user.company' => ['table' => 'companies', 'on' => 'companies.id = users.company_id'],
],
```

The join order then falls out of the path's prefixes — join `user`, then
`user.company` — with nothing guessed. Guessing is how a schema with two
foreign keys to the same table silently joins the wrong one.

**A `Relation`'s `on` is raw SQL and is trusted because it comes from git.**
It is the one place in this layer where configuration becomes SQL text. It
must never be built from request input, and the docblock says so.

**Everything else is a bound parameter,** including JSON pointers. A JSON path
comes from configuration rather than a request, but binding it costs nothing
and removes the question.

**`count => cached` is refused, not silently downgraded.** Exact, estimate and
none ship here. A cached count needs a general-purpose cache store, which
arrives with the paths and the CLI in milestone 10; until then the refusal
names that milestone, the way `Enums` named milestone 3 for its own deferral.

**An estimate ignores filters, so filters override it.** A row estimate comes
from table metadata and knows nothing about a `WHERE`. Asking for an estimate
on a filtered query silently returns the whole table's size, which is worse
than being slow, so the builder falls back to an exact count when a filter or
a search is present.

**An unknown sort column is discarded, not refused.** Sorting comes from a
URL, so it is untrusted input; a filter or sort naming a column that is not
selected is dropped. An unknown *relation*, by contrast, comes from
configuration and is an error with a suggestion.

**Database-backed enumerations memoise per request.** Spec 6.5 shows
`'cache' => 300`. Cross-request caching needs the same store as the cached
count, so this milestone memoises within one request — which is what stops a
grid of fifty rows issuing fifty lookups — and the docblock says the TTL is
not yet honoured across requests.

## Test environment

Integration tests need real servers. They read connection details from the
environment and **skip** when it is absent, so `composer run check` stays green
on a machine without them.

```bash
export RA_TEST_MYSQL_DSN='mysql:host=127.0.0.1;port=3306;dbname=rockadmin_test'
export RA_TEST_MYSQL_USER=root
export RA_TEST_MYSQL_PASSWORD=root

export RA_TEST_PGSQL_DSN='pgsql:host=127.0.0.1;port=5432;dbname=rockadmin_test'
export RA_TEST_PGSQL_USER=postgres
export RA_TEST_PGSQL_PASSWORD=root
```

Both servers are up on this machine: MySQL 8.0.44 and PostgreSQL 17.7, each
with a `rockadmin_test` database. CI runs MariaDB 11 and PostgreSQL 17, so
between the two environments the MySQL dialect is exercised against both MySQL
and MariaDB — worth knowing when a difference between them surfaces.

Tests create their own tables, prefixed `ra_test_`, and drop them afterwards.
Point the variables at a throwaway database.

## File Structure

```
src/Db/
├── Sql.php                 a statement: text plus its bindings
├── DbException.php         every failure in this layer
├── Dialect.php             interface: everything the two databases spell differently
├── MySqlDialect.php
├── PgDialect.php
├── Connection.php          executes a Sql; never composes one
├── JoinType.php            enum: left, inner
├── Relation.php            a declared join
├── Entity.php              table, key, relations
├── SourcePath.php          parses user.company.name and stats->daily->views
├── FilterOperator.php      enum
├── Filter.php              column, operator, value
├── Search.php              a term across several columns
├── Sort.php                column and direction
├── SortDirection.php       enum
├── Page.php                offset paging or a keyset cursor
├── CountStrategy.php       enum: exact, cached, estimate, none
├── Collection.php          a one-to-many fetched in one supplementary query
├── Query.php               everything a grid asks for
├── QueryBuilder.php        Query -> Sql; never executes
├── Result.php              rows, total, and the statements that produced them
├── RowSource.php           interface a project replaces to read its own way
└── SqlRowSource.php        the default: build, execute, attach collections

src/Config/
└── Enums.php               modified: database-backed sources

tests/Unit/Db/              composition only, no database
tests/Integration/Db/       against both servers, skipped without the environment
tests/Support/DatabaseTestCase.php
```

Composition and execution stay apart on purpose: every SQL question can then
be answered by a unit test that runs in microseconds, and the integration
tests only have to prove the composed statement is accepted by a real server.

---

### Task 1: Sql, dialects, connection and the database harness

**Files:**
- Create: `src/Db/Sql.php`, `src/Db/DbException.php`, `src/Db/Dialect.php`,
  `src/Db/MySqlDialect.php`, `src/Db/PgDialect.php`, `src/Db/Connection.php`
- Create: `tests/Support/DatabaseTestCase.php`
- Test: `tests/Unit/Db/DialectTest.php`,
  `tests/Integration/Db/ConnectionTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `Sql` with readonly `string $text`, `list<mixed> $bindings`
  - `DbException extends RuntimeException`
  - `interface Dialect` with `name(): string`,
    `quoteIdentifier(string): string`, `qualify(string $table, string $column): string`,
    `caseInsensitiveLike(): string`, `jsonPath(string $expression, list<string> $path): Sql`,
    `estimatedCount(string $table): Sql`
  - `MySqlDialect`, `PgDialect`
  - `Connection::fromPdo(PDO): self`, `dialect(): Dialect`,
    `select(Sql): list<array<string, mixed>>`, `scalar(Sql): mixed`,
    `execute(Sql): int`
  - `RockAdmin\Tests\Support\DatabaseTestCase` with
    `public static function connections(): iterable<string, array{Connection}>`
    and fixture table management

- [ ] **Step 1: Write the failing unit test**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Db\Dialect;
use RockAdmin\Db\MySqlDialect;
use RockAdmin\Db\PgDialect;
use RockAdmin\Db\Sql;

#[CoversClass(MySqlDialect::class)]
#[CoversClass(PgDialect::class)]
#[CoversClass(Sql::class)]
final class DialectTest extends TestCase
{
    public function testNames(): void
    {
        $this->assertSame('mysql', (new MySqlDialect())->name());
        $this->assertSame('pgsql', (new PgDialect())->name());
    }

    public function testIdentifierQuoting(): void
    {
        $this->assertSame('`ads`', (new MySqlDialect())->quoteIdentifier('ads'));
        $this->assertSame('"ads"', (new PgDialect())->quoteIdentifier('ads'));
    }

    public function testAQuoteInsideAnIdentifierIsDoubled(): void
    {
        // Identifiers come from configuration, not from a request, but an
        // unescaped quote would still turn a typo into broken SQL.
        $this->assertSame('`we``ird`', (new MySqlDialect())->quoteIdentifier('we`ird'));
        $this->assertSame('"we""ird"', (new PgDialect())->quoteIdentifier('we"ird'));
    }

    public function testQualifying(): void
    {
        $this->assertSame('`ads`.`title`', (new MySqlDialect())->qualify('ads', 'title'));
        $this->assertSame('"ads"."title"', (new PgDialect())->qualify('ads', 'title'));
    }

    public function testCaseInsensitiveLike(): void
    {
        $this->assertSame('LIKE', (new MySqlDialect())->caseInsensitiveLike());
        $this->assertSame('ILIKE', (new PgDialect())->caseInsensitiveLike());
    }

    public function testJsonPathBindsThePointerRatherThanInliningIt(): void
    {
        $mysql = (new MySqlDialect())->jsonPath('`ads`.`stats`', ['daily', 'views']);

        $this->assertStringContainsString('JSON_EXTRACT(`ads`.`stats`, ?)', $mysql->text);
        $this->assertSame(['$."daily"."views"'], $mysql->bindings);

        $pgsql = (new PgDialect())->jsonPath('"ads"."stats"', ['daily', 'views']);

        $this->assertStringContainsString('"ads"."stats" #>> ?', $pgsql->text);
        $this->assertSame(['{daily,views}'], $pgsql->bindings);
    }

    public function testEstimatedCountBindsTheTableName(): void
    {
        foreach ([new MySqlDialect(), new PgDialect()] as $dialect) {
            $sql = $dialect->estimatedCount('ads');

            $this->assertSame(['ads'], $sql->bindings, $dialect->name());
            $this->assertStringNotContainsString("'ads'", $sql->text, $dialect->name());
        }
    }

    public function testSqlCarriesItsBindings(): void
    {
        $sql = new Sql('SELECT 1 WHERE x = ?', [42]);

        $this->assertSame('SELECT 1 WHERE x = ?', $sql->text);
        $this->assertSame([42], $sql->bindings);
        $this->assertSame([], (new Sql('SELECT 1'))->bindings);
    }

    public function testEveryDialectImplementsTheInterface(): void
    {
        $this->assertInstanceOf(Dialect::class, new MySqlDialect());
        $this->assertInstanceOf(Dialect::class, new PgDialect());
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Db/DialectTest.php`
Expected: FAIL — `Class "RockAdmin\Db\MySqlDialect" not found`.

- [ ] **Step 3: Write `Sql` and `DbException`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * A statement and the values bound into it.
 *
 * Text and bindings travel together because separating them is how a value
 * ends up concatenated into SQL "just this once".
 */
final class Sql
{
    /** @param list<mixed> $bindings */
    public function __construct(
        public readonly string $text,
        public readonly array $bindings = [],
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

use RuntimeException;

/** Anything wrong in the data layer. */
final class DbException extends RuntimeException
{
}
```

- [ ] **Step 4: Write `Dialect` and the two implementations**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * Everything MySQL and PostgreSQL spell differently, and nothing else.
 *
 * None of this may leak into configuration: the same page definition has to
 * run on both databases, so every difference is answered here or it becomes
 * the project's problem.
 */
interface Dialect
{
    /** Matches PDO's driver name, so a connection can pick its dialect. */
    public function name(): string;

    public function quoteIdentifier(string $name): string;

    public function qualify(string $table, string $column): string;

    /** The operator that compares text without regard to case. */
    public function caseInsensitiveLike(): string;

    /**
     * Reads a value out of a JSON column, as text.
     *
     * @param string       $expression an already-qualified column
     * @param list<string> $path       the keys to walk, outermost first
     */
    public function jsonPath(string $expression, array $path): Sql;

    /**
     * An approximate row count from table metadata — cheap, and wrong by
     * however much has changed since the last statistics update.
     */
    public function estimatedCount(string $table): Sql;
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

final class MySqlDialect implements Dialect
{
    public function name(): string
    {
        return 'mysql';
    }

    public function quoteIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    public function qualify(string $table, string $column): string
    {
        return $this->quoteIdentifier($table) . '.' . $this->quoteIdentifier($column);
    }

    public function caseInsensitiveLike(): string
    {
        // MySQL's default collations are case-insensitive, so LIKE already is.
        return 'LIKE';
    }

    public function jsonPath(string $expression, array $path): Sql
    {
        $pointer = '$' . implode('', array_map(
            static fn (string $key): string => '."' . str_replace('"', '\\"', $key) . '"',
            $path,
        ));

        return new Sql("JSON_UNQUOTE(JSON_EXTRACT({$expression}, ?))", [$pointer]);
    }

    public function estimatedCount(string $table): Sql
    {
        return new Sql(
            'SELECT TABLE_ROWS FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        );
    }
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

final class PgDialect implements Dialect
{
    public function name(): string
    {
        return 'pgsql';
    }

    public function quoteIdentifier(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    public function qualify(string $table, string $column): string
    {
        return $this->quoteIdentifier($table) . '.' . $this->quoteIdentifier($column);
    }

    public function caseInsensitiveLike(): string
    {
        return 'ILIKE';
    }

    public function jsonPath(string $expression, array $path): Sql
    {
        return new Sql("({$expression} #>> ?::text[])", ['{' . implode(',', $path) . '}']);
    }

    public function estimatedCount(string $table): Sql
    {
        return new Sql('SELECT reltuples::bigint FROM pg_class WHERE oid = to_regclass(?)', [$table]);
    }
}
```

- [ ] **Step 5: Write `Connection`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

use PDO;
use PDOException;

/**
 * Executes a Sql. It never composes one — that is the builder's job, and
 * keeping them apart is what lets every SQL question be answered by a unit
 * test that never opens a socket.
 */
final class Connection
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Dialect $dialect,
    ) {
    }

    /**
     * Picks the dialect from the driver and turns on exceptions.
     *
     * The error mode is set deliberately, even though the PDO belongs to the
     * host: with PDO's default, a failing statement returns false and the
     * failure surfaces later as a confusing type error somewhere else.
     */
    public static function fromPdo(PDO $pdo): self
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $dialect = match ($driver) {
            'mysql' => new MySqlDialect(),
            'pgsql' => new PgDialect(),
            default => throw new DbException(
                "Unsupported database driver '{$driver}'. RockAdmin speaks mysql and pgsql.",
            ),
        };

        return new self($pdo, $dialect);
    }

    public function dialect(): Dialect
    {
        return $this->dialect;
    }

    /** @return list<array<string, mixed>> */
    public function select(Sql $sql): array
    {
        $statement = $this->run($sql);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    public function scalar(Sql $sql): mixed
    {
        $value = $this->run($sql)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @return int rows affected */
    public function execute(Sql $sql): int
    {
        return $this->run($sql)->rowCount();
    }

    private function run(Sql $sql): \PDOStatement
    {
        try {
            $statement = $this->pdo->prepare($sql->text);
            $statement->execute($sql->bindings);

            return $statement;
        } catch (PDOException $e) {
            throw new DbException(
                "Query failed: {$e->getMessage()}" . PHP_EOL . $sql->text,
                0,
                $e,
            );
        }
    }
}
```

- [ ] **Step 6: Write the database harness**

```php
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

    /** Creates the fixture tables and fills them. Drops them first if present. */
    protected function createFixtures(Connection $connection): void
    {
        $this->dropFixtures($connection);

        $json = $connection->dialect()->name() === 'mysql' ? 'JSON' : 'JSONB';

        foreach ([
            "CREATE TABLE ra_test_companies (id INTEGER PRIMARY KEY, name VARCHAR(100) NOT NULL)",
            "CREATE TABLE ra_test_users (id INTEGER PRIMARY KEY, company_id INTEGER, "
                . 'name VARCHAR(100) NOT NULL, email VARCHAR(100))',
            "CREATE TABLE ra_test_ads (id INTEGER PRIMARY KEY, user_id INTEGER, "
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
```

- [ ] **Step 7: Write the integration test**

```php
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

        $this->assertSame('42', (string) $rows[0]['views']);

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
    public function testAFailingStatementRaisesDbExceptionCarryingTheSql(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->expectException(DbException::class);
        $this->expectExceptionMessage('ra_test_nonexistent');

        $connection->select(new Sql('SELECT * FROM ra_test_nonexistent'));
    }
}
```

- [ ] **Step 8: Run both test files, then the full check**

Run: `vendor/bin/phpunit tests/Unit/Db/ tests/Integration/Db/`, then `composer run check`.
Expected: PASS on both servers, then all green.

If the integration tests skip, the environment variables are not set — export
them as the plan's "Test environment" section shows and run again. A skipped
integration suite is not a passing one; say so in your report.

- [ ] **Step 9: Commit**

```bash
git add src/Db tests/Unit/Db tests/Integration/Db tests/Support
git commit -m "Add the SQL value object, dialects, connection and database harness"
```

---

### Task 2: Entity, relations and source paths

**Files:**
- Create: `src/Db/JoinType.php`, `src/Db/Relation.php`, `src/Db/Entity.php`,
  `src/Db/SourcePath.php`
- Test: `tests/Unit/Db/SourcePathTest.php`, `tests/Unit/Db/EntityTest.php`

**Interfaces:**
- Consumes: `DbException` from Task 1; `RockAdmin\Config\Schema::nearestOf(list<string>, string): ?string`.
- Produces:
  - `enum JoinType: string` with `Left`, `Inner`
  - `Relation` with readonly `string $name`, `string $table`, `string $on`, `JoinType $type`
  - `Entity` with readonly `string $table`, `string $key`, `array<string, Relation> $relations`;
    `relation(string): Relation`, `hasRelation(string): bool`
  - `SourcePath::parse(string): self` with readonly `?string $relation`,
    `string $column`, `list<string> $json`; `joins(): list<string>`

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Db\DbException;
use RockAdmin\Db\SourcePath;

#[CoversClass(SourcePath::class)]
final class SourcePathTest extends TestCase
{
    public function testAPlainColumnBelongsToTheEntitysOwnTable(): void
    {
        $path = SourcePath::parse('title');

        $this->assertNull($path->relation);
        $this->assertSame('title', $path->column);
        $this->assertSame([], $path->json);
        $this->assertSame([], $path->joins());
    }

    public function testASingleRelation(): void
    {
        $path = SourcePath::parse('user.name');

        $this->assertSame('user', $path->relation);
        $this->assertSame('name', $path->column);
        $this->assertSame(['user'], $path->joins());
    }

    public function testAChainedRelationNeedsEveryPrefixJoinedInOrder(): void
    {
        $path = SourcePath::parse('user.company.name');

        $this->assertSame('user.company', $path->relation);
        $this->assertSame('name', $path->column);
        $this->assertSame(['user', 'user.company'], $path->joins());
    }

    public function testJsonTraversalStaysInsideTheRow(): void
    {
        $path = SourcePath::parse('stats->daily->views');

        $this->assertNull($path->relation);
        $this->assertSame('stats', $path->column);
        $this->assertSame(['daily', 'views'], $path->json);
        $this->assertSame([], $path->joins(), 'JSON adds no join');
    }

    public function testARelationAndJsonTogether(): void
    {
        $path = SourcePath::parse('user.profile->locale');

        $this->assertSame('user', $path->relation);
        $this->assertSame('profile', $path->column);
        $this->assertSame(['locale'], $path->json);
        $this->assertSame(['user'], $path->joins());
    }

    /** @return array<string, array{string}> */
    public static function malformed(): array
    {
        return [
            'empty' => [''],
            'trailing dot' => ['user.'],
            'leading dot' => ['.name'],
            'double dot' => ['user..name'],
            'trailing arrow' => ['stats->'],
            'empty json key' => ['stats->->views'],
            'arrow before dot' => ['stats->daily.views'],
        ];
    }

    #[DataProvider('malformed')]
    public function testAMalformedPathIsRefused(string $source): void
    {
        $this->expectException(DbException::class);

        SourcePath::parse($source);
    }
}
```

```php
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
```

- [ ] **Step 2: Run them to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Db/SourcePathTest.php tests/Unit/Db/EntityTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write `JoinType` and `Relation`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

enum JoinType: string
{
    case Left = 'left';
    case Inner = 'inner';

    public function keyword(): string
    {
        return match ($this) {
            self::Left => 'LEFT JOIN',
            self::Inner => 'INNER JOIN',
        };
    }
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * A declared join.
 *
 * `$on` is raw SQL and is the one place in this layer where configuration
 * becomes SQL text rather than a bound value. That is deliberate — a join
 * condition cannot be expressed as a parameter — and it is safe only because
 * configuration lives in the project's repository. It must never be built
 * from anything that arrived in a request.
 */
final class Relation
{
    public function __construct(
        public readonly string $name,
        public readonly string $table,
        public readonly string $on,
        public readonly JoinType $type = JoinType::Left,
    ) {
    }
}
```

- [ ] **Step 4: Write `Entity`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

use RockAdmin\Config\Schema;

/**
 * What a grid reads from: one table, its key, and the joins it may follow.
 *
 * Relations are declared by their full path — 'user' and 'user.company' are
 * two entries, not a nesting. Inferring the second from the first would mean
 * guessing which foreign key to follow, and a schema with two foreign keys to
 * the same table would silently get the wrong one.
 */
final class Entity
{
    /** @param array<string, Relation> $relations keyed by the relation's own name */
    public function __construct(
        public readonly string $table,
        public readonly string $key = 'id',
        public readonly array $relations = [],
    ) {
        foreach ($relations as $name => $relation) {
            if ($name !== $relation->name) {
                throw new DbException(
                    "Relation '{$name}' of table '{$table}' calls itself '{$relation->name}'. "
                    . 'A source path refers to the key, so a mismatch makes it unreachable.',
                );
            }
        }
    }

    public function hasRelation(string $name): bool
    {
        return isset($this->relations[$name]);
    }

    public function relation(string $name): Relation
    {
        if (isset($this->relations[$name])) {
            return $this->relations[$name];
        }

        $nearest = Schema::nearestOf(array_keys($this->relations), $name);
        $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

        throw new DbException("Unknown relation '{$name}' on table '{$this->table}'.{$suffix}");
    }
}
```

- [ ] **Step 5: Write `SourcePath`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * Where a column's value comes from.
 *
 *   title                  a column of the entity's own table
 *   user.name              a column reached through one declared relation
 *   user.company.name      through two, each declared by its full path
 *   stats->daily->views    a value inside a JSON column of this row
 *   user.profile->locale   both
 *
 * The two separators are deliberately different. A dot adds a join; an arrow
 * stays inside the row. Spelling them the same would hide the cost of a
 * column.
 */
final class SourcePath
{
    /**
     * @param ?string      $relation the full relation path, or null for this table
     * @param list<string> $json     keys to walk inside the column
     */
    private function __construct(
        public readonly ?string $relation,
        public readonly string $column,
        public readonly array $json,
    ) {
    }

    public static function parse(string $source): self
    {
        $parts = explode('->', $source);
        $head = array_shift($parts);

        foreach ($parts as $key) {
            if ($key === '') {
                throw new DbException("Source path '{$source}' has an empty JSON key.");
            }
        }

        if (str_contains(implode('->', $parts), '.')) {
            throw new DbException(
                "Source path '{$source}' mixes a dot into its JSON keys. A dot adds a join, "
                . 'so it cannot appear after an arrow.',
            );
        }

        $segments = explode('.', $head);

        foreach ($segments as $segment) {
            if ($segment === '') {
                throw new DbException("Source path '{$source}' has an empty segment.");
            }
        }

        /** @var non-empty-list<string> $segments */
        $column = array_pop($segments);
        $relation = $segments === [] ? null : implode('.', $segments);

        return new self($relation, $column, array_values($parts));
    }

    /**
     * Every join this path needs, outermost first.
     *
     * 'user.company.name' needs 'user' before 'user.company', because the
     * second joins onto the table the first brought in.
     *
     * @return list<string>
     */
    public function joins(): array
    {
        if ($this->relation === null) {
            return [];
        }

        $joins = [];
        $prefix = '';

        foreach (explode('.', $this->relation) as $segment) {
            $prefix = $prefix === '' ? $segment : "{$prefix}.{$segment}";
            $joins[] = $prefix;
        }

        return $joins;
    }
}
```

- [ ] **Step 6: Run the tests, then the full check**

Run: `vendor/bin/phpunit tests/Unit/Db/`, then `composer run check`.
Expected: PASS, then all green.

- [ ] **Step 7: Commit**

```bash
git add src/Db/JoinType.php src/Db/Relation.php src/Db/Entity.php src/Db/SourcePath.php \
        tests/Unit/Db/SourcePathTest.php tests/Unit/Db/EntityTest.php
git commit -m "Add entities, declared relations and source paths"
```

---

### Task 3: Query builder — SELECT, FROM and JOIN

**Files:**
- Create: `src/Db/Query.php`, `src/Db/QueryBuilder.php`
- Test: `tests/Unit/Db/QueryBuilderSelectTest.php`,
  `tests/Integration/Db/QueryBuilderSelectTest.php`

**Interfaces:**
- Consumes: `Sql`, `Dialect`, `DbException` (Task 1); `Entity`, `Relation`,
  `JoinType`, `SourcePath` (Task 2).
- Produces:
  - `Query` with readonly `Entity $entity`, `array<string, string> $columns`
    (alias => source path); later tasks add more constructor parameters
  - `QueryBuilder::__construct(Dialect $dialect)` and `rows(Query $query): Sql`

Only the pieces this task needs exist yet; Tasks 4 to 6 extend `Query` and
`QueryBuilder` rather than replacing them.

- [ ] **Step 1: Write the failing unit test**

```php
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
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Db/QueryBuilderSelectTest.php`
Expected: FAIL — `Class "RockAdmin\Db\Query" not found`.

- [ ] **Step 3: Write `Query`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * Everything a grid asks the database for.
 *
 * A description, not a builder: it holds what was asked and knows nothing
 * about SQL. That is what lets the same description be answered by the
 * default SQL reader or by a project's own RowSource.
 */
final class Query
{
    /** @param array<string, string> $columns alias => source path */
    public function __construct(
        public readonly Entity $entity,
        public readonly array $columns,
    ) {
    }
}
```

- [ ] **Step 4: Write `QueryBuilder`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * Composes SQL. It never executes, and it never reads a request.
 *
 * Keeping composition separate from execution is what makes the SQL a grid
 * produces answerable by a unit test that never opens a socket, and it is why
 * the development console can show a statement before it runs.
 */
final class QueryBuilder
{
    public function __construct(private readonly Dialect $dialect)
    {
    }

    public function rows(Query $query): Sql
    {
        if ($query->columns === []) {
            throw new DbException(
                "A query on '{$query->entity->table}' needs at least one column.",
            );
        }

        $selects = [];
        $bindings = [];
        $joins = [];

        foreach ($query->columns as $alias => $source) {
            $path = SourcePath::parse($source);

            foreach ($path->joins() as $join) {
                $joins[$join] = true;
            }

            $expression = $this->expression($query->entity, $path);
            $bindings = [...$bindings, ...$expression->bindings];
            $selects[] = $expression->text . ' AS ' . $this->dialect->quoteIdentifier((string) $alias);
        }

        $text = 'SELECT ' . implode(', ', $selects)
            . ' FROM ' . $this->dialect->quoteIdentifier($query->entity->table)
            . $this->joins($query->entity, array_keys($joins));

        return new Sql($text, $bindings);
    }

    /**
     * The SQL expression a source path resolves to, with any bindings it needs.
     *
     * A relation's table is used as the qualifier because a relation is joined
     * once, under its own table name.
     */
    private function expression(Entity $entity, SourcePath $path): Sql
    {
        $table = $path->relation === null
            ? $entity->table
            : $entity->relation($path->relation)->table;

        $qualified = $this->dialect->qualify($table, $path->column);

        if ($path->json === []) {
            return new Sql($qualified);
        }

        return $this->dialect->jsonPath($qualified, $path->json);
    }

    /** @param list<string> $names relation names, already deduplicated and in order */
    private function joins(Entity $entity, array $names): string
    {
        $sql = '';

        foreach ($names as $name) {
            $relation = $entity->relation($name);

            $sql .= ' ' . $relation->type->keyword()
                . ' ' . $this->dialect->quoteIdentifier($relation->table)
                . ' ON ' . $relation->on;
        }

        return $sql;
    }
}
```

Note on join ordering: `$joins` is keyed by relation name and PHP preserves
insertion order, and `SourcePath::joins()` yields prefixes before full paths,
so a chained relation's parent is always inserted first.

- [ ] **Step 5: Write the integration test**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Entity;
use RockAdmin\Db\JoinType;
use RockAdmin\Db\Query;
use RockAdmin\Db\QueryBuilder;
use RockAdmin\Db\Relation;
use RockAdmin\Tests\Support\DatabaseTestCase;

#[CoversClass(QueryBuilder::class)]
final class QueryBuilderSelectTest extends DatabaseTestCase
{
    private function entity(): Entity
    {
        return new Entity('ra_test_ads', 'id', [
            'user' => new Relation('user', 'ra_test_users', 'ra_test_users.id = ra_test_ads.user_id'),
            'user.company' => new Relation(
                'user.company',
                'ra_test_companies',
                'ra_test_companies.id = ra_test_users.company_id',
                JoinType::Inner,
            ),
        ]);
    }

    #[DataProvider('connections')]
    public function testAChainedRelationAndAJsonColumnRunOnARealServer(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $sql = (new QueryBuilder($connection->dialect()))->rows(new Query($this->entity(), [
            'id' => 'id',
            'title' => 'title',
            'author' => 'user.name',
            'company' => 'user.company.name',
            'views' => 'stats->daily->views',
        ]));

        $rows = $connection->select($sql);

        $this->assertCount(3, $rows);

        $byId = array_column($rows, null, 'id');

        $this->assertSame('Jana', $byId[1]['author']);
        $this->assertSame('Velo s.r.o.', $byId[1]['company']);
        $this->assertSame('42', (string) $byId[1]['views']);
        $this->assertSame('Moto a.s.', $byId[3]['company']);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testOneJoinIsEmittedForSeveralColumnsOfTheSameRelation(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $sql = (new QueryBuilder($connection->dialect()))->rows(new Query($this->entity(), [
            'author' => 'user.name',
            'email' => 'user.email',
        ]));

        $this->assertSame(1, substr_count($sql->text, 'JOIN'));
        $this->assertCount(3, $connection->select($sql), 'a duplicated join would multiply the rows');

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testALeftJoinKeepsRowsWithoutARelatedRecord(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $connection->execute(new \RockAdmin\Db\Sql(
            'INSERT INTO ra_test_ads (id, user_id, title) VALUES (?, ?, ?)',
            [4, null, 'Orphan'],
        ));

        $sql = (new QueryBuilder($connection->dialect()))->rows(
            new Query($this->entity(), ['id' => 'id', 'author' => 'user.name']),
        );

        $this->assertCount(4, $connection->select($sql), 'the orphan survives a left join');

        $this->dropFixtures($connection);
    }
}
```

- [ ] **Step 6: Run both test files, then the full check**

Run: `vendor/bin/phpunit tests/Unit/Db/ tests/Integration/Db/`, then `composer run check`.
Expected: PASS on both servers, then all green.

- [ ] **Step 7: Commit**

```bash
git add src/Db/Query.php src/Db/QueryBuilder.php tests/Unit/Db/QueryBuilderSelectTest.php \
        tests/Integration/Db/QueryBuilderSelectTest.php
git commit -m "Compose the select list and deduplicated joins"
```

---

### Task 4: Query builder — WHERE

**Files:**
- Create: `src/Db/FilterOperator.php`, `src/Db/Filter.php`, `src/Db/Search.php`
- Modify: `src/Db/Query.php` (add `$scope`, `$filters`, `$search`),
  `src/Db/QueryBuilder.php` (add the WHERE clause)
- Test: `tests/Unit/Db/QueryBuilderWhereTest.php`,
  `tests/Integration/Db/QueryBuilderWhereTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1 to 3; `RockAdmin\Config\Placeholder`.
- Produces:
  - `enum FilterOperator: string` — `Equals`, `NotEquals`, `Contains`,
    `StartsWith`, `EndsWith`, `GreaterThan`, `GreaterOrEqual`, `LessThan`,
    `LessOrEqual`, `Between`, `In`, `IsNull`, `IsNotNull`
  - `Filter` with readonly `string $column`, `FilterOperator $operator`, `mixed $value`
  - `Search` with readonly `string $term`, `list<string> $columns`
  - `Query` gains `array<string, mixed> $scope`, `list<Filter> $filters`, `?Search $search`

- [ ] **Step 1: Write the failing unit test**

```php
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

    /** @param array<string, string> $columns */
    private function query(array $columns, mixed ...$rest): Query
    {
        return new Query($this->entity(), $columns, ...$rest);
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
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Db/QueryBuilderWhereTest.php`
Expected: FAIL — `Class "RockAdmin\Db\Filter" not found`.

- [ ] **Step 3: Write `FilterOperator`, `Filter` and `Search`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * The comparisons a filter may make.
 *
 * An enum rather than a string, because an operator that arrives from a URL
 * as free text is one concatenation away from being a SQL injection.
 */
enum FilterOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case Contains = 'contains';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case GreaterThan = 'gt';
    case GreaterOrEqual = 'gte';
    case LessThan = 'lt';
    case LessOrEqual = 'lte';
    case Between = 'between';
    case In = 'in';
    case IsNull = 'is_null';
    case IsNotNull = 'is_not_null';
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/** One condition, naming a column by the alias the page selected it under. */
final class Filter
{
    public function __construct(
        public readonly string $column,
        public readonly FilterOperator $operator,
        public readonly mixed $value = null,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/** One term looked for across several columns at once. */
final class Search
{
    /** @param list<string> $columns aliases to look in */
    public function __construct(
        public readonly string $term,
        public readonly array $columns,
    ) {
    }
}
```

- [ ] **Step 4: Extend `Query`**

```php
    /**
     * @param array<string, string> $columns alias => source path
     * @param array<string, mixed>  $scope   column of the entity's own table => value
     * @param list<Filter>          $filters
     */
    public function __construct(
        public readonly Entity $entity,
        public readonly array $columns,
        public readonly array $scope = [],
        public readonly array $filters = [],
        public readonly ?Search $search = null,
    ) {
    }
```

- [ ] **Step 5: Extend `QueryBuilder`**

Replace `rows()` and add the private helpers below. The select-list loop is
unchanged except that it now records each alias's expression for filters to
reuse.

```php
    public function rows(Query $query): Sql
    {
        if ($query->columns === []) {
            throw new DbException("A query on '{$query->entity->table}' needs at least one column.");
        }

        $selects = [];
        $bindings = [];
        $joins = [];

        /** @var array<string, string> $expressions alias => SQL expression, for filters to reuse */
        $expressions = [];

        foreach ($query->columns as $alias => $source) {
            $path = SourcePath::parse($source);

            foreach ($path->joins() as $join) {
                $joins[$join] = true;
            }

            $expression = $this->expression($query->entity, $path);
            $bindings = [...$bindings, ...$expression->bindings];
            $expressions[(string) $alias] = $expression->text;
            $selects[] = $expression->text . ' AS ' . $this->dialect->quoteIdentifier((string) $alias);
        }

        $where = $this->where($query, $expressions);
        $bindings = [...$bindings, ...$where->bindings];

        $text = 'SELECT ' . implode(', ', $selects)
            . ' FROM ' . $this->dialect->quoteIdentifier($query->entity->table)
            . $this->joins($query->entity, array_keys($joins))
            . ($where->text === '' ? '' : ' WHERE ' . $where->text);

        return new Sql($text, $bindings);
    }

    /**
     * Scope first, then filters, then search — all joined with AND.
     *
     * Scope is applied to the entity's own table and is never reachable from a
     * filter, because it is the boundary a workspace draws. A filter naming a
     * column the page did not select is dropped: filters arrive from a URL.
     *
     * @param array<string, string> $expressions alias => SQL expression
     */
    private function where(Query $query, array $expressions): Sql
    {
        $conditions = [];
        $bindings = [];

        foreach ($query->scope as $column => $value) {
            $this->assertBound($value, $column);

            $conditions[] = $this->dialect->qualify($query->entity->table, $column) . ' = ?';
            $bindings[] = $value;
        }

        foreach ($query->filters as $filter) {
            if (!isset($expressions[$filter->column])) {
                continue;
            }

            $condition = $this->condition($expressions[$filter->column], $filter);
            $conditions[] = $condition->text;
            $bindings = [...$bindings, ...$condition->bindings];
        }

        if ($query->search !== null && $query->search->term !== '') {
            $alternatives = [];

            foreach ($query->search->columns as $alias) {
                if (!isset($expressions[$alias])) {
                    continue;
                }

                $alternatives[] = $expressions[$alias] . ' ' . $this->dialect->caseInsensitiveLike() . ' ?';
                $bindings[] = '%' . $this->escapeLike($query->search->term) . '%';
            }

            if ($alternatives !== []) {
                $conditions[] = '(' . implode(' OR ', $alternatives) . ')';
            }
        }

        return new Sql(implode(' AND ', $conditions), $bindings);
    }

    private function condition(string $expression, Filter $filter): Sql
    {
        $like = $this->dialect->caseInsensitiveLike();

        return match ($filter->operator) {
            FilterOperator::Equals => new Sql("{$expression} = ?", [$filter->value]),
            FilterOperator::NotEquals => new Sql("{$expression} <> ?", [$filter->value]),
            FilterOperator::GreaterThan => new Sql("{$expression} > ?", [$filter->value]),
            FilterOperator::GreaterOrEqual => new Sql("{$expression} >= ?", [$filter->value]),
            FilterOperator::LessThan => new Sql("{$expression} < ?", [$filter->value]),
            FilterOperator::LessOrEqual => new Sql("{$expression} <= ?", [$filter->value]),
            FilterOperator::Contains => new Sql(
                "{$expression} {$like} ?",
                ['%' . $this->escapeLike($this->text($filter)) . '%'],
            ),
            FilterOperator::StartsWith => new Sql(
                "{$expression} {$like} ?",
                [$this->escapeLike($this->text($filter)) . '%'],
            ),
            FilterOperator::EndsWith => new Sql(
                "{$expression} {$like} ?",
                ['%' . $this->escapeLike($this->text($filter))],
            ),
            FilterOperator::IsNull => new Sql("{$expression} IS NULL"),
            FilterOperator::IsNotNull => new Sql("{$expression} IS NOT NULL"),
            FilterOperator::Between => $this->between($expression, $filter),
            FilterOperator::In => $this->in($expression, $filter),
        };
    }

    private function between(string $expression, Filter $filter): Sql
    {
        if (!\is_array($filter->value) || \count($filter->value) !== 2) {
            throw new DbException(
                "A 'between' filter on '{$filter->column}' needs exactly two values.",
            );
        }

        return new Sql("{$expression} BETWEEN ? AND ?", array_values($filter->value));
    }

    private function in(string $expression, Filter $filter): Sql
    {
        if (!\is_array($filter->value)) {
            throw new DbException("An 'in' filter on '{$filter->column}' needs a list of values.");
        }

        $values = array_values($filter->value);

        if ($values === []) {
            // "IN ()" is a syntax error, and dropping the condition would make
            // an empty selection match every row — the opposite of the ask.
            return new Sql('1 = 0');
        }

        return new Sql(
            $expression . ' IN (' . implode(', ', array_fill(0, \count($values), '?')) . ')',
            $values,
        );
    }

    private function text(Filter $filter): string
    {
        if (!\is_scalar($filter->value)) {
            throw new DbException(
                "A text filter on '{$filter->column}' needs a scalar, got "
                . get_debug_type($filter->value) . '.',
            );
        }

        return (string) $filter->value;
    }

    /** Escapes the wildcards LIKE understands, so a term matches literally. */
    private function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    private function assertBound(mixed $value, string $column): void
    {
        if ($value instanceof \RockAdmin\Config\Placeholder) {
            throw new DbException(
                "The scope on '{$column}' is still {$value}. A workspace or user placeholder "
                . 'must be bound to the request before it reaches a query, so that it arrives '
                . 'as a value rather than as text.',
            );
        }
    }
```

- [ ] **Step 6: Write the integration test**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Filter;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\Query;
use RockAdmin\Db\QueryBuilder;
use RockAdmin\Db\Relation;
use RockAdmin\Db\Search;
use RockAdmin\Tests\Support\DatabaseTestCase;

#[CoversClass(QueryBuilder::class)]
final class QueryBuilderWhereTest extends DatabaseTestCase
{
    private function entity(): Entity
    {
        return new Entity('ra_test_ads', 'id', [
            'user' => new Relation('user', 'ra_test_users', 'ra_test_users.id = ra_test_ads.user_id'),
        ]);
    }

    #[DataProvider('connections')]
    public function testContainsMatchesIrrespectiveOfCaseOnBothServers(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $sql = (new QueryBuilder($connection->dialect()))->rows(new Query(
            $this->entity(),
            ['id' => 'id', 'title' => 'title'],
            [],
            [new Filter('title', FilterOperator::Contains, 'KOLO')],
        ));

        $this->assertCount(2, $connection->select($sql));

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAWildcardInTheTermMatchesLiterally(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $connection->execute(new \RockAdmin\Db\Sql(
            'INSERT INTO ra_test_ads (id, user_id, title) VALUES (?, ?, ?)',
            [5, 1, 'Sleva 50% dnes'],
        ));

        $builder = new QueryBuilder($connection->dialect());

        $literal = $builder->rows(new Query(
            $this->entity(),
            ['id' => 'id'],
            [],
            [new Filter('title', FilterOperator::Contains, '50%')],
        ));

        $this->assertCount(1, $connection->select($literal), 'the percent is a character, not a wildcard');

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAFilterThroughARelation(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $sql = (new QueryBuilder($connection->dialect()))->rows(new Query(
            $this->entity(),
            ['id' => 'id', 'author' => 'user.name'],
            [],
            [new Filter('author', FilterOperator::Equals, 'Petr')],
        ));

        $rows = $connection->select($sql);

        $this->assertCount(1, $rows);
        $this->assertSame('Petr', $rows[0]['author']);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testSearchAcrossTwoColumns(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $sql = (new QueryBuilder($connection->dialect()))->rows(new Query(
            $this->entity(),
            ['id' => 'id', 'title' => 'title', 'author' => 'user.name'],
            [],
            [],
            new Search('Petr', ['title', 'author']),
        ));

        $this->assertCount(1, $connection->select($sql), 'matched on the author, not the title');

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAnEmptyInReturnsNothing(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $sql = (new QueryBuilder($connection->dialect()))->rows(new Query(
            $this->entity(),
            ['id' => 'id'],
            [],
            [new Filter('id', FilterOperator::In, [])],
        ));

        $this->assertSame([], $connection->select($sql));

        $this->dropFixtures($connection);
    }
}
```

- [ ] **Step 7: Run both test files, then the full check**

Run: `vendor/bin/phpunit tests/Unit/Db/ tests/Integration/Db/`, then `composer run check`.
Expected: PASS, then all green.

- [ ] **Step 8: Commit**

```bash
git add src/Db tests/Unit/Db tests/Integration/Db
git commit -m "Compose the where clause from scope, filters and search"
```

---

### Task 5: Query builder — ORDER BY, LIMIT and pagination

**Files:**
- Create: `src/Db/SortDirection.php`, `src/Db/Sort.php`, `src/Db/Page.php`
- Modify: `src/Db/Query.php` (add `$sort`, `$page`), `src/Db/QueryBuilder.php`
- Test: `tests/Unit/Db/QueryBuilderOrderTest.php`,
  `tests/Integration/Db/QueryBuilderOrderTest.php`

**Interfaces:**
- Consumes: Tasks 1 to 4.
- Produces:
  - `enum SortDirection: string` — `Asc`, `Desc`
  - `Sort` with readonly `string $column`, `SortDirection $direction`
  - `Page::of(int $number, int $perPage): self`,
    `Page::after(mixed $key, int $perPage): self`, readonly `int $limit`,
    `int $offset`, `mixed $after`, `isKeyset(): bool`
  - `Query` gains `list<Sort> $sort`, `?Page $page`

- [ ] **Step 1: Write the failing unit test**

```php
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
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Db/QueryBuilderOrderTest.php`
Expected: FAIL — `Class "RockAdmin\Db\Sort" not found`.

- [ ] **Step 3: Write `SortDirection`, `Sort` and `Page`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';

    public function keyword(): string
    {
        return match ($this) {
            self::Asc => 'ASC',
            self::Desc => 'DESC',
        };
    }
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/** One ordering, naming a column by the alias the page selected it under. */
final class Sort
{
    public function __construct(
        public readonly string $column,
        public readonly SortDirection $direction = SortDirection::Asc,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * Which slice of the result to read.
 *
 * Offset paging is what a numbered pager needs. Keyset paging walks backwards
 * from a cursor and stays fast on page 100 000, where an offset makes the
 * database count past everything before it — but it can only offer "next",
 * which is why both exist.
 */
final class Page
{
    private function __construct(
        public readonly int $limit,
        public readonly int $offset,
        public readonly mixed $after,
        private readonly bool $keyset,
    ) {
    }

    public static function of(int $number, int $perPage): self
    {
        self::assertPerPage($perPage);

        $number = max(1, $number);

        return new self($perPage, ($number - 1) * $perPage, null, false);
    }

    /** @param mixed $key the last key of the previous page, or null to start */
    public static function after(mixed $key, int $perPage): self
    {
        self::assertPerPage($perPage);

        return new self($perPage, 0, $key, true);
    }

    public function isKeyset(): bool
    {
        return $this->keyset;
    }

    private static function assertPerPage(int $perPage): void
    {
        if ($perPage < 1) {
            throw new DbException("A page needs at least one row, got {$perPage}.");
        }
    }
}
```

- [ ] **Step 4: Extend `Query`**

Add to the constructor, after `$search`:

```php
        /** @param list<Sort> $sort */
        public readonly array $sort = [],
        public readonly ?Page $page = null,
```

- [ ] **Step 5: Extend `QueryBuilder`**

In `rows()`, after the `WHERE` is assembled, add the cursor condition, then
the order and the limit. The cursor is part of the `WHERE`, so it must be
folded in before the clause is rendered:

```php
        $where = $this->where($query, $expressions);
        $conditions = $where->text === '' ? [] : [$where->text];
        $bindings = [...$bindings, ...$where->bindings];

        if ($query->page?->isKeyset() === true && $query->page->after !== null) {
            $conditions[] = $this->dialect->qualify($query->entity->table, $query->entity->key) . ' < ?';
            $bindings[] = $query->page->after;
        }

        $text = 'SELECT ' . implode(', ', $selects)
            . ' FROM ' . $this->dialect->quoteIdentifier($query->entity->table)
            . $this->joins($query->entity, array_keys($joins))
            . ($conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions))
            . $this->order($query, $expressions)
            . $this->limit($query->page);

        return new Sql($text, $bindings);
```

and the two helpers:

```php
    /**
     * Keyset paging orders by the key and nothing else: a cursor is only
     * meaningful against the order it was taken from, so honouring another
     * sort alongside it would silently skip rows.
     *
     * @param array<string, string> $expressions alias => SQL expression
     */
    private function order(Query $query, array $expressions): string
    {
        if ($query->page?->isKeyset() === true) {
            return ' ORDER BY '
                . $this->dialect->qualify($query->entity->table, $query->entity->key) . ' DESC';
        }

        $parts = [];

        foreach ($query->sort as $sort) {
            if (isset($expressions[$sort->column])) {
                $parts[] = $expressions[$sort->column] . ' ' . $sort->direction->keyword();
            }
        }

        return $parts === [] ? '' : ' ORDER BY ' . implode(', ', $parts);
    }

    /**
     * The limit is interpolated rather than bound, which is safe because both
     * values are integers this class produced — and necessary, because several
     * databases refuse a placeholder in LIMIT.
     */
    private function limit(?Page $page): string
    {
        if ($page === null) {
            return '';
        }

        return " LIMIT {$page->limit}" . ($page->offset > 0 ? " OFFSET {$page->offset}" : '');
    }
```

- [ ] **Step 6: Write the integration test**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Page;
use RockAdmin\Db\Query;
use RockAdmin\Db\QueryBuilder;
use RockAdmin\Db\Sort;
use RockAdmin\Db\SortDirection;
use RockAdmin\Tests\Support\DatabaseTestCase;

#[CoversClass(QueryBuilder::class)]
final class QueryBuilderOrderTest extends DatabaseTestCase
{
    #[DataProvider('connections')]
    public function testSortingAndPagingWalkTheWholeTableExactlyOnce(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $builder = new QueryBuilder($connection->dialect());
        $entity = new Entity('ra_test_ads', 'id');
        $seen = [];

        foreach ([1, 2] as $number) {
            $sql = $builder->rows(new Query(
                $entity,
                ['id' => 'id'],
                [],
                [],
                null,
                [new Sort('id', SortDirection::Asc)],
                Page::of($number, 2),
            ));

            foreach ($connection->select($sql) as $row) {
                $seen[] = (int) $row['id'];
            }
        }

        $this->assertSame([1, 2, 3], $seen, 'every row once, in order');

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testKeysetPagingWalksBackwardsFromTheCursor(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $builder = new QueryBuilder($connection->dialect());
        $entity = new Entity('ra_test_ads', 'id');

        $first = $connection->select($builder->rows(new Query(
            $entity,
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            Page::after(null, 2),
        )));

        $this->assertSame([3, 2], array_map(static fn (array $r): int => (int) $r['id'], $first));

        $second = $connection->select($builder->rows(new Query(
            $entity,
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            Page::after((int) $first[1]['id'], 2),
        )));

        $this->assertSame([1], array_map(static fn (array $r): int => (int) $r['id'], $second));

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testSortingThroughARelation(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $entity = new Entity('ra_test_ads', 'id', [
            'user' => new \RockAdmin\Db\Relation(
                'user',
                'ra_test_users',
                'ra_test_users.id = ra_test_ads.user_id',
            ),
        ]);

        $sql = (new QueryBuilder($connection->dialect()))->rows(new Query(
            $entity,
            ['id' => 'id', 'author' => 'user.name'],
            [],
            [],
            null,
            [new Sort('author', SortDirection::Desc), new Sort('id', SortDirection::Asc)],
        ));

        $authors = array_column($connection->select($sql), 'author');

        $this->assertSame(['Petr', 'Jana', 'Jana'], $authors);

        $this->dropFixtures($connection);
    }
}
```

- [ ] **Step 7: Run both test files, then the full check**

Run: `vendor/bin/phpunit tests/Unit/Db/ tests/Integration/Db/`, then `composer run check`.
Expected: PASS, then all green.

- [ ] **Step 8: Commit**

```bash
git add src/Db tests/Unit/Db tests/Integration/Db
git commit -m "Add ordering, offset paging and keyset paging"
```

---

### Task 6: Counting strategies

**Files:**
- Create: `src/Db/CountStrategy.php`
- Modify: `src/Db/Query.php` (add `$count`), `src/Db/QueryBuilder.php` (add `count()`)
- Test: `tests/Unit/Db/QueryBuilderCountTest.php`,
  `tests/Integration/Db/QueryBuilderCountTest.php`

**Interfaces:**
- Consumes: Tasks 1 to 5.
- Produces:
  - `enum CountStrategy: string` — `Exact`, `Cached`, `Estimate`, `None`
  - `Query` gains `CountStrategy $count` (default `Exact`)
  - `QueryBuilder::count(Query $query): ?Sql` — null when no count is wanted

- [ ] **Step 1: Write the failing unit test**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Db\CountStrategy;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Filter;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\MySqlDialect;
use RockAdmin\Db\Page;
use RockAdmin\Db\Query;
use RockAdmin\Db\QueryBuilder;
use RockAdmin\Db\Relation;
use RockAdmin\Db\Sort;
use RockAdmin\Db\SortDirection;

#[CoversClass(QueryBuilder::class)]
#[CoversClass(CountStrategy::class)]
final class QueryBuilderCountTest extends TestCase
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

    public function testExactCountsOverTheSameFromAndWhere(): void
    {
        $sql = $this->builder()->count(new Query(
            $this->entity(),
            ['id' => 'id', 'author' => 'user.name'],
            ['site_id' => 7],
            [new Filter('author', FilterOperator::Equals, 'Jana')],
            null,
            [],
            null,
            CountStrategy::Exact,
        ));

        $this->assertNotNull($sql);
        $this->assertStringStartsWith('SELECT COUNT(*) FROM `ads`', $sql->text);
        $this->assertStringContainsString('LEFT JOIN `users`', $sql->text);
        $this->assertStringContainsString('`ads`.`site_id` = ?', $sql->text);
        $this->assertSame([7, 'Jana'], $sql->bindings);
    }

    public function testACountHasNoSelectListOrderOrLimit(): void
    {
        $sql = $this->builder()->count(new Query(
            $this->entity(),
            ['views' => 'stats->daily->views'],
            [],
            [],
            null,
            [new Sort('views', SortDirection::Desc)],
            Page::of(3, 50),
        ));

        $this->assertNotNull($sql);
        $this->assertStringNotContainsString('ORDER BY', $sql->text);
        $this->assertStringNotContainsString('LIMIT', $sql->text);
        $this->assertSame([], $sql->bindings, 'the select list bindings are gone with it');
    }

    public function testNoneAsksForNoCountAtAll(): void
    {
        $this->assertNull($this->builder()->count(new Query(
            $this->entity(),
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            null,
            CountStrategy::None,
        )));
    }

    public function testEstimateUsesTableMetadata(): void
    {
        $sql = $this->builder()->count(new Query(
            $this->entity(),
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            null,
            CountStrategy::Estimate,
        ));

        $this->assertNotNull($sql);
        $this->assertStringContainsString('information_schema', $sql->text);
        $this->assertSame(['ads'], $sql->bindings);
    }

    public function testEstimateFallsBackToExactWhenAFilterNarrowsTheResult(): void
    {
        // A metadata estimate counts the whole table. Returning it for a
        // filtered query would report a number with no relation to the result.
        $sql = $this->builder()->count(new Query(
            $this->entity(),
            ['title' => 'title'],
            [],
            [new Filter('title', FilterOperator::Contains, 'kolo')],
            null,
            [],
            null,
            CountStrategy::Estimate,
        ));

        $this->assertNotNull($sql);
        $this->assertStringStartsWith('SELECT COUNT(*)', $sql->text);
        $this->assertSame(['%kolo%'], $sql->bindings);
    }

    public function testEstimateAlsoFallsBackForAScopeOrASearch(): void
    {
        $scoped = $this->builder()->count(new Query(
            $this->entity(),
            ['id' => 'id'],
            ['site_id' => 7],
            [],
            null,
            [],
            null,
            CountStrategy::Estimate,
        ));

        $searched = $this->builder()->count(new Query(
            $this->entity(),
            ['title' => 'title'],
            [],
            [],
            new \RockAdmin\Db\Search('kolo', ['title']),
            [],
            null,
            CountStrategy::Estimate,
        ));

        $this->assertNotNull($scoped);
        $this->assertNotNull($searched);
        $this->assertStringStartsWith('SELECT COUNT(*)', $scoped->text);
        $this->assertStringStartsWith('SELECT COUNT(*)', $searched->text);
    }

    public function testCachedIsRefusedRatherThanSilentlyDowngraded(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('milestone 10');

        $this->builder()->count(new Query(
            $this->entity(),
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            null,
            CountStrategy::Cached,
        ));
    }

    public function testExactIsTheDefault(): void
    {
        $sql = $this->builder()->count(new Query($this->entity(), ['id' => 'id']));

        $this->assertNotNull($sql);
        $this->assertStringStartsWith('SELECT COUNT(*)', $sql->text);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Db/QueryBuilderCountTest.php`
Expected: FAIL — `Class "RockAdmin\Db\CountStrategy" not found`.

- [ ] **Step 3: Write `CountStrategy`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * How hard to work for the total row count.
 *
 * COUNT(*) over ten million rows is usually the most expensive thing on a
 * page, and a pager only needs it to draw the last page number — so this is a
 * choice a project makes per grid, not a fixed cost.
 */
enum CountStrategy: string
{
    /** One COUNT(*) over the same conditions. Correct, and the default. */
    case Exact = 'exact';

    /** Not yet available: it needs a cache store, which arrives in milestone 10. */
    case Cached = 'cached';

    /** From table metadata — cheap, approximate, and only valid unfiltered. */
    case Estimate = 'estimate';

    /** No count at all: the pager offers next and previous, not a last page. */
    case None = 'none';
}
```

- [ ] **Step 4: Extend `Query`**

Add to the constructor, after `$page`:

```php
        public readonly CountStrategy $count = CountStrategy::Exact,
```

- [ ] **Step 5: Add `count()` to `QueryBuilder`**

```php
    /**
     * The statement that yields the total, or null when none was asked for.
     *
     * An estimate reads table metadata, which knows nothing about a WHERE, so
     * a query that narrows its result falls back to an exact count rather than
     * reporting the size of the whole table.
     */
    public function count(Query $query): ?Sql
    {
        if ($query->count === CountStrategy::None) {
            return null;
        }

        if ($query->count === CountStrategy::Cached) {
            throw new DbException(
                "A cached count needs a cache store, which arrives in milestone 10. "
                . "Use 'exact', 'estimate' or 'none' until then.",
            );
        }

        if ($query->count === CountStrategy::Estimate && !$this->isNarrowed($query)) {
            return $this->dialect->estimatedCount($query->entity->table);
        }

        $joins = [];

        foreach ($query->columns as $source) {
            foreach (SourcePath::parse($source)->joins() as $join) {
                $joins[$join] = true;
            }
        }

        $where = $this->where($query, $this->columnExpressions($query, $joins));

        return new Sql(
            'SELECT COUNT(*) FROM ' . $this->dialect->quoteIdentifier($query->entity->table)
            . $this->joins($query->entity, array_keys($joins))
            . ($where->text === '' ? '' : ' WHERE ' . $where->text),
            $where->bindings,
        );
    }

    private function isNarrowed(Query $query): bool
    {
        return $query->scope !== []
            || $query->filters !== []
            || ($query->search !== null && $query->search->term !== '');
    }
```

The select-list expressions are needed by `where()` but not by the count
itself, so extract the loop that builds them out of `rows()` into a shared
private method and have both call it:

```php
    /**
     * alias => SQL expression, plus the joins those expressions need.
     *
     * @param  array<string, bool>  $joins collected by reference, keyed to deduplicate
     * @return array<string, string>
     */
    private function columnExpressions(Query $query, array &$joins): array
    {
        $expressions = [];

        foreach ($query->columns as $alias => $source) {
            $path = SourcePath::parse($source);

            foreach ($path->joins() as $join) {
                $joins[$join] = true;
            }

            $expressions[(string) $alias] = $this->expression($query->entity, $path)->text;
        }

        return $expressions;
    }
```

Note that `count()` discards the select list's own bindings — a JSON pointer
is bound for the select list, and a count has no select list — which is what
`testACountHasNoSelectListOrderOrLimit` pins.

- [ ] **Step 6: Write the integration test**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Db\Connection;
use RockAdmin\Db\CountStrategy;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Filter;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\Query;
use RockAdmin\Db\QueryBuilder;
use RockAdmin\Tests\Support\DatabaseTestCase;

#[CoversClass(QueryBuilder::class)]
final class QueryBuilderCountTest extends DatabaseTestCase
{
    #[DataProvider('connections')]
    public function testAnExactCountMatchesTheRowsReturned(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $builder = new QueryBuilder($connection->dialect());
        $query = new Query(
            new Entity('ra_test_ads', 'id'),
            ['id' => 'id', 'title' => 'title'],
            [],
            [new Filter('title', FilterOperator::Contains, 'kolo')],
        );

        $rows = $connection->select($builder->rows($query));
        $count = $builder->count($query);

        $this->assertNotNull($count);
        $this->assertSame(\count($rows), (int) $connection->scalar($count));
        $this->assertSame(2, (int) $connection->scalar($count));

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testACountIgnoresPagingSoItReportsTheWholeResult(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $builder = new QueryBuilder($connection->dialect());
        $query = new Query(
            new Entity('ra_test_ads', 'id'),
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            \RockAdmin\Db\Page::of(1, 1),
        );

        $this->assertCount(1, $connection->select($builder->rows($query)));

        $count = $builder->count($query);
        $this->assertNotNull($count);
        $this->assertSame(3, (int) $connection->scalar($count));

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAnEstimateRunsOnBothServers(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $count = (new QueryBuilder($connection->dialect()))->count(new Query(
            new Entity('ra_test_ads', 'id'),
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            null,
            CountStrategy::Estimate,
        ));

        $this->assertNotNull($count);
        // Approximate by definition; this proves the statement is accepted.
        $this->assertIsNumeric($connection->scalar($count));

        $this->dropFixtures($connection);
    }
}
```

- [ ] **Step 7: Run both test files, then the full check**

Run: `vendor/bin/phpunit tests/Unit/Db/ tests/Integration/Db/`, then `composer run check`.
Expected: PASS, then all green.

- [ ] **Step 8: Commit**

```bash
git add src/Db tests/Unit/Db tests/Integration/Db
git commit -m "Add counting strategies"
```

---

### Task 7: RowSource and the one-to-many supplementary fetch

**Files:**
- Create: `src/Db/Collection.php`, `src/Db/Result.php`, `src/Db/RowSource.php`,
  `src/Db/SqlRowSource.php`
- Modify: `src/Db/Query.php` (add `$collections`)
- Test: `tests/Integration/Db/SqlRowSourceTest.php`

**Interfaces:**
- Consumes: Tasks 1 to 6.
- Produces:
  - `Collection` with readonly `string $alias`, `string $table`,
    `string $foreignKey`, `string $column`
  - `Result` with readonly `list<array<string, mixed>> $rows`, `?int $total`,
    `list<Sql> $statements`
  - `interface RowSource` with `fetch(Query $query): Result`
  - `SqlRowSource::__construct(Connection $connection)`

This is where the N+1 guarantee is made good: a one-to-many relation is read
with **one** supplementary query for the whole page, never one per row. The
test asserts the statement count, which is what would catch a regression.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Db\Collection;
use RockAdmin\Db\Connection;
use RockAdmin\Db\CountStrategy;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Page;
use RockAdmin\Db\Query;
use RockAdmin\Db\Result;
use RockAdmin\Db\RowSource;
use RockAdmin\Db\Sort;
use RockAdmin\Db\SortDirection;
use RockAdmin\Db\SqlRowSource;
use RockAdmin\Tests\Support\DatabaseTestCase;

#[CoversClass(SqlRowSource::class)]
#[CoversClass(Result::class)]
#[CoversClass(Collection::class)]
final class SqlRowSourceTest extends DatabaseTestCase
{
    #[DataProvider('connections')]
    public function testItReturnsRowsAndATotal(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $result = (new SqlRowSource($connection))->fetch(new Query(
            new Entity('ra_test_ads', 'id'),
            ['id' => 'id', 'title' => 'title'],
            [],
            [],
            null,
            [new Sort('id', SortDirection::Asc)],
            Page::of(1, 2),
        ));

        $this->assertInstanceOf(Result::class, $result);
        $this->assertCount(2, $result->rows, 'the page holds two');
        $this->assertSame(3, $result->total, 'the total counts them all');
        $this->assertCount(2, $result->statements, 'one for the rows, one for the count');

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testNoCountMeansNoTotalAndNoSecondStatement(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $result = (new SqlRowSource($connection))->fetch(new Query(
            new Entity('ra_test_ads', 'id'),
            ['id' => 'id'],
            [],
            [],
            null,
            [],
            null,
            CountStrategy::None,
        ));

        $this->assertNull($result->total);
        $this->assertCount(1, $result->statements);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAOneToManyCostsExactlyOneExtraQueryForTheWholePage(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $result = (new SqlRowSource($connection))->fetch(new Query(
            new Entity('ra_test_ads', 'id'),
            ['id' => 'id', 'title' => 'title'],
            [],
            [],
            null,
            [new Sort('id', SortDirection::Asc)],
            null,
            CountStrategy::None,
            [new Collection('tags', 'ra_test_tags', 'ad_id', 'label')],
        ));

        $this->assertCount(
            2,
            $result->statements,
            'one for the rows and one for every tag on the page — never one per row',
        );

        $byId = array_column($result->rows, null, 'id');

        $this->assertSame(['bazar', 'sleva'], $byId[1]['tags']);
        $this->assertSame([], $byId[2]['tags'], 'a row with no children gets an empty list');
        $this->assertSame(['novinka'], $byId[3]['tags']);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAnEmptyPageSkipsTheSupplementaryQueryAltogether(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $result = (new SqlRowSource($connection))->fetch(new Query(
            new Entity('ra_test_ads', 'id'),
            ['id' => 'id'],
            ['id' => -1],
            [],
            null,
            [],
            null,
            CountStrategy::None,
            [new Collection('tags', 'ra_test_tags', 'ad_id', 'label')],
        ));

        $this->assertSame([], $result->rows);
        $this->assertCount(1, $result->statements, 'nothing to fetch children for');

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testItIsARowSource(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->assertInstanceOf(RowSource::class, new SqlRowSource($connection));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Db/SqlRowSourceTest.php`
Expected: FAIL — `Class "RockAdmin\Db\Collection" not found`.

- [ ] **Step 3: Write `Collection`, `Result` and `RowSource`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * A one-to-many read in one supplementary query for the whole page.
 *
 * Joining it into the main query would multiply the rows; reading it per row
 * would be the N+1 this layer exists to prevent. So it is fetched once for
 * every key on the page and grouped in PHP.
 */
final class Collection
{
    /**
     * @param string $alias      the key the collected values appear under on each row
     * @param string $table      the child table
     * @param string $foreignKey the child column pointing at the parent's key
     * @param string $column     the child column to collect
     */
    public function __construct(
        public readonly string $alias,
        public readonly string $table,
        public readonly string $foreignKey,
        public readonly string $column,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * What a read produced, and how.
 *
 * The statements travel with the rows so the development console can show
 * exactly what ran, which is the spec's requirement that a developer can see
 * how a grid's queries were composed.
 */
final class Result
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param ?int                       $total null when no count was asked for
     * @param list<Sql>                  $statements in the order they ran
     */
    public function __construct(
        public readonly array $rows,
        public readonly ?int $total,
        public readonly array $statements,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * Reads rows for a query.
 *
 * The seam a project replaces when it wants its own reader — an ORM, a search
 * index, a remote API. The core only ever asks for "the rows matching this
 * description", which is why the replacement does not have to speak SQL.
 */
interface RowSource
{
    public function fetch(Query $query): Result;
}
```

- [ ] **Step 4: Extend `Query`**

Add to the constructor, after `$count`:

```php
        /** @param list<Collection> $collections */
        public readonly array $collections = [],
```

- [ ] **Step 5: Write `SqlRowSource`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/** The default reader: build, execute, attach collections. */
final class SqlRowSource implements RowSource
{
    private readonly QueryBuilder $builder;

    public function __construct(private readonly Connection $connection)
    {
        $this->builder = new QueryBuilder($connection->dialect());
    }

    public function fetch(Query $query): Result
    {
        $rowsSql = $this->builder->rows($query);
        $rows = $this->connection->select($rowsSql);
        $statements = [$rowsSql];

        $total = null;
        $countSql = $this->builder->count($query);

        if ($countSql !== null) {
            $statements[] = $countSql;
            $total = (int) $this->connection->scalar($countSql);
        }

        foreach ($query->collections as $collection) {
            $rows = $this->attach($rows, $collection, $query->entity->key, $statements);
        }

        return new Result($rows, $total, $statements);
    }

    /**
     * One query for every child of every row on this page, grouped in PHP.
     *
     * @param  list<array<string, mixed>> $rows
     * @param  list<Sql>                  $statements collected as they run
     * @return list<array<string, mixed>>
     */
    private function attach(array $rows, Collection $collection, string $key, array &$statements): array
    {
        $keys = [];

        foreach ($rows as $row) {
            if (\array_key_exists($key, $row) && $row[$key] !== null) {
                $keys[] = $row[$key];
            }
        }

        if ($keys === []) {
            // Nothing to attach to, and "IN ()" is a syntax error anyway.
            return array_map(
                static fn (array $row): array => [...$row, $collection->alias => []],
                $rows,
            );
        }

        $dialect = $this->connection->dialect();
        $sql = new Sql(
            'SELECT ' . $dialect->qualify($collection->table, $collection->foreignKey) . ' AS ra_key, '
            . $dialect->qualify($collection->table, $collection->column) . ' AS ra_value'
            . ' FROM ' . $dialect->quoteIdentifier($collection->table)
            . ' WHERE ' . $dialect->qualify($collection->table, $collection->foreignKey)
            . ' IN (' . implode(', ', array_fill(0, \count($keys), '?')) . ')',
            $keys,
        );

        $statements[] = $sql;

        /** @var array<string, list<mixed>> $grouped */
        $grouped = [];

        foreach ($this->connection->select($sql) as $child) {
            $grouped[(string) $child['ra_key']][] = $child['ra_value'];
        }

        return array_map(
            static fn (array $row): array => [
                ...$row,
                $collection->alias => $grouped[(string) ($row[$key] ?? '')] ?? [],
            ],
            $rows,
        );
    }
}
```

Note that the collection's key column has to be selected for this to work —
the row's key is read from the row. A query that asks for a collection
without selecting the entity's key gets empty lists, which the test for the
empty page covers by selecting `id`.

- [ ] **Step 6: Run the test, then the full check**

Run: `vendor/bin/phpunit tests/Integration/Db/`, then `composer run check`.
Expected: PASS, then all green.

- [ ] **Step 7: Commit**

```bash
git add src/Db tests/Integration/Db
git commit -m "Add the row source and the one-to-many supplementary fetch"
```

---

### Task 8: Database-backed enumerations

**Files:**
- Modify: `src/Config/Enums.php`
- Create: `src/Config/EnumSource.php`
- Test: `tests/Unit/Config/EnumsTest.php` (extend),
  `tests/Integration/Db/DatabaseEnumsTest.php`

**Interfaces:**
- Consumes: `Connection`, `Sql` (Task 1); `ConfigException`, `EnumOption`,
  `EnumReference` (milestone 2).
- Produces:
  - `EnumSource` with readonly `string $table`, `string $value`,
    `string $label`, `?string $order`, `?int $cache`
  - `Enums::fromConfig()` accepts a `source` definition instead of refusing it
  - `Enums::withConnection(Connection $connection): self`
  - `Enums::options()` resolves a database-backed enumeration, memoised

Milestone 2 refused a `source` definition with a message naming this
milestone. That refusal now becomes support — and the message it used is what
the test for it asserted, so that test changes here.

- [ ] **Step 1: Write the failing tests**

Replace `testADatabaseBackedEnumerationIsRefusedForNow` and
`testADatabaseBackedEnumerationIsRefusedEvenWithANullSource` in
`tests/Unit/Config/EnumsTest.php` with:

```php
    public function testADatabaseBackedEnumerationIsAccepted(): void
    {
        $enums = Enums::fromConfig([
            'categories' => [
                'source' => ['table' => 'categories', 'value' => 'id', 'label' => 'name'],
            ],
        ]);

        $this->assertTrue($enums->has('categories'));
    }

    public function testADatabaseBackedEnumerationNeedsAConnectionToResolve(): void
    {
        $enums = Enums::fromConfig([
            'categories' => [
                'source' => ['table' => 'categories', 'value' => 'id', 'label' => 'name'],
            ],
        ]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('categories');

        $enums->options('categories');
    }

    public function testASourceMissingItsTableIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('table');

        Enums::fromConfig(['categories' => ['source' => ['value' => 'id', 'label' => 'name']]]);
    }

    public function testASourceThatIsNotAnArrayIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('categories');

        Enums::fromConfig(['categories' => ['source' => 'categories']]);
    }
```

and add the integration test:

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Config\Enums;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Sql;
use RockAdmin\Tests\Support\DatabaseTestCase;

#[CoversClass(Enums::class)]
final class DatabaseEnumsTest extends DatabaseTestCase
{
    private function enums(Connection $connection): Enums
    {
        return Enums::fromConfig([
            'companies' => [
                'source' => [
                    'table' => 'ra_test_companies',
                    'value' => 'id',
                    'label' => 'name',
                    'order' => 'name',
                ],
            ],
        ])->withConnection($connection);
    }

    #[DataProvider('connections')]
    public function testOptionsComeFromTheTableInTheOrderAsked(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $options = $this->enums($connection)->options('companies');

        $this->assertSame(['2', '1'], array_keys($options), 'ordered by name: Moto, then Velo');
        $this->assertSame('Moto a.s.', $options['2']->label);
        $this->assertSame('Velo s.r.o.', $options['1']->label);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testTheTableIsReadOnceHoweverOftenTheOptionsAreAsked(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $enums = $this->enums($connection);
        $enums->options('companies');

        $connection->execute(new Sql('INSERT INTO ra_test_companies (id, name) VALUES (?, ?)', [3, 'Aaa']));

        $this->assertCount(
            2,
            $enums->options('companies'),
            'memoised for the request: a grid of fifty rows must not read the table fifty times',
        );

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAStaticEnumerationStillWorksWithAConnectionAttached(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $enums = Enums::fromConfig(['state' => ['active' => ['label' => 'Active']]])
            ->withConnection($connection);

        $this->assertSame('Active', $enums->options('state')['active']->label);
    }
}
```

- [ ] **Step 2: Run them to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Config/EnumsTest.php tests/Integration/Db/DatabaseEnumsTest.php`
Expected: FAIL — the unit tests fail because a source is still refused; the
integration test fails because `withConnection()` does not exist.

- [ ] **Step 3: Write `EnumSource`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * An enumeration whose options live in a table.
 *
 * `cache` is accepted because the spec describes it, but this milestone only
 * memoises within one request — which is what stops a grid of fifty rows
 * reading the table fifty times. Caching across requests needs the same store
 * as a cached row count, which arrives in milestone 10.
 */
final class EnumSource
{
    public function __construct(
        public readonly string $table,
        public readonly string $value,
        public readonly string $label,
        public readonly ?string $order = null,
        public readonly ?int $cache = null,
    ) {
    }

    /** @param array<string, mixed> $definition */
    public static function fromConfig(string $enum, array $definition): self
    {
        foreach (['table', 'value', 'label'] as $required) {
            if (!\is_string($definition[$required] ?? null) || $definition[$required] === '') {
                throw new ConfigException(
                    "Enumeration '{$enum}' reads from the database and needs a "
                    . "non-empty '{$required}'.",
                );
            }
        }

        $order = $definition['order'] ?? null;
        $cache = $definition['cache'] ?? null;

        /** @var array{table: string, value: string, label: string} $definition */
        return new self(
            $definition['table'],
            $definition['value'],
            $definition['label'],
            \is_string($order) ? $order : null,
            \is_int($cache) ? $cache : null,
        );
    }

    /**
     * @param array{table: string, value: string, label: string, order: string|null, cache: int|null} $data
     */
    public static function __set_state(array $data): self
    {
        return new self(
            $data['table'],
            $data['value'],
            $data['label'],
            $data['order'],
            $data['cache'],
        );
    }
}
```

- [ ] **Step 4: Extend `Enums`**

**Read `src/Config/Enums.php` in full before editing it.** This step changes an
existing class rather than writing a new one, and the fragments below are the
shape to reach, not a file to paste over what is there.

Four things change and the static path is otherwise untouched:

1. the constructor and `__set_state()` carry a second array, `$sources`, beside
   the parsed static options — `__set_state()` must read both keys or a cached
   configuration loses its database-backed enumerations;
2. `fromConfig()` parses a `source` definition instead of refusing it;
3. a connection can be attached;
4. `options()` resolves a source, memoised.

The rule that decides whether a definition is database-backed stays exactly as
milestone 2 settled it — a lone `source` entry whose value is an array or null
— and a non-array source is refused by name:

```php
    /**
     * @param  array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private static function source(string $enum, array $definition): array
    {
        $source = $definition['source'] ?? null;

        if (!\is_array($source)) {
            throw new ConfigException(
                "Enumeration '{$enum}' has a 'source' that is not an array, got "
                . get_debug_type($source) . '. A database-backed enumeration needs a '
                . "table, a value column and a label column.",
            );
        }

        /** @var array<string, mixed> $source */
        return $source;
    }
```

```php
    /** @var array<string, array<string, EnumOption>> */
    private array $memoised = [];

    // in fromConfig(), where the milestone-3 refusal was:
    if (self::isDatabaseBacked($definition)) {
        $sources[(string) $key] = EnumSource::fromConfig(
            (string) $key,
            self::source((string) $key, $definition),
        );

        continue;
    }

    /** Returns a copy that can read its database-backed enumerations. */
    public function withConnection(Connection $connection): self
    {
        $copy = new self($this->enums, $this->sources);
        $copy->connection = $connection;

        return $copy;
    }

    // in options(), before the unknown-enumeration error:
    if (isset($this->sources[$key])) {
        return $this->memoised[$key] ??= $this->read($key, $this->sources[$key]);
    }

    /** @return array<string, EnumOption> */
    private function read(string $key, EnumSource $source): array
    {
        if ($this->connection === null) {
            throw new ConfigException(
                "Enumeration '{$key}' reads its options from the database, but no connection "
                . 'was given. Call withConnection() before resolving it.',
            );
        }

        $dialect = $this->connection->dialect();

        $sql = 'SELECT ' . $dialect->qualify($source->table, $source->value) . ' AS ra_value, '
            . $dialect->qualify($source->table, $source->label) . ' AS ra_label'
            . ' FROM ' . $dialect->quoteIdentifier($source->table)
            . ($source->order === null
                ? ''
                : ' ORDER BY ' . $dialect->qualify($source->table, $source->order));

        $options = [];

        foreach ($this->connection->select(new Sql($sql)) as $row) {
            $value = (string) $row['ra_value'];
            $options[$value] = new EnumOption($value, (string) $row['ra_label']);
        }

        return $options;
    }
```

`isDatabaseBacked()` keeps the rule milestone 2 settled — a lone `source`
entry whose value is an array or null — and `source()` returns it as an array,
refusing a non-array with a message naming the enumeration.

- [ ] **Step 5: Run the tests, then the full check**

Run: `vendor/bin/phpunit tests/Unit/Config/ tests/Integration/Db/`, then `composer run check`.
Expected: PASS, then all green.

- [ ] **Step 6: Update the changelog**

Under `## [Unreleased]` / `### Added`:

```markdown
- Data layer: MySQL and PostgreSQL dialects, a query builder composing one
  statement from declared relations and source paths, filters, search,
  ordering, offset and keyset paging, counting strategies, a row source whose
  one-to-many reads cost one query per page, and enumerations that read their
  options from a table.
```

- [ ] **Step 7: Commit**

```bash
git add src/Config src/Db tests CHANGELOG.md
git commit -m "Read enumeration options from the database"
```

---

## Milestone acceptance

Each verified by running the command and reading its output:

1. `composer run check` passes with both servers configured, and the
   integration tests run rather than skip — a skipped integration suite proves
   nothing.
2. `composer show --tree` lists no runtime dependency beyond PHP extensions.
3. The same `Query` produces working SQL on MySQL and on PostgreSQL, proven by
   every integration test running twice.
4. A grid of rows with three joined columns and one one-to-many issues three
   statements in total: rows, count, collection. `SqlRowSourceTest` asserts
   the count.
5. A filter or sort naming a column the page did not select changes nothing.
6. A `Placeholder` reaching a scope is refused by name rather than
   stringified.

## What this milestone deliberately leaves out

- Writes, and defaults for new rows — milestone 7, with the form region
- Page, column and filter schemas that produce these objects from
  configuration — milestone 6
- Binding `{{workspace.*}}` and `{{user.*}}` to a request — milestone 5
- A cached row count and cross-request enumeration caching — milestone 10,
  with the cache store
- The development console that displays `Result::$statements` — milestone 10
- Row-level permissions, which will attach to the same `WHERE` — later
- The other two escape hatches in spec 7.4 — a `query` callback that adjusts a
  built query, and an explicitly dangerous `raw` condition. Both are shaped by
  how configuration reaches the builder, which is milestone 6; designing them
  now would be designing against an imagined caller. The third, a custom
  `RowSource`, ships here because it is an interface rather than a syntax.

## Amendments made during execution

Written after the fact. The plan above is what was dispatched; this section
records where reality differed, so a reader comparing plan to branch is not
left guessing.

**The integration harness could not skip.** The plan promised that a machine
with no database configured would skip the integration tests. It would not
have: a PHPUnit data provider that yields nothing makes the test an *error*,
and a `setUp()` guard never runs because providers are collected first.
`DatabaseTestCase`'s provider now yields a single null case when nothing is
configured, every provider-driven test takes `?Connection`, and
`requireConnection()` skips on that sentinel. A DSN that is set but
unreachable still fails loudly — configuration claiming a server exists is a
claim worth failing on.

**A test that proved nothing.** The plan's test for "a failed statement
carries its SQL" matched on the table name, which both MySQL and PostgreSQL
already put in their own error text; it passed with the append deleted. It now
asserts on a marker only the appended SQL can contain. The PostgreSQL leg
still passes without the append, because PDO's pgsql driver echoes
`LINE 1: <query>` — the behaviour is pinned by the MySQL leg. Anyone tempted
to level the MySQL assertion down to match PostgreSQL would silently retire
the only test that proves the wrapping.

**Three assertions in the plan were impossible or wrong.** A wildcard
integration test filtered on a column the page did not select, so the discard
rule dropped the filter — fixed by selecting the column, not by weakening the
rule. A `mixed ...$rest` spread in a unit helper fails PHPStan at level max.
And `assertSame(['2', '1'], array_keys($options))` can never pass, because PHP
coerces a numeric-string key to an integer unconditionally.

**JSON keys are escaped.** A key containing a comma, brace, quote or backslash
produced a wrong path rather than failing. The dialects now escape it.

**Malformed source paths are distinguishable.** The plan's tests asserted only
the exception class, so the three guards could have been merged or reordered
with no test noticing. Each now carries its own message.

**`Enums::__set_state()` carries `$sources`.** Added beyond the plan, with a
test that a database-backed enumeration survives the config cache round trip —
the existing cache test only exercised static enumerations, so a cached
configuration could have lost every database-backed enumeration silently.

**Column expressions carry their bindings.** The largest amendment, and the
one defect that survived every per-task review. `columnExpressions()` returned
a map of alias to expression *text*, discarding the bound parameters. A JSON
column carries its pointer as a bound parameter, never as inlined SQL — so the
moment such a column was filtered, sorted, searched or counted, the statement
had one more placeholder than it had values. The map now carries `Sql` objects
(text plus bindings) to all of its consumers, in placeholder order. This also
retired the double-parsing inefficiency the plan's Task 6 extraction left in
`rows()`.

Three further defects were fixed in the same wave:

- A page that aliases a collection's key differently from its entity key used
  to produce a silently empty collection. It is now an error, refused in
  `Query`'s constructor — the only place that sees both the collection and the
  entity it hangs off.
- `count()` joined every selected column's relations rather than only the ones
  its own conditions use. A to-many join inflates `COUNT(*)` past the row
  count, which reads as a data problem rather than a query problem.
- PostgreSQL reports `reltuples = -1` for a never-analysed table, which
  `SqlRowSource` turned into a negative total and a pager with negative page
  numbers. The estimate is clamped at zero.
