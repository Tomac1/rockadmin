<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * Everything MySQL and PostgreSQL spell differently, and nothing else.
 *
 * None of this may leak into configuration: the same page definition has to
 * run on both databases, so every difference is answered here or it becomes
 * the project's problem.
 */
interface Dialect
{
    /** Matches PDO's driver name, so a connection can pick its dialect. */
    public function name(): string;

    public function quoteIdentifier(string $name): string;

    public function qualify(string $table, string $column): string;

    /** The operator that compares text without regard to case. */
    public function caseInsensitiveLike(): string;

    /**
     * Reads a value out of a JSON column, as text.
     *
     * @param string       $expression an already-qualified column
     * @param list<string> $path       the keys to walk, outermost first
     */
    public function jsonPath(string $expression, array $path): Sql;

    /**
     * An approximate row count from table metadata — cheap, and wrong by
     * however much has changed since the last statistics update.
     */
    public function estimatedCount(string $table): Sql;

    /**
     * The table's own columns, in their declared order — what `'fields' =>
     * '@all'` (spec 8.5) expands to. `SHOW COLUMNS` on MySQL,
     * `information_schema.columns` on PostgreSQL; the two name the column
     * holding the column's own name differently ('Field' vs 'column_name'),
     * which `Connection::columns()` reads regardless of which arrives.
     */
    public function columns(string $table): Sql;

    /**
     * The clause an INSERT appends to have the database hand back the key
     * it assigned to a new row, or null when this server has none.
     *
     * PostgreSQL answers with `RETURNING "id"`, read off the statement like
     * any other query. MySQL has no equivalent clause — null tells the
     * caller to ask `Connection::lastInsertId()` once the statement has run
     * instead, which is also why this is a `Dialect` method rather than a
     * single flag: the two servers do not just spell the same thing
     * differently, they hand the value back through different parts of the
     * API.
     */
    public function returningClause(string $key): ?string;
}
