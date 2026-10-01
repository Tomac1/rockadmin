<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

/**
 * Hashing a password, verifying one, and knowing when a stored hash is out of
 * date.
 *
 * It exists for two reasons, and neither is configurability.
 *
 * The first is that "an unknown email and a wrong password do the same work"
 * is a property of this class's *call pattern*, and a wall-clock test of it is
 * flaky by nature. With the hashing behind an interface, a test can assert the
 * structure instead — that both paths ask exactly once — which is a fact that
 * stays true when the machine is busy.
 *
 * The second is `dummyHash()`. The hash an unknown email is verified against
 * has to cost what a real one costs, and only the implementation knows what
 * that means; leaving it to the caller puts a constant next to code that has
 * no way to check it.
 *
 * A host may implement this to verify hashes a previous admin left behind, but
 * it is not a place to make hashing cheaper: `Argon2idHasher` is what
 * specification 9.1 requires and what a project gets without asking.
 */
interface PasswordHasher
{
    public function hash(string $password): string;

    public function verify(string $password, string $hash): bool;

    /** Whether this hash was written with parameters this implementation no longer uses. */
    public function needsRehash(string $hash): bool;

    /**
     * A hash no password will ever match, which `verify()` spends the same
     * work on as it would on a real one.
     *
     * Verified when no stored hash is available, so that an unknown email
     * takes as long to refuse as a known one. An implementation returning
     * something cheap here — an empty string, a constant that fails to parse —
     * reopens the timing channel while appearing to close it.
     */
    public function dummyHash(): string;
}
