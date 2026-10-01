<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use RockAdmin\Db\WriteResult;

/**
 * The record of what happened (spec 9.4): one row per write, carrying who did
 * it and what changed.
 *
 * **Who did it comes from the authenticated user and from nowhere else.** A
 * row's own `user_id` column is who *owns* it, and a log that read that column
 * would say an administrator's edit of somebody else's row was made by that
 * somebody else — an audit trail that exonerates the wrong person is worse
 * than none, because it is believed.
 *
 * `$user` is nullable because a write can be made by something that is not a
 * logged-in person, and a row naming nobody is an honest answer where a row
 * naming a guess is not. Today nothing reaches `ActionHandler` unauthenticated
 * (the kernel refuses it), so the null case is the seam a future CLI or
 * scheduled write would come through rather than a hole in the HTTP path.
 *
 * The diff is taken from `WriteResult::changed()` and never recomputed: the
 * same two rows that a flash message reads, read once.
 *
 * Implementations must not throw for anything the caller can be expected to
 * recover from — but they are allowed to throw, and the caller is required to
 * keep the write it describes regardless. See `SqlAuditLog`.
 */
interface AuditLog
{
    /**
     * @param ?AuthUser $user   the acting user, never the row's owner
     * @param string    $page   the page the write was made through, not the table
     * @param string    $action `create`, `update` or `delete`
     */
    public function record(?AuthUser $user, string $page, string $action, WriteResult $result): void;
}
