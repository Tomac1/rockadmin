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

    /**
     * When `$values` carries the entity's key, that value is used as-is -- a
     * `@uuid` default, a natural key, a copy that preserves an id. When it
     * does not, the far more common case, the database assigns one: on
     * PostgreSQL through the INSERT's own `RETURNING` clause, on MySQL
     * through `Connection::lastInsertId()` once the statement has run.
     * `Dialect::returningClause()` is what tells the two apart.
     *
     * @param array<string, mixed> $values
     */
    public function insert(Entity $entity, array $values): WriteResult
    {
        if ($values === []) {
            throw new DbException("An insert into '{$entity->table}' needs at least one value.");
        }

        return $this->connection->transaction(function () use ($entity, $values): WriteResult {
            $key = $this->hasExplicitKey($entity, $values)
                ? $this->insertWithGivenKey($entity, $values)
                : $this->insertWithGeneratedKey($entity, $this->withoutKey($entity, $values));

            // No scope: an insert has no WHERE, and the row was just written
            // with whatever workspace columns the caller put in $values. Re-
            // reading it through a scope would ask whether the write landed
            // where it was told to, which is a question about the caller
            // rather than about this row.
            $after = $this->find($entity, $key, []);

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

    /**
     * Whether `$values` carries a real value for the entity's key -- present,
     * and neither `null` nor `''`. A hidden key field on a create form sends
     * exactly one of those two when it has nothing to offer, and both mean
     * "let the database assign one", the same as the key being absent
     * altogether: a create form cannot know in advance whether the entity's
     * key is generated, so it is not in a position to leave the field out.
     *
     * @param array<string, mixed> $values
     */
    private function hasExplicitKey(Entity $entity, array $values): bool
    {
        return \array_key_exists($entity->key, $values)
            && $values[$entity->key] !== null
            && $values[$entity->key] !== '';
    }

    /**
     * `$values` with the entity's key removed, whatever it was set to --
     * `insertWithGeneratedKey()` must never see it, because inserting an
     * explicit `NULL` into a generated column is accepted by MySQL as
     * "assign one" but refused by PostgreSQL's identity columns as a
     * `NOT NULL` violation.
     *
     * @param  array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function withoutKey(Entity $entity, array $values): array
    {
        unset($values[$entity->key]);

        return $values;
    }

    /** @param array<string, mixed> $values */
    private function insertWithGivenKey(Entity $entity, array $values): string
    {
        $key = $this->stringKey($entity, $values[$entity->key]);

        $this->connection->execute($this->insertSql($entity, $values, null));

        return $key;
    }

    /**
     * Inserts without the entity's key among the values, and asks the
     * database for the one it assigned.
     *
     * @param array<string, mixed> $values already stripped of the entity's key
     */
    private function insertWithGeneratedKey(Entity $entity, array $values): string
    {
        if ($values === []) {
            throw new DbException(
                "An insert into '{$entity->table}' needs at least one value besides the key '"
                . "{$entity->key}', which is left to the database to assign.",
            );
        }

        $returning = $this->connection->dialect()->returningClause($entity->key);

        if ($returning !== null) {
            // PostgreSQL: the INSERT itself hands the key back as a row.
            $rows = $this->connection->select($this->insertSql($entity, $values, $returning));
            $generated = $rows[0][$entity->key] ?? null;
        } else {
            // MySQL: no RETURNING clause -- ask the connection what it just did.
            $this->connection->execute($this->insertSql($entity, $values, null));
            $generated = $this->connection->lastInsertId();
        }

        if ($this->isNoKeyAtAll($generated)) {
            throw new DbException(
                "Insert into '{$entity->table}' produced no key for '{$entity->key}'. Either give "
                . "'{$entity->key}' among the values, or declare it as a generated column in the "
                . 'database.',
            );
        }

        return $this->stringKey($entity, $generated);
    }

    /**
     * Whether a value read back as a "generated key" is actually the
     * absence of one: `null` when nothing was read at all, `''` when
     * PostgreSQL's `RETURNING` produced a row without the key column
     * present (an `Entity` naming the wrong key, say), and `'0'` because
     * that is `PDO::lastInsertId()`'s own way of saying "no auto-increment
     * value was generated by this statement" on MySQL. None of these is a
     * key a row could actually have been stored under.
     */
    private function isNoKeyAtAll(mixed $generated): bool
    {
        return $generated === null || $generated === '' || $generated === '0';
    }

    /**
     * `$key` names the row before the write; the row's key afterwards may
     * not be the same value, because `$values` is free to include the key
     * column itself -- an editable natural key (`code`, `slug`) is ordinary
     * admin configuration, not an edge case. `after` is always read back by
     * whatever the key is now (`$values[$entity->key] ?? $key`), never by
     * the key the row had before the write, and `WriteResult::$key` is that
     * same resolved value: both are wrong, in different ways, if either
     * still pointed at a row the update just renamed away from.
     *
     * @param array<string, mixed> $values
     */
    public function update(Entity $entity, string $key, array $values, array $scope): WriteResult
    {
        return $this->connection->transaction(function () use ($entity, $key, $values, $scope): WriteResult {
            // Scoped, so a row in another workspace is simply not found --
            // which from the asker's side is exactly what it is, and is why
            // the refusal is the one a missing row already gets rather than a
            // new kind of forbidden that would confirm the row exists.
            $before = $this->find($entity, $key, $scope);

            if ($before === null) {
                throw NoSuchRowException::for($entity->table, $key, 'update');
            }

            if ($values !== []) {
                // Scoped again, and not only for the read above. Between the
                // two there is a moment, and the UPDATE is the statement that
                // actually changes something -- a boundary enforced only by
                // the read before it is a boundary with a gap in it.
                $this->connection->execute($this->updateSql($entity, $key, $values, $scope));
            }

            $resolvedKey = \array_key_exists($entity->key, $values) && $values[$entity->key] !== null
                ? $this->stringKey($entity, $values[$entity->key])
                : $key;

            $after = $this->find($entity, $resolvedKey, $scope);

            if ($after === null) {
                // Not "the row vanished" in the ordinary sense -- the write
                // above already succeeded. Either the update changed the
                // key to a value this read cannot see (a UNIQUE violation
                // would have stopped the write first, so that is not this),
                // or something outside this transaction removed the row
                // between the write and this read. Either way, falling back
                // to $before here would report an update that changed two
                // columns as though it changed none, which is worse than
                // raising: it reads as a no-op rather than as missing.
                throw new DbException(
                    "Updated '{$entity->table}' but could not read the row back under key "
                    . "'{$resolvedKey}'. If the update changed '{$entity->key}', check that the new "
                    . 'value is the one the row was actually stored under; if it changed a column '
                    . 'the scope matches on, it moved the row out of the workspace it was written '
                    . 'from, which is not something an update may do.',
                );
            }

            return new WriteResult($resolvedKey, $before, $after);
        });
    }

    public function delete(Entity $entity, string $key, array $scope): WriteResult
    {
        return $this->connection->transaction(function () use ($entity, $key, $scope): WriteResult {
            $before = $this->find($entity, $key, $scope);

            if ($before === null) {
                throw NoSuchRowException::for($entity->table, $key, 'delete');
            }

            $this->connection->execute($this->deleteSql($entity, $key, $scope));

            return new WriteResult($key, $before, []);
        });
    }

    /** @param array<string, mixed> $values */
    private function insertSql(Entity $entity, array $values, ?string $returning): Sql
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
            . " ({$names}) VALUES ({$placeholders})" . ($returning ?? ''),
            array_values($values),
        );
    }

    /** @param array<string, mixed> $values */
    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $scope
     */
    private function updateSql(Entity $entity, string $key, array $values, array $scope): Sql
    {
        $dialect = $this->connection->dialect();

        $assignments = [];
        $bindings = [];

        foreach ($values as $column => $value) {
            $assignments[] = $dialect->quoteIdentifier((string) $column) . ' = ?';
            $bindings[] = $value;
        }

        [$where, $whereBindings] = $this->where($entity, $key, $scope);

        return new Sql(
            'UPDATE ' . $dialect->quoteIdentifier($entity->table)
            . ' SET ' . implode(', ', $assignments)
            . ' WHERE ' . $where,
            [...$bindings, ...$whereBindings],
        );
    }

    /** @param array<string, mixed> $scope */
    private function deleteSql(Entity $entity, string $key, array $scope): Sql
    {
        [$where, $bindings] = $this->where($entity, $key, $scope);

        return new Sql(
            'DELETE FROM ' . $this->connection->dialect()->quoteIdentifier($entity->table)
            . ' WHERE ' . $where,
            $bindings,
        );
    }

    /**
     * The key, and every column the scope pins.
     *
     * One place builds it, so the read, the UPDATE and the DELETE cannot
     * disagree about what "this row" means -- which is the way a boundary
     * ends up enforced on two statements out of three.
     *
     * A scope value of null becomes `IS NULL` rather than `= ?`, because
     * `= NULL` is never true in SQL and a page scoped to `['archived_at' =>
     * null]` would quietly match nothing.
     *
     * @param  array<string, mixed>              $scope
     * @return array{0: string, 1: list<mixed>} the clause, its bindings
     */
    private function where(Entity $entity, string $key, array $scope): array
    {
        $dialect = $this->connection->dialect();

        $clauses = [$dialect->quoteIdentifier($entity->key) . ' = ?'];
        $bindings = [$key];

        foreach ($scope as $column => $value) {
            $quoted = $dialect->quoteIdentifier((string) $column);

            if ($value === null) {
                $clauses[] = $quoted . ' IS NULL';

                continue;
            }

            $clauses[] = $quoted . ' = ?';
            $bindings[] = $value;
        }

        return [implode(' AND ', $clauses), $bindings];
    }

    /**
     * Reads one row by its key -- what makes `update()`'s `before` the row
     * the change actually applies to, and `after` a value read from the
     * database rather than the submitted values echoed back.
     *
     * @return ?array<string, mixed>
     */
    /**
     * @param  array<string, mixed>     $scope
     * @return array<string, mixed>|null
     */
    private function find(Entity $entity, string $key, array $scope): ?array
    {
        [$where, $bindings] = $this->where($entity, $key, $scope);

        $rows = $this->connection->select(new Sql(
            'SELECT * FROM ' . $this->connection->dialect()->quoteIdentifier($entity->table)
            . ' WHERE ' . $where,
            $bindings,
        ));

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
