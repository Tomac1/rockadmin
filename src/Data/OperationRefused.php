<?php

declare(strict_types=1);

namespace RockAdmin\Data;

use RuntimeException;

/**
 * An operation saying no, in words meant for the person who pressed the
 * button.
 *
 * The alternative is a 500, and the two are not the same thing: "this ad is
 * already sold, so it cannot be archived" is the project's logic working
 * correctly, while a 500 says the admin broke. An operation that cannot tell
 * them apart has to choose between crashing over an ordinary business rule
 * and quietly doing nothing.
 *
 * Raising this still rolls everything back -- the transaction, the audit row,
 * anything queued with `afterCommit()`. What it changes is the answer: a form
 * is redrawn with the message above it, and anywhere else the message arrives
 * as a flash on the page the person came from. Both are places they can read
 * it and act on it.
 *
 * The message is shown verbatim, so it is written for them: say what was
 * refused and what would make it work, and name nothing about the schema.
 */
final class OperationRefused extends RuntimeException
{
}
