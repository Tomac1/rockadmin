<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Support;

use RockAdmin\Auth\PasswordHasher;

/**
 * A hasher that counts what it was asked, and hashes nothing.
 *
 * It exists for one assertion `Argon2idHasher` cannot make: that an unknown
 * email and a wrong password do the *same work*, one verification each. A
 * wall-clock measurement of that is flaky by nature, so the structure is
 * asserted instead -- both paths ask the hasher exactly once -- and this is
 * what counts the asking. See `SqlAuthenticatorTest` for what that does and
 * does not prove.
 *
 * `hash()` is reversible on purpose. A test that needs a stored hash for the
 * password 'secret' writes 'fake:secret' and reads it back in microseconds,
 * where Argon2id would spend 60 milliseconds per call and buy the unit suite
 * nothing -- `tests/Integration/Auth/LoginTest.php` uses the real hasher
 * against a real database, which is where Argon2id itself is exercised.
 */
final class CountingHasher implements PasswordHasher
{
    public int $hashes = 0;

    /** @var list<string> the hash each verify() call was given, in order */
    public array $verified = [];

    public int $rehashQuestions = 0;

    public function __construct(private readonly bool $needsRehash = false)
    {
    }

    public function hash(string $password): string
    {
        $this->hashes++;

        return 'fake:' . $password;
    }

    public function verify(string $password, string $hash): bool
    {
        $this->verified[] = $hash;

        return $hash === 'fake:' . $password;
    }

    public function needsRehash(string $hash): bool
    {
        $this->rehashQuestions++;

        return $this->needsRehash;
    }

    public function dummyHash(): string
    {
        // Never equal to 'fake:' . anything, so verify() against it is always
        // false -- the property the real dummy hash has because it is the hash
        // of a value nobody knows.
        return 'no password matches this';
    }
}
