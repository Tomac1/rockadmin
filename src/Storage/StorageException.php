<?php

declare(strict_types=1);

namespace RockAdmin\Storage;

use RuntimeException;

/**
 * A file that did not get where it was going.
 *
 * It raises rather than answering false, for the reason `MailException` does:
 * the caller is a form save, and a save that silently stored nothing is a row
 * pointing at a file that was never written -- which looks like a working
 * upload until somebody opens the page again.
 *
 * The message names the key and what was being attempted, and never the
 * credentials it was attempted with.
 */
final class StorageException extends RuntimeException
{
}
