<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Support;

use PDO;
use PDOStatement;

/**
 * Stands in for the statement `FakePdo::prepare()` would otherwise return.
 *
 * It has no real driver underneath it, so it overrides every method
 * `Connection` calls rather than delegating to the parent class. The
 * statement text is kept as `$text` rather than the real `queryString` --
 * `PDOStatement` already declares that one `readonly`, and a subclass that
 * never calls the parent constructor cannot initialise it.
 *
 * `bindValue()` records each value under its 1-based position rather than
 * executing anything, which is what lets `Connection::run()`'s own
 * bind-then-execute sequence (`Connection::paramType()`, one `bindValue()`
 * call per value, then a parameterless `execute()`) run against this fake
 * exactly as it runs against a real statement.
 */
final class FakeStatement extends PDOStatement
{
    /** @var list<array<string, mixed>> */
    private array $rows = [];

    private int $affected = 0;

    /** @var array<int|string, mixed> */
    private array $bindings = [];

    /** @var array<int|string, int> the PDO::PARAM_* type each value was bound with, for a test to inspect */
    public array $boundTypes = [];

    public function __construct(private readonly FakePdo $pdo, public readonly string $text)
    {
    }

    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->bindings[$param] = $value;
        $this->boundTypes[$param] = $type;

        return true;
    }

    /** @param ?array<int|string, mixed> $params */
    public function execute(?array $params = null): bool
    {
        $bindings = $params ?? $this->orderedBindings();

        [$this->rows, $this->affected] = $this->pdo->record($this->text, $bindings);

        return true;
    }

    /** @return list<array<string, mixed>> */
    public function fetchAll(int $mode = PDO::FETCH_ASSOC, mixed ...$args): array
    {
        return $this->rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->rows[0] ?? null;

        if ($row === null) {
            return false;
        }

        $values = array_values($row);

        return $values[$column] ?? false;
    }

    public function rowCount(): int
    {
        return $this->affected;
    }

    /**
     * `bindValue()` is called once per value, in position order, so the
     * keys of `$bindings` are already `1, 2, 3, ...` in the order they were
     * bound -- `ksort()` only guards against a caller that bound them out
     * of order.
     *
     * @return list<mixed>
     */
    private function orderedBindings(): array
    {
        ksort($this->bindings);

        return array_values($this->bindings);
    }
}
