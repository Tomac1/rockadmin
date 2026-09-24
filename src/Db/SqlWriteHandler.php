<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * The default `WriteHandler`: builds INSERT/UPDATE/DELETE from plain values
 * and runs each one through `Connection::transaction()`.
 *
 * It composes statements the same way `QueryBuilder` does -- an `Sql` value
 * object, identifiers quoted through the same `Dialect`, every value bound
 * rather than interpolated -- so that a form, a bulk action and a future
 * inline cell edit share not just this one write path but the same
 * discipline about what may reach the database as text.
 */
final class SqlWriteHandler implements WriteHandler
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @param array<string, mixed> $values */
    public function insert(Entity $entity, array $values): WriteResult
    {
        if (!\array_key_exists($entity->key, $values)) {
            throw new DbException(
                "An insert into '{$entity->table}' needs its key '{$entity->key}' among the "
                . 'values given. RockAdmin never reads a key back from the database -- a row\'s '
                . 'key is decided before the write, by a form default or a bulk action, so it is '
                . 'the same key whichever database is behind it.',
            );
        }

        $key = $this->stringKey($entity, $values[$entity->key]);

        return $this->connection->transaction(function () use ($entity, $values, $key): WriteResult {
            $this->connection->execute($this->insertSql($entity, $values));

            $after = $this->find($entity, $key);

            if ($after === null) {
                throw new DbException(
                    "Inserted into '{$entity->table}' but could not read the row for key "
                    . "'{$key}' back. Check that '{$entity->key}' is unique and that the value "
                    . 'given for it is the one the row was actually stored under.',
                );
            }

            return new WriteResult($key, [], $after);
        });
    }

    /** @param array<string, mixed> $values */
    public function update(Entity $entity, string $key, array $values): WriteResult
    {
        return $this->connection->transaction(function () use ($entity, $key, $values): WriteResult {
            $before = $this->find($entity, $key);

            if ($before === null) {
                throw new DbException("No row '{$key}' in '{$entity->table}' to update.");
            }

            if ($values !== []) {
                $this->connection->execute($this->updateSql($entity, $key, $values));
            }

            $after = $this->find($entity, $key) ?? $before;

            return new WriteResult($key, $before, $after);
        });
    }

    public function delete(Entity $entity, string $key): WriteResult
    {
        return $this->connection->transaction(function () use ($entity, $key): WriteResult {
            $before = $this->find($entity, $key);

            if ($before === null) {
                throw new DbException("No row '{$key}' in '{$entity->table}' to delete.");
            }

            $this->connection->execute($this->deleteSql($entity, $key));

            return new WriteResult($key, $before, []);
        });
    }

    /** @param array<string, mixed> $values */
    private function insertSql(Entity $entity, array $values): Sql
    {
        $dialect = $this->connection->dialect();
        $columns = array_keys($values);

        $names = implode(', ', array_map(
            static fn (int|string $column): string => $dialect->quoteIdentifier((string) $column),
            $columns,
        ));
        $placeholders = implode(', ', array_fill(0, \count($columns), '?'));

        return new Sql(
            'INSERT INTO ' . $dialect->quoteIdentifier($entity->table)
            . " ({$names}) VALUES ({$placeholders})",
            array_values($values),
        );
    }

    /** @param array<string, mixed> $values */
    private function updateSql(Entity $entity, string $key, array $values): Sql
    {
        $dialect = $this->connection->dialect();

        $assignments = [];
        $bindings = [];

        foreach ($values as $column => $value) {
            $assignments[] = $dialect->quoteIdentifier((string) $column) . ' = ?';
            $bindings[] = $value;
        }

        $bindings[] = $key;

        return new Sql(
            'UPDATE ' . $dialect->quoteIdentifier($entity->table)
            . ' SET ' . implode(', ', $assignments)
            . ' WHERE ' . $dialect->quoteIdentifier($entity->key) . ' = ?',
            $bindings,
        );
    }

    private function deleteSql(Entity $entity, string $key): Sql
    {
        $dialect = $this->connection->dialect();

        return new Sql(
            'DELETE FROM ' . $dialect->quoteIdentifier($entity->table)
            . ' WHERE ' . $dialect->quoteIdentifier($entity->key) . ' = ?',
            [$key],
        );
    }

    /**
     * Reads one row by its key -- what makes `update()`'s `before` the row
     * the change actually applies to, and `after` a value read from the
     * database rather than the submitted values echoed back.
     *
     * @return ?array<string, mixed>
     */
    private function find(Entity $entity, string $key): ?array
    {
        $dialect = $this->connection->dialect();

        $sql = new Sql(
            'SELECT * FROM ' . $dialect->quoteIdentifier($entity->table)
            . ' WHERE ' . $dialect->quoteIdentifier($entity->key) . ' = ?',
            [$key],
        );

        $rows = $this->connection->select($sql);

        return $rows[0] ?? null;
    }

    private function stringKey(Entity $entity, mixed $value): string
    {
        if (!\is_scalar($value)) {
            throw new DbException(
                "The key '{$entity->key}' of '{$entity->table}' must be a scalar, got "
                . get_debug_type($value) . '.',
            );
        }

        return (string) $value;
    }
}
