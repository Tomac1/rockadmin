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
            $total = (int) (\is_scalar($scalar) ? $scalar : 0);
        }

        /** @var Collection $collection */
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

        foreach ($rows as $row) {
            if (\array_key_exists($key, $row) && $row[$key] !== null) {
                $keys[] = $row[$key];
            }
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

        /** @var array<string, list<mixed>> $grouped */
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
