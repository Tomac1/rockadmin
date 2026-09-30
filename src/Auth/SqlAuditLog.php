<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use JsonException;
use RockAdmin\Db\Connection;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Sql;
use RockAdmin\Db\WriteResult;
use stdClass;

/**
 * One INSERT into `ra_activity_log` per write.
 *
 * **It is not part of the write's transaction, and that is the decision.**
 * `SqlWriteHandler` opens a transaction per verb and commits it before it
 * returns, so by the time this runs the change is already durable. An audit
 * row that joined that transaction could roll the write back with it — a full
 * disk in the log table would start refusing edits — and rule 9.4's "it can be
 * disabled" says plainly that the log is a record of the admin, not a part of
 * its correctness. The cost is a window: a crash between the commit and this
 * INSERT leaves a change with no record. That is the right way round. The
 * other way loses the change itself.
 *
 * **A failure here is never silent and never fatal.** This class raises
 * `DbException` like any other failing statement; `ActionHandler` catches it,
 * keeps the write, and tells the person that the change stands but the record
 * of it does not. Swallowing it there would make an installation whose audit
 * table is broken look exactly like one where nobody changed anything, which
 * is the failure this whole subsystem exists to prevent.
 *
 * **Every column the indexes want is filled.** `(user_id, at)` answers "what
 * did this person do", `(page, row_id)` answers "what happened to this row",
 * and both are only usable if `page` and `row_id` hold the same values every
 * write stores — the page's name, not its table, and the key as text.
 */
final class SqlAuditLog implements AuditLog
{
    /**
     * What stands in for a value whose column name says it is a secret.
     *
     * The column stays in the diff: the fact that a password changed is
     * exactly what an audit log is for, and dropping the column would record
     * a password reset as a write that changed nothing. Both sides carry the
     * marker, so neither the old value nor the new one is stored — a log that
     * kept "before" would be a list of previous passwords.
     */
    public const string REDACTED = '[redacted]';

    /**
     * Substrings that make a column name a secret, matched case-insensitively
     * anywhere in the name so that `password`, `password_hash`,
     * `api_token` and `stripe_secret_key` are all caught.
     *
     * Deliberately short. A list that also matched `hash` would redact a
     * `content_hash` column and quietly make its diff useless, and a redaction
     * nobody expects is as hard to notice as a leak nobody expects.
     *
     * @var list<string>
     */
    private const array SECRETS = ['password', 'passwd', 'secret', 'token', 'api_key', 'apikey', 'private_key'];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function record(?AuthUser $user, string $page, string $action, WriteResult $result): void
    {
        $this->connection->execute(new Sql(
            'INSERT INTO ra_activity_log (user_id, page, action, row_id, at, diff) VALUES (?, ?, ?, ?, ?, ?)',
            [
                // The acting user, or nobody. Never a column of the row.
                $user?->id,
                $page,
                $action,
                $result->key,
                gmdate('Y-m-d H:i:s'),
                $this->json($this->redacted($result->changed())),
            ],
        ));
    }

    /**
     * The diff with every secret column's values replaced.
     *
     * @param  array<string, array{0: mixed, 1: mixed}> $diff
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function redacted(array $diff): array
    {
        foreach ($diff as $column => $values) {
            if ($this->isSecret($column)) {
                $diff[$column] = [self::REDACTED, self::REDACTED];
            }
        }

        return $diff;
    }

    private function isSecret(string $column): bool
    {
        $name = mb_strtolower($column);

        foreach (self::SECRETS as $secret) {
            if (str_contains($name, $secret)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The diff as JSON, with an empty diff written as `{}` rather than `[]`.
     *
     * An update that changed nothing still writes a row (the fact that
     * somebody pressed Save is itself the record), and PHP's empty array
     * encodes as a JSON array while every non-empty diff encodes as an
     * object. One column that is sometimes an array and sometimes an object
     * is the kind of thing that works until somebody queries it.
     *
     * @param array<string, array{0: mixed, 1: mixed}> $diff
     */
    private function json(array $diff): string
    {
        try {
            return json_encode($diff === [] ? new stdClass() : $diff, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // A value the database handed back that JSON cannot carry --
            // invalid UTF-8 out of a binary column is the realistic one.
            // Raised rather than written as null: `ActionHandler` turns this
            // into "saved, but not recorded", which is true, where a row with
            // an empty diff would claim the write changed nothing.
            throw new DbException(
                'The diff of this write could not be encoded as JSON: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }
}
