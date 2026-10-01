<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use RockAdmin\Http\SessionStore;

/**
 * Step 3 of the lifecycle: who is asking.
 *
 * This is the one place that joins the two seams a host may replace —
 * `SessionStore`, which knows how state survives a request, and
 * `Authenticator`, which knows who the ids mean. Neither knows about the
 * other, deliberately: an `Authenticator` that reached for the session would
 * make every host implement both at once, and a `SessionStore` that knew
 * about users would stop being a session store.
 *
 * It also owns the session key. Exactly one string in this project says who
 * is signed in, and it is declared here rather than repeated in the login
 * handler, the logout handler and the kernel, because three copies of a
 * session key is how a logout stops logging somebody out.
 *
 * **The session is resolved through the authenticator on every request, not
 * trusted as it stands.** That is what makes deactivating an account take
 * effect now rather than whenever the session happens to expire —
 * `Authenticator::byId()` answers "may act now", not "existed once". It is
 * also what makes a tampered session harmless: a value that names no row is
 * nobody, and `SqlAuthenticator` refuses a non-numeric id without querying at
 * all, because asking PostgreSQL to compare `'7 OR 1=1'` to a BIGINT raises
 * where MySQL merely finds nothing — the same edited cookie would otherwise
 * be a 500 on one server and a clean anonymous on the other.
 *
 * The lookup is memoised, because the kernel asks at step 3 and rendering
 * asks again at step 10, and the answer cannot change within one request.
 */
final class Identity
{
    /**
     * Namespaced with `ra.` so that a host sharing its own session array with
     * this admin cannot collide with it by accident.
     */
    public const string SESSION_KEY = 'ra.user';

    /**
     * Where the person was going when the kernel sent them to the login page.
     *
     * It lives here, next to the key that says who they are, for the reason
     * the class docblock gives about that one: three copies of a session key
     * is how a logout stops logging somebody out, and this is the second key
     * the sign-in flow owns. The alternative was a second seam — the kernel
     * holding a `SessionStore` of its own beside the `Identity` it already
     * has — which would put two classes in charge of the same session and
     * two namespaces in play for one conversation.
     *
     * The kernel writes it and the login handler reads it. It is deliberately
     * not a query parameter or a link: at the moment it is written nobody is
     * authenticated, and a user-controlled path in a `Location` header at that
     * exact moment is the open-redirect surface `ReturnAddress` exists to
     * keep closed. What comes back out of here is still validated by
     * `ReturnAddress` before it is used, because a session is a place a host
     * sharing its own session array can write to.
     */
    public const string INTENDED_KEY = 'ra.intended';

    private ?AuthUser $user = null;

    private bool $resolved = false;

    public function __construct(
        private readonly SessionStore $session,
        private readonly Authenticator $authenticator,
    ) {
    }

    /** Whoever this request is, or null. */
    public function user(): ?AuthUser
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $this->user = $this->resolve();

        return $this->user;
    }

    public function isAuthenticated(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Records a successful sign-in.
     *
     * The session id is reissued first and unconditionally. Without it a token
     * fixed before the login — by an attacker who handed the victim a link
     * carrying one — is still valid after it, and the privilege the login just
     * granted is granted to that token too. This is the whole of session
     * fixation, and it costs one call.
     */
    public function login(AuthUser $user): void
    {
        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, $user->id);

        $this->resolved = true;
        $this->user = $user;
    }

    /**
     * Forgets the session, and tells the authenticator so it can forget
     * whatever it keeps of its own.
     *
     * The session is cleared whether or not anybody was signed in, and the id
     * is reissued afterwards: leaving the old id in the browser after a logout
     * means the next person at that machine resumes the same session
     * container, which is the second half of the same fixation problem.
     */
    public function logout(): void
    {
        $user = $this->user();

        if ($user !== null) {
            $this->authenticator->logout($user);
        }

        $this->session->forget(self::SESSION_KEY);
        $this->session->regenerate();

        $this->resolved = true;
        $this->user = null;
    }

    /** Remembers where somebody was going before they were sent to sign in. */
    public function rememberIntended(string $url): void
    {
        $this->session->set(self::INTENDED_KEY, $url);
    }

    /**
     * Where they were going, or null.
     *
     * A peek rather than a take: `LoginHandler` draws the form more than once
     * — a refused attempt redraws it — and a value consumed by the first
     * drawing would leave the second one with nowhere to send the person.
     * It is forgotten when it has actually been used, by `forgetIntended()`.
     *
     * Anything that is not a non-empty string is nobody's address and is
     * reported as absent, for the same reason `resolve()` treats a session
     * key holding a non-string as no session at all.
     */
    public function intended(): ?string
    {
        $url = $this->session->get(self::INTENDED_KEY);

        return \is_string($url) && $url !== '' ? $url : null;
    }

    public function forgetIntended(): void
    {
        $this->session->forget(self::INTENDED_KEY);
    }

    /**
     * A session key holding something that is not a user is treated as no
     * session at all, and dropped.
     *
     * Dropping it matters: without that, every subsequent request pays another
     * lookup for an id that will never resolve, and the person keeps a cookie
     * that looks signed in to anything reading it naively.
     */
    private function resolve(): ?AuthUser
    {
        $id = $this->session->get(self::SESSION_KEY);

        if (!\is_string($id) || $id === '') {
            return null;
        }

        $user = $this->authenticator->byId($id);

        if ($user === null) {
            $this->session->forget(self::SESSION_KEY);
        }

        return $user;
    }
}
