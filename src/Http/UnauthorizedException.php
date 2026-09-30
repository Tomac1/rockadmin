<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use Throwable;

/**
 * 401 — nobody is signed in, and this request is not one to redirect.
 *
 * The distinction from `ForbiddenException` is the one the design spec draws
 * at lifecycle step 4: 401 means "I do not know who you are", 403 means "I
 * know, and no". A caller that flattens them loses the only thing that tells
 * a client whether signing in again would help.
 *
 * The kernel raises this for fragments and actions rather than redirecting
 * them. A redirect to the login page is a 200 full of HTML as far as an AJAX
 * call is concerned, so the login form is rendered into the grid or swallowed
 * by a handler expecting a fragment — which is what session expiry looks like
 * in most admin tools, and is a bug rather than a feature.
 *
 * The message never says whether the session was absent, expired or naming an
 * account that no longer exists, for the same reason `LoginResult::REFUSED`
 * does not say which check failed.
 */
final class UnauthorizedException extends HttpException
{
    public function __construct(string $message = 'Not signed in', ?Throwable $previous = null)
    {
        parent::__construct(401, $message, $previous);
    }
}
