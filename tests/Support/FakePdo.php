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
 * `FakeStatement` that records what it was asked to run, and a `SELECT` is
 * answered from a queue of rows supplied up front, consumed in call order.
 */
final class FakePdo extends PDO
{
    /** @var list<Sql> every statement executed, in the order it ran */
    public array $executed = [];

    public int $begins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;

    private bool $inTransaction = false;

    /** @param list<list<array<string, mixed>>> $rows one result set per SELECT, consumed in order */
    public function __construct(private array $rows = [])
    {
    }

    /** @param array<string, mixed> $options */
    public function prepare(string $query, array $options = []): PDOStatement
    {
        return new FakeStatement($this, $query);
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

    /**
     * Records a statement and, for a SELECT, hands back the next queued
     * result set.
     *
     * Only a SELECT consumes the queue: an INSERT, UPDATE or DELETE answers
     * with a row count, not rows, so queuing a placeholder entry for each of
     * those would make the test read as if it mattered what they returned.
     *
     * @param  array<int|string, mixed>   $bindings
     * @return list<array<string, mixed>>
     */
    public function record(string $text, array $bindings): array
    {
        $this->executed[] = new Sql($text, array_values($bindings));

        if (!str_starts_with(ltrim($text), 'SELECT')) {
            return [];
        }

        return array_shift($this->rows) ?? [];
    }
}
