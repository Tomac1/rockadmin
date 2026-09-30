<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

/**
 * Argon2id, through PHP's own `password_*` functions and nothing else.
 *
 * No options are passed. PHP's defaults for `PASSWORD_ARGON2ID` are chosen by
 * people who follow the analysis, and they move with the language: pinning a
 * memory cost here would freeze it at whatever was reasonable the day this was
 * written, and every stored hash would stay there with it. Because nothing is
 * pinned, `needsRehash()` becomes true for every old hash the moment PHP
 * raises its defaults, and `SqlAuthenticator` rewrites each one the next time
 * its owner signs in.
 *
 * `verify()` handles bcrypt and every other algorithm PHP knows, because
 * `password_verify()` reads the algorithm out of the hash. A project arriving
 * with bcrypt hashes in `ra_users` therefore works, and each of those hashes
 * is upgraded to Argon2id on its owner's next login — `needsRehash()` says
 * yes to all of them.
 */
final class Argon2idHasher implements PasswordHasher
{
    /**
     * An Argon2id hash of 32 random bytes nobody kept.
     *
     * Verified when there is no stored hash to verify against, so that an
     * unknown email costs the same as a known one. It is a constant rather
     * than something computed per call because computing it *is* a hash, and
     * one path paying for an extra hash is the very difference this exists to
     * remove.
     *
     * It must stay at the parameters this PHP would write today, or verifying
     * it stops costing what verifying a real hash costs and the timing channel
     * reopens quietly. `SqlAuthenticatorTest` asks `needsRehash()` about it
     * for exactly that reason, so a PHP release that changes the defaults
     * fails the suite instead of shipping.
     */
    public const DUMMY = '$argon2id$v=19$m=65536,t=4,p=1$bzl6eXZGWm02bHRuMS5xOA$'
        . 'ZrnuNwU/tGlRnUDrvMPrd2uu5m8VPSDDj6us4WiVxqU';

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }

    public function dummyHash(): string
    {
        return self::DUMMY;
    }
}
