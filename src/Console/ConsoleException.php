<?php

declare(strict_types=1);

namespace RockAdmin\Console;

use RuntimeException;

/**
 * Something a command cannot do, said in a sentence meant for a person.
 *
 * `Application` prints the message and nothing else, so the message has to
 * carry the whole answer: what was looked for, where, and what to do about
 * it. "Bootstrap not found" is not one of these; "No bootstrap file at
 * config/rockadmin/bootstrap.php — run `rockadmin init`, or point at one with
 * --bootstrap=path" is.
 */
final class ConsoleException extends RuntimeException
{
}
