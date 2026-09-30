<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * A write named a row that is not there.
 *
 * This is an ordinary race rather than a fault: two people open the same row,
 * one saves, the other deletes, and the first save arrives to find nothing to
 * write. The person who sees it should be told the row is gone, which is a
 * 404, not that the server broke, which is what a bare `DbException` becomes
 * by the time it reaches a handler.
 *
 * It has a type rather than a SQLSTATE because no driver raised it —
 * `SqlWriteHandler` discovered the absence itself, by reading the row before
 * writing it. Matching on the message text instead would tie a handler to
 * wording that is free to change.
 */
final class NoSuchRowException extends DbException
{
    public static function for(string $table, string $key, string $verb): self
    {
        return new self("No row '{$key}' in '{$table}' to {$verb}.");
    }
}
