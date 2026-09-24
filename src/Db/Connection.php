<?php

declare(strict_types=1);

namespace RockAdmin\Db;

use PDO;
use PDOException;
use PDOStatement;

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
     * The table's own columns, in their declared order — what `'fields' =>
     * '@all'` (spec 8.5) reads from. The statement itself is the one thing
     * that differs per server, and `Dialect::columns()` already carries that
     * difference; MySQL's `SHOW COLUMNS` names the column 'Field',
     * PostgreSQL's `information_schema.columns` names it 'column_name', so
     * both are read here rather than making a caller guess which ran.
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

        return $names;
    }

    private function run(Sql $sql): PDOStatement
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
