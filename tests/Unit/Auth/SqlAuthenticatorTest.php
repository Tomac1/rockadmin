<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RockAdmin\Auth\Argon2idHasher;
use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\LoginOutcome;
use RockAdmin\Auth\LoginResult;
use RockAdmin\Auth\SqlAuthenticator;
use RockAdmin\Db\Connection;
use RockAdmin\Db\DbException;
use RockAdmin\Db\MySqlDialect;
use RockAdmin\Tests\Support\CountingHasher;
use RockAdmin\Tests\Support\FakePdo;

/**
 * What `SqlAuthenticator` does with what a database hands back, asserted on
 * the statements it issued rather than against a server. `Connection` is
 * final, so the fake goes underneath it -- the same arrangement
 * `SqlWriteHandlerTest` uses.
 *
 * `FakePdo`'s row queue has one entry per statement executed, in order. The
 * order `attempt()` issues them in is fixed and asserted here, because two of
 * the properties this class exists for are properties of that order: the
 * throttle is consulted before any work is done, and the attempt is recorded
 * before anything can decide not to.
 *
 * `tests/Integration/Auth/LoginTest.php` is the other half -- real Argon2id
 * against a real `ra_users` -- and neither test replaces the other.
 */
#[CoversClass(SqlAuthenticator::class)]
#[CoversClass(AuthUser::class)]
#[CoversClass(LoginResult::class)]
#[CoversClass(Argon2idHasher::class)]
final class SqlAuthenticatorTest extends TestCase
{
    /**
     * Anything reachable from an `AuthUser` that looks like a password hash is
     * the failure this asserts against, the way `ListRegionTest` asserts what
     * a view object may never carry.
     *
     * @var list<string>
     */
    private const HASH_PREFIXES = ['$argon2', '$2y$', '$2a$', '$2b$', 'fake:'];

    /**
     * The row `ra_users` hands back, as either driver spells it.
     *
     * @return array<string, mixed>
     */
    private function row(string $password = 'fake:secret', mixed $active = 1): array
    {
        return [
            'id' => '7',
            'email' => 'jana@example.com',
            'name' => 'Jana',
            'password' => $password,
            'is_active' => $active,
            'roles' => '["editor"]',
            'workspaces' => '["cz"]',
        ];
    }

    /**
     * No attempts recorded within the window, for either key.
     *
     * @return list<list<array<string, mixed>>>
     */
    private function noAttempts(): array
    {
        return [[['n' => 0]], [['n' => 0]]];
    }

    /** @param list<list<array<string, mixed>>> $rows */
    private function authenticator(
        array $rows,
        CountingHasher $hasher = new CountingHasher(),
        int $perEmail = 10,
        int $perIp = 20,
    ): SqlAuthenticator {
        return new SqlAuthenticator(
            new Connection(new FakePdo($rows), new MySqlDialect()),
            $hasher,
            $perEmail,
            $perIp,
        );
    }

    private function authenticatorOn(FakePdo $pdo): SqlAuthenticator
    {
        return new SqlAuthenticator(new Connection($pdo, new MySqlDialect()), new CountingHasher());
    }

    /** @return list<string> */
    private function texts(FakePdo $pdo): array
    {
        $texts = [];

        foreach ($pdo->executed as $sql) {
            $texts[] = $sql->text;
        }

        return $texts;
    }

    /**
     * The one that matters, and the one a wall clock cannot assert.
     *
     * An unknown email and a known email with the wrong password must do the
     * same work: the same statements, in the same order, and exactly one
     * verification each. An early return on "no such user" would leak which
     * addresses exist through the response time, whatever the message says.
     */
    public function testAnUnknownEmailAndAWrongPasswordDoTheSameWork(): void
    {
        $unknownPdo = new FakePdo([...$this->noAttempts(), [], []]);
        $wrongPdo = new FakePdo([...$this->noAttempts(), [], [$this->row()]]);

        $unknownHasher = new CountingHasher();
        $wrongHasher = new CountingHasher();

        $unknown = (new SqlAuthenticator(new Connection($unknownPdo, new MySqlDialect()), $unknownHasher))
            ->attempt('nobody@example.com', 'secret', '10.0.0.1');
        $wrong = (new SqlAuthenticator(new Connection($wrongPdo, new MySqlDialect()), $wrongHasher))
            ->attempt('jana@example.com', 'wrong', '10.0.0.1');

        $this->assertCount(1, $unknownHasher->verified, 'The unknown email did no verification.');
        $this->assertCount(1, $wrongHasher->verified, 'The wrong password did not verify exactly once.');
        $this->assertSame($this->texts($wrongPdo), $this->texts($unknownPdo));
        $this->assertSame(LoginOutcome::Refused, $unknown->outcome);
        $this->assertSame(LoginOutcome::Refused, $wrong->outcome);
        $this->assertSame($wrong->message, $unknown->message);
    }

    /** The dummy is verified, not skipped -- otherwise the work is not equal. */
    public function testAnUnknownEmailIsVerifiedAgainstADummyHash(): void
    {
        $hasher = new CountingHasher();

        $this->authenticator([...$this->noAttempts(), [], []], $hasher)
            ->attempt('nobody@example.com', 'secret', '10.0.0.1');

        $this->assertSame([$hasher->dummyHash()], $hasher->verified);
    }

    /** A row with no password at all -- an OIDC user -- is the same case. */
    public function testAUserWithNoPasswordIsVerifiedAgainstTheDummyHashToo(): void
    {
        $hasher = new CountingHasher();

        $result = $this->authenticator([...$this->noAttempts(), [], [$this->row(password: '')]], $hasher)
            ->attempt('jana@example.com', 'secret', '10.0.0.1');

        $this->assertSame([$hasher->dummyHash()], $hasher->verified);
        $this->assertSame(LoginOutcome::Refused, $result->outcome);
    }

    public function testAnInactiveUserIsRefusedWithTheSameMessageAsAWrongPassword(): void
    {
        $hasher = new CountingHasher();

        $inactive = $this->authenticator([...$this->noAttempts(), [], [$this->row(active: 0)]], $hasher)
            ->attempt('jana@example.com', 'secret', '10.0.0.1');

        $this->assertSame(LoginOutcome::Refused, $inactive->outcome);
        $this->assertSame(LoginResult::refused()->message, $inactive->message);
        $this->assertCount(1, $hasher->verified, 'An inactive user skipped the verification.');
        $this->assertNull($inactive->user);
    }

    /** PostgreSQL answers a BOOLEAN with 't'; MySQL a TINYINT(1) with 1 or '1'. */
    public function testEveryFalseSpellingEitherServerUsesMeansInactive(): void
    {
        foreach ([0, '0', false, 'f', 'false', null] as $spelling) {
            $result = $this->authenticator([...$this->noAttempts(), [], [$this->row(active: $spelling)]])
                ->attempt('jana@example.com', 'secret', '10.0.0.1');

            $this->assertSame(
                LoginOutcome::Refused,
                $result->outcome,
                'is_active = ' . var_export($spelling, true) . ' let an inactive user in.',
            );
        }

        foreach ([1, '1', true, 't', 'true'] as $spelling) {
            $result = $this->authenticator(
                [...$this->noAttempts(), [], [$this->row(active: $spelling)], [], [], []],
            )->attempt('jana@example.com', 'secret', '10.0.0.1');

            $this->assertSame(
                LoginOutcome::Success,
                $result->outcome,
                'is_active = ' . var_export($spelling, true) . ' locked an active user out.',
            );
        }
    }

    /**
     * The statement order is part of the contract: the throttle is consulted
     * first, and the attempt is recorded before anything reads the user -- so
     * no later failure can lose the evidence the throttle counts.
     */
    public function testTheStatementOrderIsThrottleRecordThenRead(): void
    {
        $pdo = new FakePdo([...$this->noAttempts(), [], [$this->row()], [], [], []]);

        $this->authenticatorOn($pdo)->attempt('jana@example.com', 'secret', '10.0.0.1');

        $texts = $this->texts($pdo);

        $this->assertStringContainsString('COUNT(*)', $texts[0]);
        $this->assertStringContainsString('email', $texts[0]);
        $this->assertStringContainsString('COUNT(*)', $texts[1]);
        $this->assertStringContainsString('ip', $texts[1]);
        $this->assertStringContainsString('INSERT INTO ra_login_attempts', $texts[2]);
        $this->assertStringContainsString('FROM ra_users', $texts[3]);
    }

    public function testAnAttemptOverTheEmailLimitIsRefusedWithoutDoingAnyWork(): void
    {
        $pdo = new FakePdo([[['n' => 3]], [['n' => 0]]]);
        $hasher = new CountingHasher();

        $result = (new SqlAuthenticator(new Connection($pdo, new MySqlDialect()), $hasher, 3, 20))
            ->attempt('jana@example.com', 'secret', '10.0.0.1');

        $this->assertSame(LoginOutcome::Throttled, $result->outcome);
        $this->assertSame([], $hasher->verified, 'A throttled attempt still verified a password.');
        $this->assertCount(2, $pdo->executed, 'A throttled attempt issued more than the two counts.');
    }

    public function testAnAttemptOverTheIpLimitIsRefusedToo(): void
    {
        $pdo = new FakePdo([[['n' => 0]], [['n' => 5]]]);

        $result = (new SqlAuthenticator(new Connection($pdo, new MySqlDialect()), new CountingHasher(), 10, 5))
            ->attempt('jana@example.com', 'secret', '10.0.0.1');

        $this->assertSame(LoginOutcome::Throttled, $result->outcome);
    }

    /**
     * Whichever limit was hit, the person is told the same thing. Saying which
     * one would tell an attacker whether the address they are calling from is
     * the one being counted, and so when changing it would help.
     */
    public function testARefusalNeverSaysWhichLimitWasHit(): void
    {
        $byEmail = (new SqlAuthenticator(
            new Connection(new FakePdo([[['n' => 9]], [['n' => 0]]]), new MySqlDialect()),
            new CountingHasher(),
            9,
            20,
        ))->attempt('jana@example.com', 'secret', '10.0.0.1');

        $byIp = (new SqlAuthenticator(
            new Connection(new FakePdo([[['n' => 0]], [['n' => 9]]]), new MySqlDialect()),
            new CountingHasher(),
            10,
            9,
        ))->attempt('jana@example.com', 'secret', '10.0.0.1');

        $this->assertSame($byEmail->message, $byIp->message);

        foreach ([$byEmail->message, $byIp->message] as $message) {
            $this->assertStringNotContainsStringIgnoringCase('address', $message);
            $this->assertStringNotContainsStringIgnoringCase('email', $message);
            $this->assertStringNotContainsStringIgnoringCase(' ip', $message);
        }
    }

    /**
     * The message may not distinguish them; the result must. The handler
     * flashes one and logs the other, and a caller that had to match on the
     * message would break the moment somebody translated it.
     */
    public function testTheCallerCanTellWrongFromThrottledWithoutReadingTheMessage(): void
    {
        $wrong = $this->authenticator([...$this->noAttempts(), [], []])
            ->attempt('jana@example.com', 'secret', '10.0.0.1');

        $throttled = (new SqlAuthenticator(
            new Connection(new FakePdo([[['n' => 3]], [['n' => 0]]]), new MySqlDialect()),
            new CountingHasher(),
            3,
        ))->attempt('jana@example.com', 'secret', '10.0.0.1');

        $this->assertSame(LoginOutcome::Refused, $wrong->outcome);
        $this->assertSame(LoginOutcome::Throttled, $throttled->outcome);
        $this->assertFalse($wrong->succeeded());
        $this->assertFalse($throttled->succeeded());
        $this->assertTrue($throttled->wasThrottled());
        $this->assertFalse($wrong->wasThrottled());
    }

    public function testASuccessfulLoginStampsLastLoginAndClearsThatEmailsAttempts(): void
    {
        $pdo = new FakePdo([...$this->noAttempts(), [], [$this->row()], [], [], []]);

        $result = $this->authenticatorOn($pdo)->attempt('jana@example.com', 'secret', '10.0.0.1');

        $this->assertSame(LoginOutcome::Success, $result->outcome);
        $this->assertTrue($result->succeeded());

        $texts = $this->texts($pdo);

        $this->assertStringContainsString('UPDATE ra_users SET last_login_at', $texts[4]);
        $this->assertStringContainsString('DELETE FROM ra_login_attempts', $texts[5]);
        $this->assertStringContainsString('email', $texts[5]);
        $this->assertStringNotContainsString('ip', $texts[5], 'A success cleared the address budget too.');
    }

    /** The success writes are one transaction; the attempt record is not. */
    public function testTheAttemptRecordIsCommittedIndependentlyOfTheSuccessWrites(): void
    {
        $pdo = new FakePdo([...$this->noAttempts(), [], [$this->row()], [], [], []]);

        $this->authenticatorOn($pdo)->attempt('jana@example.com', 'secret', '10.0.0.1');

        $this->assertSame(1, $pdo->begins);
        $this->assertSame(1, $pdo->commits);
        $this->assertStringContainsString('INSERT INTO ra_login_attempts', $this->texts($pdo)[2]);
    }

    public function testAHashAtAnOlderCostIsRewrittenOnSuccess(): void
    {
        $pdo = new FakePdo([...$this->noAttempts(), [], [$this->row()], [], [], [], []]);
        $hasher = new CountingHasher(needsRehash: true);

        (new SqlAuthenticator(new Connection($pdo, new MySqlDialect()), $hasher))
            ->attempt('jana@example.com', 'secret', '10.0.0.1');

        $update = $pdo->executed[4];

        $this->assertStringContainsString('UPDATE ra_users SET password', $update->text);
        $this->assertSame(['fake:secret', '7'], $update->bindings);
        $this->assertSame(1, $hasher->hashes);
    }

    public function testAHashAtTheCurrentCostIsLeftAlone(): void
    {
        $pdo = new FakePdo([...$this->noAttempts(), [], [$this->row()], [], [], []]);
        $hasher = new CountingHasher(needsRehash: false);

        (new SqlAuthenticator(new Connection($pdo, new MySqlDialect()), $hasher))
            ->attempt('jana@example.com', 'secret', '10.0.0.1');

        $this->assertSame(0, $hasher->hashes);

        foreach ($this->texts($pdo) as $text) {
            $this->assertStringNotContainsString('SET password', $text);
        }
    }

    /**
     * MySQL's default collation compares text without regard to case and
     * PostgreSQL's does not, so a lookup by `email = ?` would let the same
     * address log in on one server and not on the other -- exactly the
     * two-behaviours-from-one-configuration rule 3 forbids. Both the lookup
     * and the throttle key are folded to lower case here instead.
     */
    public function testTheEmailIsFoldedToLowerCaseForBothTheLookupAndTheThrottle(): void
    {
        $pdo = new FakePdo([...$this->noAttempts(), [], []]);

        $this->authenticatorOn($pdo)->attempt('  Jana@Example.COM ', 'secret', '10.0.0.1');

        $this->assertSame('jana@example.com', $pdo->executed[0]->bindings[0]);
        $this->assertSame('jana@example.com', $pdo->executed[2]->bindings[0]);
        $this->assertStringContainsString('LOWER(email)', $pdo->executed[3]->text);
        $this->assertSame(['jana@example.com'], $pdo->executed[3]->bindings);
    }

    /**
     * `ra_login_attempts.ip` is VARCHAR(45), and under the strict `sql_mode`
     * this project tests with, MySQL refuses a longer value rather than
     * truncating it. A spoofed `X-Forwarded-For` must not turn the login page
     * into a 500.
     */
    public function testAnOverlongAddressIsTruncatedRatherThanRefusedByTheDatabase(): void
    {
        $pdo = new FakePdo([...$this->noAttempts(), [], []]);

        $this->authenticatorOn($pdo)->attempt('jana@example.com', 'secret', str_repeat('9', 200));

        $recorded = $pdo->executed[2]->bindings[1];

        $this->assertIsString($recorded);
        $this->assertSame(45, \strlen($recorded));
    }

    /**
     * PostgreSQL's `UNIQUE (email)` is case-sensitive, so it permits
     * `Jana@example.com` beside `jana@example.com` where MySQL's collation
     * would not. Picking one of the two would mean logging somebody into an
     * account they did not name.
     */
    public function testTwoRowsDifferingOnlyInCaseAreRefusedRatherThanGuessedBetween(): void
    {
        $pdo = new FakePdo([...$this->noAttempts(), [], [$this->row(), $this->row()]]);

        try {
            $this->authenticatorOn($pdo)->attempt('jana@example.com', 'secret', '10.0.0.1');
            $this->fail('Two accounts matched one address and one of them was picked.');
        } catch (DbException $e) {
            $this->assertStringContainsString('jana@example.com', $e->getMessage());
        }
    }

    public function testByIdReturnsNullForAnIdThatDoesNotExist(): void
    {
        $this->assertNull($this->authenticator([[]])->byId('7'));
    }

    /**
     * `ra_users.id` is a generated `BIGINT`, so an id that is not a run of
     * digits cannot name a row. PostgreSQL raises an invalid-input error
     * comparing one, which would turn a tampered session cookie into a 500
     * instead of an anonymous request.
     */
    public function testByIdReturnsNullForAnIdThatCouldNotBeOneWithoutAskingTheDatabase(): void
    {
        $pdo = new FakePdo([]);

        $this->assertNull($this->authenticatorOn($pdo)->byId('7 OR 1=1'));
        $this->assertSame([], $pdo->executed);
    }

    /** Deactivating an account must take effect on the next request, not at session expiry. */
    public function testByIdReturnsNullForAnInactiveUser(): void
    {
        $this->assertNull($this->authenticator([[$this->row(active: 0)]])->byId('7'));
    }

    public function testByIdDecodesRolesAndWorkspaces(): void
    {
        $user = $this->authenticator([[$this->row()]])->byId('7');

        $this->assertInstanceOf(AuthUser::class, $user);
        $this->assertSame('7', $user->id);
        $this->assertSame('Jana', $user->name);
        $this->assertSame(['editor'], $user->roles);
        $this->assertSame(['cz'], $user->workspaces);
    }

    /**
     * A malformed value is refused rather than passed on, because the layer
     * that would receive it is the gate: `roles` arriving as `{"editor":true}`
     * resolves to no permissions at all, which reads exactly like a correct
     * answer for a user who has none.
     */
    #[DataProvider('malformedLists')]
    public function testAMalformedRolesColumnIsRefusedRatherThanPassedOn(string $roles): void
    {
        $row = $this->row();
        $row['roles'] = $roles;

        try {
            $this->authenticator([[$row]])->byId('7');
            $this->fail("roles = {$roles} was passed on rather than refused.");
        } catch (DbException $e) {
            $this->assertStringContainsString('roles', $e->getMessage());
            $this->assertStringContainsString('7', $e->getMessage());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function malformedLists(): iterable
    {
        yield 'an object' => ['{"editor":true}'];
        yield 'a list of numbers' => ['[1,2]'];
        yield 'a bare string' => ['"editor"'];
        yield 'not json at all' => ['editor'];
        yield 'null' => ['null'];
        yield 'a nested list' => ['[["editor"]]'];
    }

    public function testAnAuthUserNeverCarriesAPasswordHash(): void
    {
        $pdo = new FakePdo([...$this->noAttempts(), [], [$this->row()], [], [], []]);

        $user = $this->authenticatorOn($pdo)->attempt('jana@example.com', 'secret', '10.0.0.1')->user;

        $this->assertInstanceOf(AuthUser::class, $user);

        // The public surface first: no property, no constructor parameter and
        // no method may be about a password.
        $reflection = new ReflectionClass($user);

        foreach ($reflection->getProperties() as $property) {
            $this->assertDoesNotMatchRegularExpression(
                '/pass|hash|secret|credential/i',
                $property->getName(),
                'AuthUser declares a property about a password.',
            );
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $this->assertDoesNotMatchRegularExpression(
                '/pass|hash|secret|credential/i',
                $method->getName(),
                'AuthUser exposes a method about a password.',
            );
        }

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $this->assertDoesNotMatchRegularExpression(
                '/pass|hash|secret|credential/i',
                $parameter->getName(),
                'AuthUser can be constructed carrying a password.',
            );
        }

        // And nothing shaped like a hash is reachable through its values,
        // whatever they happen to be called.
        $this->assertNoHashReachable($user);
    }

    private function assertNoHashReachable(object $object): void
    {
        foreach (get_object_vars($object) as $name => $value) {
            foreach (\is_array($value) ? $value : [$value] as $item) {
                if (\is_object($item)) {
                    $this->assertNoHashReachable($item);

                    continue;
                }

                foreach (self::HASH_PREFIXES as $prefix) {
                    $this->assertFalse(
                        \is_string($item) && str_starts_with($item, $prefix),
                        "AuthUser::\${$name} carries something shaped like a password hash.",
                    );
                }
            }
        }
    }

    /**
     * The interface has no session, so there is nothing for this
     * implementation to forget. The method is still declared and still does
     * nothing rather than being left out -- see its own docblock for what
     * specification 9.1's "logout everywhere" would need.
     */
    public function testLogoutIssuesNoStatementAndIsNotAnError(): void
    {
        $pdo = new FakePdo([]);

        $this->authenticatorOn($pdo)->logout(new AuthUser('7', 'jana@example.com', 'Jana'));

        $this->assertSame([], $pdo->executed);
    }

    /**
     * A dummy hash PHP would no longer write is one whose verification costs
     * something other than a real one's, and that difference is the timing
     * leak the dummy exists to close. `password_needs_rehash()` is the
     * question, and this is the only place it can be asked about a constant.
     */
    public function testTheDummyHashIsWrittenAtTheParametersThisPhpUses(): void
    {
        $hasher = new Argon2idHasher();

        $this->assertFalse(
            $hasher->needsRehash(Argon2idHasher::DUMMY),
            'This PHP hashes with different parameters than Argon2idHasher::DUMMY was written at, so '
                . 'verifying against the dummy no longer costs what verifying a real hash costs, and '
                . 'the difference tells an attacker which addresses exist. Regenerate DUMMY with '
                . 'password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID).',
        );

        $this->assertFalse($hasher->verify('', Argon2idHasher::DUMMY));
        $this->assertFalse($hasher->verify('password', Argon2idHasher::DUMMY));
        $this->assertTrue($hasher->verify('secret', $hasher->hash('secret')));
    }
}
