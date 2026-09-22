<?php

declare(strict_types=1);

namespace RockAdmin\Db;

final class MySqlDialect implements Dialect
{
    public function name(): string
    {
        return 'mysql';
    }

    public function quoteIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    public function qualify(string $table, string $column): string
    {
        return $this->quoteIdentifier($table) . '.' . $this->quoteIdentifier($column);
    }

    public function caseInsensitiveLike(): string
    {
        // MySQL's default collations are case-insensitive, so LIKE already is.
        return 'LIKE';
    }

    public function jsonPath(string $expression, array $path): Sql
    {
        $pointer = '$' . implode('', array_map(
            static fn (string $key): string => '."' . str_replace('"', '\\"', $key) . '"',
            $path,
        ));

        return new Sql("JSON_UNQUOTE(JSON_EXTRACT({$expression}, ?))", [$pointer]);
    }

    public function estimatedCount(string $table): Sql
    {
        return new Sql(
            'SELECT TABLE_ROWS FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        );
    }
}
