<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

/**
 * Proving who somebody is — the seam a host replaces to authenticate against
 * its own users instead of RockAdmin's.
 *
 * Admin users are not the project's users (spec 9.1): five administrators
 * against a hundred thousand customers, different fields and different
 * security properties, and the project must stay free to restructure its own
 * `users` table. `SqlAuthenticator` is the default and reads `ra_users`; a
 * project that wants the other thing implements these three methods instead,
 * and nothing above this interface knows the difference.
 *
 * Three methods, and no session among them. Sessions are the caller's
 * business: `SessionStore` is the seam for that, `Identity` is what joins the
 * two, and an `Authenticator` that reached for the session would make a host
 * implement both at once.
 */
interface Authenticator
{
    /**
     * Checks an email and a password, and records the attempt.
     *
     * Never throws to say "wrong" — a refusal is an ordinary answer, and the
     * `LoginResult` carries which kind it was. It may still throw when the
     * store behind it is broken, which is not the same thing and must not be
     * flattened into a refusal.
     *
     * An implementation must make a wrong password and an unknown address
     * indistinguishable, in the message and in the work done. See
     * `SqlAuthenticator` for what that costs.
     *
     * @param string $ip the caller's address, for throttling; the caller
     *                   decides whether it trusts a proxy header for this
     */
    public function attempt(string $email, string $password, string $ip): LoginResult;

    /**
     * The user this id may act as now, or null.
     *
     * Null is the answer for an id that names no row *and* for one that names
     * a row which may no longer act — a deactivated account, say. A caller
     * resolving a session asks this on every request, so "may act now" is the
     * question that keeps deactivation from waiting for the session to expire.
     */
    public function byId(string $id): ?AuthUser;

    /**
     * Whatever the store has to forget when somebody logs out.
     *
     * Not the session — the caller owns that, and clears it whether or not
     * this does anything. An implementation that keeps per-user session state
     * of its own (specification 9.1's "logout everywhere") invalidates it
     * here; one that keeps none does nothing, and says so.
     */
    public function logout(AuthUser $user): void;
}
