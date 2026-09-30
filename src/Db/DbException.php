<?php

declare(strict_types=1);

namespace RockAdmin\Db;

use RuntimeException;
use Throwable;

/**
 * Anything wrong in the data layer.
 *
 * `$sqlState` carries the driver's own SQLSTATE when the exception came
 * from a failed statement — PDO hands one back on every `PDOException`,
 * and `Connection::run()` copies it straight through, uninterpreted. It is
 * the one piece of a database error that means the same thing on every
 * server: class '08' is always a connection problem, class '22' is always
 * a value that does not fit where it was put, whichever driver raised it
 * and whatever its own message happens to say. A caller that needs to
 * treat one kind of failure differently from another reads this rather
 * than pattern-matching the message text, which is free to change with a
 * server's own version or locale.
 *
 * Not final, so that a condition a caller must distinguish can be a type
 * rather than a string to match. `NoSuchRowException` is the one such case
 * today: it is raised by RockAdmin itself rather than by a driver, so it
 * carries no SQLSTATE, and without a type of its own it would be
 * indistinguishable from an internal fault and answered as one.
 */
class DbException extends RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $sqlState = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * The first two characters of `$sqlState` — the standard's own
     * grouping, e.g. '22' for every "data exception" from '22001' (string
     * too long) to '22P02' (Postgres's own invalid text representation) —
     * or null when no SQLSTATE is known at all.
     */
    public function sqlStateClass(): ?string
    {
        return $this->sqlState === null ? null : substr($this->sqlState, 0, 2);
    }
}
