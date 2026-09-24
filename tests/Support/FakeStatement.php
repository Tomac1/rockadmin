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
 */
final class FakeStatement extends PDOStatement
{
    /** @var list<array<string, mixed>> */
    private array $rows = [];

    public function __construct(private readonly FakePdo $pdo, public readonly string $text)
    {
    }

    /** @param ?array<int|string, mixed> $params */
    public function execute(?array $params = null): bool
    {
        $this->rows = $this->pdo->record($this->text, $params ?? []);

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
        return \count($this->rows);
    }
}
