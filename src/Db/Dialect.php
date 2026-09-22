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
}
