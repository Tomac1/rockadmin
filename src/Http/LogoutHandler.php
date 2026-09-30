<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RockAdmin\Auth\Identity;

/**
 * `POST logout` — forget the session, and send the person to the login page.
 *
 * **A POST, and a POST with a token.** `Router` already refuses a GET here, so
 * no link scanner, mail proxy or browser prefetch can sign somebody out by
 * following a link. The token closes the other half: without it any page on
 * the internet can post a form at this URL and log an administrator out
 * mid-edit, repeatedly, and they would have no way to tell that from a broken
 * installation. It is a nuisance rather than a breach, which is exactly the
 * class of bug that ships because nobody argued it was worth a line of code.
 *
 * A failed check is a `ForbiddenException` here and not a redrawn form, which
 * is the opposite of `LoginHandler`'s choice and for the reason that made it:
 * there is no form to redraw, and a redirect on a failed check would look like
 * a logout that quietly did not happen.
 *
 * `Identity::logout()` does the work — it tells the authenticator, clears the
 * key and reissues the session id — so this class holds no second copy of what
 * logging out means.
 */
final class LogoutHandler implements Handler
{
    public function __construct(
        private readonly Identity $identity,
        private readonly Csrf $csrf,
        private readonly UrlGenerator $urls,
    ) {
    }

    public function handle(Route $route, Request $request): Response
    {
        $body = $request->input(Csrf::FIELD);
        $token = \is_string($body) ? $body : $request->header(Csrf::HEADER);

        if (!$this->csrf->isValid($token)) {
            throw new ForbiddenException(
                'This request carried no valid CSRF token, so nobody was signed out. '
                . 'Reload the page and try again.',
            );
        }

        $this->identity->logout();

        return Response::redirect($this->urls->route('login.form'));
    }
}
