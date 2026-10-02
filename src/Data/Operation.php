<?php

declare(strict_types=1);

namespace RockAdmin\Data;

use RockAdmin\Db\WriteHandler;
use RockAdmin\Db\WriteResult;

/**
 * A project's own logic for one write.
 *
 * There are five ways a row changes — `create`, `update`, `delete`, a
 * configured `post` action, and that action over a selection — and a project
 * may replace the logic of any of them, per page, without touching anything
 * else about the request.
 *
 * **What stays RockAdmin's**, and is why this is not a controller: the CSRF
 * check, the gate, the `when` condition re-checked on the server, the
 * workspace scope, the transaction, the switch into the row's own workspace,
 * the audit row, the flash and the redirect. None of that is the project's
 * logic, and all of it is the kind of thing that fails quietly when it is
 * reimplemented. Specification rule 7 says there is one write path; this
 * hands a project the inside of it rather than a way around it.
 *
 * **What it may not have:** a `Connection`, a `Request`, or the gate. A write
 * that does not go through `WriteHandler` is a write with no audit row, and
 * an operation re-checking a permission is an operation that can get it
 * wrong. If the default write is all that is wanted, `$writes` does it —
 * `$context->writeDefault($writes, $values)` is one line and is the
 * usual first line of an operation that mostly wants to send an email
 * afterwards.
 *
 * **Saying no is not failing.** `OperationRefused` carries a sentence back to
 * the person who pressed the button -- through the form it came from, or as a
 * flash wherever there was no form -- and rolls everything back on the way.
 * An ordinary business rule has no business being a 500.
 *
 * **Side effects belong after the commit.** An operation that sends mail and
 * then fails has sent mail about a change that did not happen, and the
 * transaction cannot take it back. `OperationContext::afterCommit()` queues
 * it for when the write is real.
 */
interface Operation
{
    /**
     * Performs the write, and returns what happened.
     *
     * Null means "nothing worth recording" — no audit row is written for it.
     * That is a real answer: an operation that decides this row needs no
     * change should say so rather than invent a `WriteResult` the log would
     * then claim was a change.
     *
     * Raising aborts everything. The transaction rolls back, no audit row is
     * written, and nothing queued with `afterCommit()` runs. An
     * `OperationRefused` carries its message back to whoever pressed the
     * button; anything else is a 500, which is the right answer to logic that
     * did not expect its own input.
     *
     * @param array<string, mixed> $row    the row as it is now, in the columns this page knows
     *                                     about -- the before-values, so an operation can compare.
     *                                     Empty for a create, and not every column of the table
     *                                     (see `ActionHandler::rowBefore()` for why)
     * @param array<string, mixed> $values what the form validated, or what the action's `set` named
     */
    public function perform(
        OperationContext $context,
        array $row,
        array $values,
        WriteHandler $writes,
    ): ?WriteResult;
}
