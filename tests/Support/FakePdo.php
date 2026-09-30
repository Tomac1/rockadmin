<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Support;

use PDO;
use PDOStatement;
use RockAdmin\Db\Sql;

/**
 * A PDO that runs nothing.
 *
 * `SqlWriteHandler` is constructed with a `Connection`, and `Connection` is
 * final -- deliberately, so nothing stands between it and the database it
 * wraps -- so a unit test cannot fake the connection itself. What it fakes
 * is the PDO underneath a real `Connection`: `prepare()` hands back a
 * `FakeStatement` that records what it was asked to run, and every
 * statement executed consumes the next entry off a queue of result rows
 * supplied up front, in call order -- one entry per statement, `[]` for one
 * that returns none. There is no sniffing of the SQL text to guess which
 * statements return rows and which do not: a test that does not care what a
 * given statement answers with still has to say so, explicitly, rather than
 * have this class infer it -- a guess here is exactly the kind of thing
 * that would quietly stop matching `Connection::run()` the day it changes.
 */
final class FakePdo extends PDO
{
    /** @var list<Sql> every statement executed, in the order it ran */
    public array $executed = [];

    /** @var list<FakeStatement> every statement prepared, in order -- for inspecting how it was bound */
    public array $statements = [];

    public int $begins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;

    /** What `lastInsertId()` answers -- MySQL's own way of reporting a generated key. */
    public string $nextInsertId = '';

    private bool $inTransaction = false;

    /**
     * @param list<list<array<string, mixed>>> $rows     one result set per statement, consumed in order
     * @param list<int>                        $affected one affected-row count per statement, consumed in
     *                                                    order; a statement run past the end of this list
     *                                                    gets 1, the ordinary case for every write this
     *                                                    project issues, which always targets one row by
     *                                                    its key
     */
    public function __construct(
        private array $rows = [],
        private array $affected = [],
    ) {
    }

    /** @param array<string, mixed> $options */
    public function prepare(string $query, array $options = []): PDOStatement
    {
        $statement = new FakeStatement($this, $query);
        $this->statements[] = $statement;

        return $statement;
    }

    public function beginTransaction(): bool
    {
        $this->begins++;
        $this->inTransaction = true;

        return true;
    }

    public function commit(): bool
    {
        $this->commits++;
        $this->inTransaction = false;

        return true;
    }

    public function rollBack(): bool
    {
        $this->rollbacks++;
        $this->inTransaction = false;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function lastInsertId(?string $name = null): string
    {
        return $this->nextInsertId;
    }

    /**
     * Records a statement and hands back its queued result rows and
     * affected-row count.
     *
     * @param  array<int|string, mixed>              $bindings
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    public function record(string $text, array $bindings): array
    {
        $this->executed[] = new Sql($text, array_values($bindings));

        $rows = array_shift($this->rows) ?? [];
        $affected = $this->affected === [] ? 1 : (int) array_shift($this->affected);

        return [$rows, $affected];
    }
}
