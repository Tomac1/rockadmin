<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Support;

use RockAdmin\Auth\Authenticator;
use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\LoginResult;

/**
 * An authenticator that knows the ids it was given and nothing else.
 *
 * It counts lookups, because `Identity::user()` is called by the kernel and
 * then again by rendering, and a second query per request for a fact that
 * cannot change within one request is waste nobody would ever notice.
 */
final class FakeAuthenticator implements Authenticator
{
    public int $lookups = 0;

    public ?AuthUser $loggedOut = null;

    /** What the next attempt answers. Null refuses, as it always did. */
    public ?LoginResult $nextResult = null;

    /** @var list<array{string, string, string}> every attempt, as it arrived */
    public array $attempts = [];

    /**
     * Keyed by user id. The key type is `array-key` rather than `string`
     * because PHP turns a numeric string key in an array literal into an int,
     * so `['7' => $user]` is an int-keyed array however it was written — and
     * the lookup below finds it either way.
     *
     * @param array<array-key, AuthUser> $users
     */
    public function __construct(private readonly array $users = [])
    {
    }

    public function attempt(string $email, string $password, string $ip): LoginResult
    {
        $this->attempts[] = [$email, $password, $ip];

        return $this->nextResult ?? LoginResult::refused();
    }

    public function byId(string $id): ?AuthUser
    {
        ++$this->lookups;

        return $this->users[$id] ?? null;
    }

    public function logout(AuthUser $user): void
    {
        $this->loggedOut = $user;
    }
}
