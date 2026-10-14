<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Auth\Argon2idHasher;
use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\LoginOutcome;
use RockAdmin\Auth\SqlAuthenticator;
use RockAdmin\Auth\Throttle;
use RockAdmin\Db\Connection;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Migrator;
use RockAdmin\Db\Sql;
use RockAdmin\Tests\Support\DatabaseTestCase;

/**
 * `SqlAuthenticator` against a real `ra_users` and a real `ra_login_attempts`,
 * with real Argon2id, on both servers.
 *
 * Like `MigratorTest`, this creates and drops the SDK's own `ra_` tables
 * rather than the shared read-only fixtures of `DatabaseTestCase`, and cleans
 * up on every configured connection in both `setUp()` and `tearDown()` — a
 * failing assertion here must not leave `ra_users` behind for the next test.
 *
 * Argon2id is deliberately slow, so the hashes here are few and the throttle
 * limits are small: the sizes of the shipped limits are asserted in the unit
 * test, which needs no hashing at all.
 */
#[CoversClass(SqlAuthenticator::class)]
#[CoversClass(Argon2idHasher::class)]
#[CoversClass(AuthUser::class)]
final class LoginTest extends DatabaseTestCase
{
    /** @var list<string> dropped in dependency order, children first */
    private const TABLES = [
        'ra_activity_log',
        'ra_password_resets',
        'ra_login_attempts',
        'ra_users',
        'ra_migrations',
    ];

    private const PASSWORD = 'correct horse battery staple';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropEverywhere();
    }

    protected function tearDown(): void
    {
        $this->dropEverywhere();

        parent::tearDown();
    }

    #[DataProvider('connections')]
    public function testACorrectPasswordSucceeds(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $this->insertUser($connection, 'jana@example.com');

        $result = $this->auth($connection)->attempt('jana@example.com', self::PASSWORD, '10.0.0.1');

        self::assertSame(LoginOutcome::Success, $result->outcome, $result->message);
        self::assertInstanceOf(AuthUser::class, $result->user);
        self::assertSame('jana@example.com', $result->user->email);
        self::assertSame('Jana', $result->user->name);
        self::assertSame(['editor'], $result->user->roles);
        self::assertSame(['cz'], $result->user->workspaces);
        self::assertNotSame('', $result->user->id);

        self::assertNotNull(
            $connection->scalar(new Sql('SELECT last_login_at FROM ra_users WHERE email = ?', ['jana@example.com'])),
            'A successful login did not stamp last_login_at.',
        );
    }

    #[DataProvider('connections')]
    public function testAWrongPasswordAndAnUnknownEmailAreRefusedIdentically(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $this->insertUser($connection, 'jana@example.com');

        $auth = $this->auth($connection);

        $wrong = $auth->attempt('jana@example.com', 'not the password', '10.0.0.1');
        $unknown = $auth->attempt('nobody@example.com', self::PASSWORD, '10.0.0.1');

        self::assertSame(LoginOutcome::Refused, $wrong->outcome);
        self::assertSame(LoginOutcome::Refused, $unknown->outcome);
        self::assertSame($wrong->message, $unknown->message);
        self::assertNull($wrong->user);
        self::assertNull($unknown->user);

        // Both were recorded, so both count towards the throttle. An unknown
        // address that is not counted is a free oracle for guessing addresses.
        self::assertSame(2, $this->attemptCount($connection));
    }

    #[DataProvider('connections')]
    public function testAnInactiveUserIsRefusedTheSameWayAsAWrongPassword(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $this->insertUser($connection, 'jana@example.com', active: false);

        $auth = $this->auth($connection);

        $inactive = $auth->attempt('jana@example.com', self::PASSWORD, '10.0.0.1');
        $unknown = $auth->attempt('nobody@example.com', self::PASSWORD, '10.0.0.1');

        self::assertSame(LoginOutcome::Refused, $inactive->outcome);
        self::assertSame($unknown->message, $inactive->message);
        self::assertNull(
            $connection->scalar(new Sql('SELECT last_login_at FROM ra_users WHERE email = ?', ['jana@example.com'])),
            'An inactive user was stamped as having logged in.',
        );
    }

    /**
     * MySQL's default collation matches text without regard to case and
     * PostgreSQL's does not. The same stored address has to answer the same
     * way on both, which rules out `WHERE email = ?`.
     */
    #[DataProvider('connections')]
    public function testTheEmailIsMatchedWithoutRegardToCaseOnBothServers(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $this->insertUser($connection, 'Jana@Example.COM');

        $result = $this->auth($connection)->attempt('jana@example.com', self::PASSWORD, '10.0.0.1');

        self::assertSame(LoginOutcome::Success, $result->outcome, $result->message);
    }

    #[DataProvider('connections')]
    public function testTheAttemptAfterTheAddressLimitIsRefusedWhileAnotherAddressIsNot(
        ?Connection $connection,
    ): void {
        $connection = $this->fresh($connection);
        $this->insertUser($connection, 'jana@example.com');

        // Two allowed per address, twenty per email, so only the address limit
        // can fire here.
        $auth = $this->auth($connection, perEmail: 20, perIp: 2);

        self::assertSame(LoginOutcome::Refused, $auth->attempt('jana@example.com', 'no', '10.0.0.1')->outcome);
        self::assertSame(LoginOutcome::Refused, $auth->attempt('jana@example.com', 'no', '10.0.0.1')->outcome);
        self::assertSame(LoginOutcome::Throttled, $auth->attempt('jana@example.com', 'no', '10.0.0.1')->outcome);

        // A different address starts with its own budget: throttling by
        // address alone must not become a way to lock out the world.
        self::assertSame(LoginOutcome::Refused, $auth->attempt('jana@example.com', 'no', '10.0.0.2')->outcome);

        // And the correct password still works from the address that has not
        // spent its budget.
        self::assertSame(
            LoginOutcome::Success,
            $auth->attempt('jana@example.com', self::PASSWORD, '10.0.0.2')->outcome,
        );
    }

    #[DataProvider('connections')]
    public function testTheAttemptAfterTheEmailLimitIsRefusedFromEveryAddress(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $this->insertUser($connection, 'jana@example.com');

        $auth = $this->auth($connection, perEmail: 2, perIp: 100);

        self::assertSame(LoginOutcome::Refused, $auth->attempt('jana@example.com', 'no', '10.0.0.1')->outcome);
        self::assertSame(LoginOutcome::Refused, $auth->attempt('jana@example.com', 'no', '10.0.0.2')->outcome);
        self::assertSame(LoginOutcome::Throttled, $auth->attempt('jana@example.com', 'no', '10.0.0.3')->outcome);

        // Another email from the same addresses is untouched.
        self::assertSame(LoginOutcome::Refused, $auth->attempt('petr@example.com', 'no', '10.0.0.3')->outcome);
    }

    /**
     * A refused attempt is not recorded, so the window drains. Recording it
     * would make a lockout permanent for as long as an attacker keeps calling
     * — and with no password reset until milestone 9, permanent is permanent.
     */
    #[DataProvider('connections')]
    public function testARefusedAttemptIsNotRecordedSoTheWindowDrains(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $this->insertUser($connection, 'jana@example.com');

        $auth = $this->auth($connection, perEmail: 20, perIp: 1);

        $auth->attempt('jana@example.com', 'no', '10.0.0.1');
        $before = $this->attemptCount($connection);

        self::assertSame(LoginOutcome::Throttled, $auth->attempt('jana@example.com', 'no', '10.0.0.1')->outcome);
        self::assertSame($before, $this->attemptCount($connection), 'A throttled attempt was recorded.');
    }

    /** The window is a sliding one: an attempt older than it does not count. */
    #[DataProvider('connections')]
    public function testAnAttemptOlderThanTheWindowDoesNotCount(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $this->insertUser($connection, 'jana@example.com');

        for ($i = 0; $i < 5; $i++) {
            $connection->execute(new Sql(
                'INSERT INTO ra_login_attempts (email, ip, at) VALUES (?, ?, ?)',
                ['jana@example.com', '10.0.0.1', gmdate('Y-m-d H:i:s', time() - 3600)],
            ));
        }

        $auth = $this->auth($connection, perEmail: 2, perIp: 2, window: 900);

        self::assertSame(
            LoginOutcome::Success,
            $auth->attempt('jana@example.com', self::PASSWORD, '10.0.0.1')->outcome,
        );
    }

    /**
     * Which failure this optimises against: an administrator who has
     * forgotten their password, tried six variants and then remembered it
     * should not be two attempts from a lockout tomorrow. The address's own
     * budget is deliberately left alone, so a valid account cannot be used to
     * refill it before spraying other addresses.
     */
    #[DataProvider('connections')]
    public function testASuccessClearsThatEmailsAttemptsAndNotTheAddressBudget(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $this->insertUser($connection, 'jana@example.com');

        $auth = $this->auth($connection, perEmail: 20, perIp: 20);

        $auth->attempt('jana@example.com', 'no', '10.0.0.1');
        $auth->attempt('petr@example.com', 'no', '10.0.0.1');
        $auth->attempt('jana@example.com', self::PASSWORD, '10.0.0.1');

        self::assertSame(0, $this->attemptCount($connection, 'jana@example.com'));
        self::assertSame(1, $this->attemptCount($connection, 'petr@example.com'));
    }

    #[DataProvider('connections')]
    public function testAHashAtALowerCostIsRehashedOnASuccessfulLogin(?Connection $connection): void
    {
        $connection = $this->fresh($connection);

        $weak = password_hash(self::PASSWORD, PASSWORD_ARGON2ID, ['memory_cost' => 8192, 'time_cost' => 1]);

        self::assertTrue(
            password_needs_rehash($weak, PASSWORD_ARGON2ID),
            'The fixture hash is already at the current cost, so this test would assert nothing.',
        );

        $this->insertUser($connection, 'jana@example.com', $weak);

        $result = $this->auth($connection)->attempt('jana@example.com', self::PASSWORD, '10.0.0.1');

        self::assertSame(LoginOutcome::Success, $result->outcome, $result->message);

        $stored = $connection->scalar(new Sql('SELECT password FROM ra_users WHERE email = ?', ['jana@example.com']));

        self::assertIsString($stored);
        self::assertNotSame($weak, $stored, 'The hash was not rewritten.');
        self::assertTrue(password_verify(self::PASSWORD, $stored), 'The rewritten hash does not verify.');
        self::assertFalse(password_needs_rehash($stored, PASSWORD_ARGON2ID));
    }

    #[DataProvider('connections')]
    public function testByIdFindsTheUserAndRefusesEveryIdThatCannotAct(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $this->insertUser($connection, 'jana@example.com');
        $this->insertUser($connection, 'petr@example.com', active: false);

        $auth = $this->auth($connection);
        $jana = $auth->attempt('jana@example.com', self::PASSWORD, '10.0.0.1')->user;

        self::assertInstanceOf(AuthUser::class, $jana);

        $found = $auth->byId($jana->id);

        self::assertInstanceOf(AuthUser::class, $found);
        self::assertSame('jana@example.com', $found->email);
        self::assertSame(['editor'], $found->roles);

        self::assertNull($auth->byId('99999'), 'An id that does not exist was not null.');
        self::assertNull($auth->byId(''), 'An empty id was not null.');

        $petr = $connection->scalar(new Sql('SELECT id FROM ra_users WHERE email = ?', ['petr@example.com']));

        self::assertNull($auth->byId((string) (\is_scalar($petr) ? $petr : '')), 'An inactive user was returned.');
    }

    /**
     * A value that is not a list of strings is refused rather than passed on.
     * `roles` as an object resolves to no permissions, which reads exactly
     * like a correct answer for a user who has none.
     */
    #[DataProvider('connections')]
    public function testAMalformedRolesColumnIsRefused(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $this->insertUser($connection, 'jana@example.com', roles: '{"editor":true}');

        $id = $connection->scalar(new Sql('SELECT id FROM ra_users WHERE email = ?', ['jana@example.com']));

        try {
            $this->auth($connection)->byId((string) (\is_scalar($id) ? $id : ''));
            $this->fail('A malformed roles column was passed on rather than refused.');
        } catch (DbException $e) {
            self::assertStringContainsString('roles', $e->getMessage());
        }
    }

    /**
     * There is nothing for this implementation to forget — the session belongs
     * to the caller — so the assertion is that it changes nothing, which is
     * what makes the no-op deliberate rather than unfinished.
     */
    #[DataProvider('connections')]
    public function testLogoutLeavesTheAccountExactlyAsItWas(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $this->insertUser($connection, 'jana@example.com');

        $auth = $this->auth($connection);
        $user = $auth->attempt('jana@example.com', self::PASSWORD, '10.0.0.1')->user;

        self::assertInstanceOf(AuthUser::class, $user);

        $auth->logout($user);

        self::assertInstanceOf(AuthUser::class, $auth->byId($user->id));
        self::assertSame(
            LoginOutcome::Success,
            $auth->attempt('jana@example.com', self::PASSWORD, '10.0.0.1')->outcome,
        );
    }

    private function auth(
        Connection $connection,
        int $perEmail = 10,
        int $perIp = 20,
        int $window = 900,
    ): SqlAuthenticator {
        return new SqlAuthenticator(
            $connection,
            new Argon2idHasher(),
            new Throttle($connection, $perEmail, $perIp, $window),
        );
    }

    private function fresh(?Connection $connection): Connection
    {
        $connection = $this->requireConnection($connection);

        (new Migrator($connection))->migrate();

        return $connection;
    }

    private function insertUser(
        Connection $connection,
        string $email,
        ?string $password = null,
        bool $active = true,
        string $roles = '["editor"]',
    ): void {
        $connection->execute(new Sql(
            'INSERT INTO ra_users (email, password, name, is_active, roles, workspaces, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $email,
                $password ?? password_hash(self::PASSWORD, PASSWORD_ARGON2ID),
                'Jana',
                $active,
                $roles,
                '["cz"]',
                gmdate('Y-m-d H:i:s'),
            ],
        ));
    }

    private function attemptCount(Connection $connection, ?string $email = null): int
    {
        $count = $email === null
            ? $connection->scalar(new Sql('SELECT COUNT(*) FROM ra_login_attempts'))
            : $connection->scalar(new Sql('SELECT COUNT(*) FROM ra_login_attempts WHERE email = ?', [$email]));

        return (int) (\is_scalar($count) ? $count : 0);
    }

    private function dropEverywhere(): void
    {
        foreach (self::connections() as [$connection]) {
            if ($connection === null) {
                continue;
            }

            foreach (self::TABLES as $table) {
                $connection->execute(new Sql("DROP TABLE IF EXISTS {$table}"));
            }
        }
    }
}
