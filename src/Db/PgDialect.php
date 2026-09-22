<?php

declare(strict_types=1);

namespace RockAdmin\Db;

final class PgDialect implements Dialect
{
    public function name(): string
    {
        return 'pgsql';
    }

    public function quoteIdentifier(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    public function qualify(string $table, string $column): string
    {
        return $this->quoteIdentifier($table) . '.' . $this->quoteIdentifier($column);
    }

    public function caseInsensitiveLike(): string
    {
        return 'ILIKE';
    }

    public function jsonPath(string $expression, array $path): Sql
    {
        return new Sql("({$expression} #>> ?::text[])", ['{' . implode(',', $path) . '}']);
    }

    public function estimatedCount(string $table): Sql
    {
        return new Sql('SELECT reltuples::bigint FROM pg_class WHERE oid = to_regclass(?)', [$table]);
    }
}
