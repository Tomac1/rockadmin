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
            $scalar = $this->connection->scalar($countSql);
            // PostgreSQL reports reltuples as -1 for a table that has never been
            // analysed. A negative total would become a pager with negative page
            // numbers, so an estimate that says "unknown" is read as zero.
            $total = max(0, (int) (\is_scalar($scalar) ? $scalar : 0));
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
        $carried = false;

        foreach ($rows as $row) {
            if (\array_key_exists($key, $row)) {
                $carried = true;

                if ($row[$key] !== null) {
                    $keys[] = $row[$key];
                }
            }
        }

        if ($rows !== [] && !$carried) {
            // Without this the attach would find no keys, take the early return
            // and hand every row an empty list -- no query issued, no complaint,
            // and a grid that looks like it is working while showing nothing.
            throw new DbException(
                "No row carries the key '{$key}', so the collection '{$collection->alias}' has "
                . "nothing to attach to. A query must select the entity's key, under that name, "
                . 'for collections to attach to its rows.',
            );
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

        $grouped = [];

        foreach ($this->connection->select($sql) as $child) {
            $raKey = \is_scalar($child['ra_key']) ? (string) $child['ra_key'] : '';
            $grouped[$raKey][] = $child['ra_value'];
        }

        return array_map(
            static fn (array $row): array => [
                ...$row,
                $collection->alias => $grouped[\is_scalar($row[$key] ?? null) ? (string) ($row[$key] ?? '') : ''] ?? [],
            ],
            $rows,
        );
    }
}
