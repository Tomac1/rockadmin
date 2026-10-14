<?php

declare(strict_types=1);

namespace RockAdmin\Mail;

use RuntimeException;

/**
 * A message that did not go out.
 *
 * It raises rather than returning false, and nothing in this SDK catches it
 * on the way past, because the two things an admin sends -- a password reset
 * and an invitation -- are both a person waiting for an e-mail. A send that
 * failed quietly is somebody refreshing their inbox while the admin says it
 * worked, and the only thing worse than telling them the truth late is not
 * telling them at all.
 *
 * The message names what failed and never the credentials it failed with.
 */
final class MailException extends RuntimeException
{
}
