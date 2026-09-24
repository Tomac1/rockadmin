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
     * It refuses to nest rather than silently joining an outer transaction:
     * PDO's own nesting is a fiction on both servers — a `COMMIT` inside an
     * inner call would end the outer transaction early, and a rollback of
     * the inner one would only unwind half of the outer's work, which is
     * worse than refusing outright.
     *
     * @template T
     * @param Closure(): T $work
     * @return T
     */
    public function transaction(Closure $work): mixed
    {
        if ($this->pdo->inTransaction()) {
            throw new DbException(
                'A transaction is already open. RockAdmin refuses to nest transactions: '
                . "PDO's own nesting is a fiction on both servers, and a rollback of the "
                . 'inner call would only unwind part of the outer one.',
            );
        }

        $this->pdo->beginTransaction();

        try {
            $result = $work();
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        $this->pdo->commit();

        return $result;
    }

    private function run(Sql $sql): PDOStatement
    {
        try {
            $statement = $this->pdo->prepare($sql->text);
            $statement->execute($sql->bindings);

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
}
