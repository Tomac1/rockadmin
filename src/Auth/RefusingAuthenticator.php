<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

/**
 * The authenticator an installation with no database has.
 *
 * It refuses every attempt, which is the honest answer rather than a
 * degradation: an admin with no user store has no users, and the login form
 * saying so is better than a fatal error from a null connection before a byte
 * of HTML is sent. The assets route stays open, so the page it refuses on
 * still looks like the admin.
 *
 * It is a named class and not an anonymous one because two callers need it —
 * the bootstrap and the tests — and because a thing that turns every login
 * into a refusal deserves to be findable by name when somebody is working out
 * why nobody can sign in.
 */
final class RefusingAuthenticator implements Authenticator
{
    public function attempt(string $email, string $password, string $ip): LoginResult
    {
        return LoginResult::refused();
    }

    public function byId(string $id): ?AuthUser
    {
        return null;
    }

    public function logout(AuthUser $user): void
    {
    }
}
