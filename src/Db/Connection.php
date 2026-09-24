<?php

declare(strict_types=1);

namespace RockAdmin\Db;

use Closure;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * Executes a Sql. It never composes one — that is the builder's job, and
 * keeping them apart is what lets every SQL question be answered by a unit
 * test that never opens a socket.
 */
final class Connection
{
    /**
     * Zero outside any `transaction()` call; the nesting depth of the
     * outermost one currently running on this connection while inside it.
     * See `transaction()`.
     */
    private int $transactionDepth = 0;

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
        $driverName = \is_string($driver) ? $driver : get_debug_type($driver);

        $dialect = match ($driverName) {
            'mysql' => new MySqlDialect(),
            'pgsql' => new PgDialect(),
            default => throw new DbException(
                "Unsupported database driver '{$driverName}'. RockAdmin speaks mysql and pgsql.",
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

        /** @var list<array<string, mixed>> $rows narrows PDO's array — return.type without it */
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

    /**
     * The key the database assigned to the row inserted last on this
     * connection -- MySQL's own way of answering "what key did that INSERT
     * get". PostgreSQL is asked through a `RETURNING` clause instead
     * (`Dialect::returningClause()`), because its `lastInsertId()` needs a
     * sequence name to be reliable and is fragile without one.
     */
    public function lastInsertId(): string
    {
        $id = $this->pdo->lastInsertId();

        return $id === false ? '' : $id;
    }

    /**
     * The table's own columns, in their declared order — what `'fields' =>
     * '@all'` (spec 8.5) reads from. The statement itself is the one thing
     * that differs per server, and `Dialect::columns()` already carries that
     * difference; MySQL's `SHOW COLUMNS` names the column 'Field',
     * PostgreSQL's `information_schema.columns` names it 'column_name', so
     * both are read here rather than making a caller guess which ran.
     *
     * A table that does not exist is refused here rather than left to
     * answer for itself: MySQL's `SHOW COLUMNS` already throws for one, but
     * PostgreSQL's `information_schema.columns` simply matches no rows, so
     * without this check the same typo in `entity.table` fails loudly on
     * one driver and silently produces a preview with no fields at all on
     * the other -- the two-behaviours-from-one-configuration rule 3
     * forbids.
     *
     * @return list<string>
     */
    public function columns(string $table): array
    {
        $names = [];

        foreach ($this->select($this->dialect->columns($table)) as $row) {
            $name = $row['column_name'] ?? $row['Field'] ?? null;

            if (\is_string($name)) {
                $names[] = $name;
            }
        }

        if ($names === []) {
            // Every table that exists has at least one column, so an empty
            // result means this connection cannot see one -- the same fact
            // MySQL's own SHOW COLUMNS already raised as an exception before
            // this method got a chance to run.
            //
            // The message hedges deliberately. PostgreSQL's
            // information_schema.columns hides rows the connecting role has
            // no privilege on, so an existing table the role cannot read is
            // indistinguishable here from one that was never created. Saying
            // flatly that it does not exist would send whoever reads this
            // hunting for a typo when what they need is a GRANT.
            throw new DbException(
                "Table '{$table}' does not exist, or is not visible to this connection.",
            );
        }

        return $names;
    }

    /**
     * Begins, calls, commits, and rolls back before rethrowing.
     *
     * A call made from inside a `transaction()` already running on this
     * connection joins it rather than nesting: no second `BEGIN`, no
     * `COMMIT`, no savepoint, and only the outermost call decides the
     * outcome. PDO's own nested transactions are a fiction on both
     * servers, so an inner `COMMIT` would end the outer transaction early
     * and an inner rollback would only unwind part of its work. Joining is
     * what lets a bulk action (rule 7) open one transaction around a loop
     * of writes that each open their own — the loop's `transaction()` call
     * is the one that begins and commits; every write inside it joins.
     *
     * It still refuses a transaction this connection did not open itself:
     * `$pdo->inTransaction()` true while this call's own depth is zero
     * means something outside `transaction()` holds one open, and joining
     * that blind would mean committing or rolling back work this method
     * knows nothing about.
     *
     * PostgreSQL aborts the whole transaction on its first failed
     * statement and refuses every statement after it until a rollback,
     * where MySQL carries on regardless. A caller that catches a
     * `DbException` raised from work done inside this method must let it
     * propagate out of `transaction()` (or otherwise abandon the
     * transaction) rather than catch it and keep writing inside the same
     * call: continuing is only safe on one of the two servers this project
     * supports.
     *
     * @template T
     * @param Closure(): T $work
     * @return T
     */
    public function transaction(Closure $work): mixed
    {
        if ($this->transactionDepth > 0) {
            $this->transactionDepth++;

            try {
                return $work();
            } finally {
                $this->transactionDepth--;
            }
        }

        if ($this->pdo->inTransaction()) {
            throw new DbException(
                'A transaction is already open, and this connection did not open it. Refusing to '
                . 'join a transaction it does not control, rather than commit or roll back work it '
                . 'knows nothing about.',
            );
        }

        $this->transactionDepth = 1;
        $this->pdo->beginTransaction();

        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $e) {
            try {
                $this->pdo->rollBack();
            } catch (Throwable) {
                // The original exception is what matters here; a rollback
                // that itself fails must not replace it with "there is no
                // active transaction" or similar.
            }

            throw $e;
        } finally {
            $this->transactionDepth = 0;
        }
    }

    private function run(Sql $sql): PDOStatement
    {
        try {
            $statement = $this->pdo->prepare($sql->text);

            foreach ($sql->bindings as $index => $value) {
                // Positional '?' placeholders are bound 1-indexed -- $index
                // is the 0-based position in the list, so +1.
                $statement->bindValue($index + 1, $value, $this->paramType($value));
            }

            $statement->execute();

            return $statement;
        } catch (PDOException $e) {
            // PDOException::getCode() is the driver's own SQLSTATE once
            // ATTR_ERRMODE is EXCEPTION -- a five-character string such as
            // '22P02' or '42S02' -- but PHP types Throwable::getCode() as
            // int|string, so a caller that constructed one directly with an
            // integer code is still honoured: no SQLSTATE is claimed for it.
            $sqlState = \is_string($e->getCode()) ? $e->getCode() : null;

            throw new DbException(
                "Query failed: {$e->getMessage()}" . PHP_EOL . $sql->text,
                0,
                $e,
                $sqlState,
            );
        }
    }

    /**
     * `PDOStatement::execute(array)` binds every value as `PDO::PARAM_STR`,
     * and PHP's own string coercion turns `false` into `''` before it ever
     * reaches PDO -- a value neither server accepts into an integer or
     * boolean column: MySQL refuses it once `STRICT_TRANS_TABLES` is on,
     * PostgreSQL refuses it unconditionally. `true` survives that path
     * because `'1'` happens to be a valid literal for both; `false` does
     * not, because `''` is not. Binding each value individually, with its
     * own inferred type, is what makes `false` reach the driver as a
     * boolean rather than as an empty string.
     */
    private function paramType(mixed $value): int
    {
        return match (true) {
            $value === null => PDO::PARAM_NULL,
            \is_bool($value) => PDO::PARAM_BOOL,
            \is_int($value) => PDO::PARAM_INT,
            default => PDO::PARAM_STR,
        };
    }
}
