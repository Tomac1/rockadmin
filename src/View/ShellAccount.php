<?php

declare(strict_types=1);

namespace RockAdmin\View;

use RockAdmin\Auth\Identity;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\UrlGenerator;

/**
 * Who the navbar says is signed in, and how they sign out.
 *
 * **It exists to keep three facts from being three decisions.** `ShellView`
 * takes `userName`, `logoutUrl` and `csrfToken` separately, and it cannot
 * enforce a relationship between them — a shell legitimately has none of the
 * three, which is what the login page is. But `logoutUrl` without `csrfToken`
 * draws a Log out button whose only possible answer is 403, because
 * `LogoutHandler` refuses a post that carries no valid token. So the pairing is
 * decided here, once, and every handler that draws a shell reads it from here
 * rather than deciding again.
 *
 * **Resolved per render, not per wiring,** for the reason `Menu` gives about
 * the same thing: a handler is built once and the reader belongs to the
 * request. `Identity` memoises its lookup, so asking costs no query the kernel
 * has not already paid for, and asking nothing costs nothing — an anonymous
 * reader never reaches a `Csrf::token()` call, so no session is started to
 * mint a token nobody will post.
 *
 * It is not a view object. A view object is handed to a template; this is
 * handed to whatever builds one.
 */
final class ShellAccount
{
    public function __construct(
        private readonly Identity $identity,
        private readonly UrlGenerator $urls,
        private readonly Csrf $csrf,
    ) {
    }

    /** The signed-in person's name, or null for an anonymous reader. */
    public function userName(): ?string
    {
        return $this->identity->user()?->name;
    }

    /**
     * Where the Log out button posts, or null.
     *
     * Null for an anonymous reader, who has nothing to log out of — and a null
     * here is what stops the template drawing the form at all, so it travels
     * with `csrfToken()` returning null for the same reader.
     */
    public function logoutUrl(): ?string
    {
        return $this->identity->isAuthenticated() ? $this->urls->route('logout') : null;
    }

    /** The token that button carries. Never null while `logoutUrl()` is not. */
    public function csrfToken(): ?string
    {
        return $this->identity->isAuthenticated() ? $this->csrf->token() : null;
    }
}
