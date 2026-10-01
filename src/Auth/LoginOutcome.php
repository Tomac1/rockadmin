<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

/**
 * How an attempt ended, as the caller must be able to tell it apart.
 *
 * `Refused` and `Throttled` say the same thing to the person — the message is
 * the same shape of refusal and neither names a limit — but the handler does
 * different things with them: one is flashed and forgotten, the other is worth
 * logging, because a throttled login is either an attack or an administrator
 * who is about to open a support ticket.
 *
 * It is an enum rather than a boolean pair so that a third outcome — an OIDC
 * challenge, a second factor — is a case somebody must handle rather than a
 * flag somebody forgets.
 */
enum LoginOutcome
{
    case Success;

    /** The credentials did not match, or the account may not log in at all. */
    case Refused;

    /** Too many attempts for this email or from this address, within the window. */
    case Throttled;
}
