<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Auth\Throttle;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Migrator;
use RockAdmin\Db\Sql;
use RockAdmin\Tests\Support\DatabaseTestCase;

/**
 * Two budgets that must not be one.
 *
 * The test that matters here is the one about `kind`, and it is worth saying
 * why it is worth a table column. Sharing a counter between signing in and
 * asking for a reset link breaks in both directions, and the second is the bad
 * one:
 *
 * - ten forgotten passwords would exhaust the login allowance and lock
 *   somebody out of the form they were trying to reach;
 * - a login lockout would block the reset request -- removing the one
 *   self-service way out of exactly the situation somebody is already stuck
 *   in, and turning a quarter-hour wait into a message to whoever has database
 *   access.
 *
 * Against a real database on both servers, because the counting is a `WHERE`
 * and a comparison against a timestamp the application wrote, and those are
 * the two things that differ between drivers.
 */
#[CoversClass(Throttle::class)]
final class ThrottleTest extends DatabaseTestCase
{
    /** @var list<string> dropped in dependency order, children first */
    private const TABLES = [
        'ra_activity_log',
        'ra_password_resets',
        'ra_login_attempts',
        'ra_users',
        'ra_migrations',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::connections() as [$connection]) {
            if ($connection !== null) {
                $this->migrate($connection);
            }
        }
    }

    protected function tearDown(): void
    {
        foreach (self::connections() as [$connection]) {
            if ($connection !== null) {
                $this->drop($connection);
                $this->createFixtures($connection);
            }
        }

        parent::tearDown();
    }

    #[DataProvider('connections')]
    public function testAnAddressIsRefusedOnceItsAllowanceIsGone(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $throttle = new Throttle($connection, perEmail: 3, perIp: 20);

        for ($i = 0; $i < 3; $i++) {
            self::assertFalse($throttle->isExhausted(Throttle::LOGIN, 'jana@example.com', '10.0.0.1'));
            $throttle->record(Throttle::LOGIN, 'jana@example.com', '10.0.0.1');
        }

        self::assertTrue($throttle->isExhausted(Throttle::LOGIN, 'jana@example.com', '10.0.0.1'));
    }

    #[DataProvider('connections')]
    public function testAMachineIsRefusedHoweverManyAddressesItTried(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $throttle = new Throttle($connection, perEmail: 10, perIp: 3);

        foreach (['a@example.com', 'b@example.com', 'c@example.com'] as $email) {
            $throttle->record(Throttle::LOGIN, $email, '10.0.0.9');
        }

        self::assertTrue(
            $throttle->isExhausted(Throttle::LOGIN, 'd@example.com', '10.0.0.9'),
            'Three addresses from one machine did not count against the machine.',
        );
        self::assertFalse(
            $throttle->isExhausted(Throttle::LOGIN, 'd@example.com', '10.0.0.8'),
            'The lockout followed the address rather than the machine it came from.',
        );
    }

    /** The test this task exists for, in both directions. */
    #[DataProvider('connections')]
    public function testLoginAndPasswordResetDoNotShareACounter(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $throttle = new Throttle($connection, perEmail: 3, perIp: 20);

        for ($i = 0; $i < 3; $i++) {
            $throttle->record(Throttle::PASSWORD_RESET, 'jana@example.com', '10.0.0.1');
        }

        self::assertTrue($throttle->isExhausted(Throttle::PASSWORD_RESET, 'jana@example.com', '10.0.0.1'));
        self::assertFalse(
            $throttle->isExhausted(Throttle::LOGIN, 'jana@example.com', '10.0.0.1'),
            'Asking for a reset link three times locked somebody out of the login form.',
        );

        for ($i = 0; $i < 3; $i++) {
            $throttle->record(Throttle::LOGIN, 'petr@example.com', '10.0.0.2');
        }

        self::assertTrue($throttle->isExhausted(Throttle::LOGIN, 'petr@example.com', '10.0.0.2'));
        self::assertFalse(
            $throttle->isExhausted(Throttle::PASSWORD_RESET, 'petr@example.com', '10.0.0.2'),
            'A login lockout blocked the one self-service way out of it.',
        );
    }

    #[DataProvider('connections')]
    public function testAnAttemptOutsideTheWindowNoLongerCounts(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        // Written with the timestamp of an hour ago, which is what an attempt
        // that has aged out looks like. The column holds UTC written by the
        // application, so this is the same statement on both servers.
        $connection->execute(new Sql(
            'INSERT INTO ra_login_attempts (kind, email, ip, at) VALUES (?, ?, ?, ?)',
            [Throttle::LOGIN, 'jana@example.com', '10.0.0.1', gmdate('Y-m-d H:i:s', time() - 3600)],
        ));

        self::assertFalse(
            (new Throttle($connection, perEmail: 1, perIp: 1, windowSeconds: 900))
                ->isExhausted(Throttle::LOGIN, 'jana@example.com', '10.0.0.1'),
            'An attempt from an hour ago still counted against a fifteen-minute window.',
        );
    }

    #[DataProvider('connections')]
    public function testSigningInForgetsThatAddressesAttemptsAndNobodyElses(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $throttle = new Throttle($connection, perEmail: 3, perIp: 20);

        $throttle->record(Throttle::LOGIN, 'jana@example.com', '10.0.0.1');
        $throttle->record(Throttle::LOGIN, 'petr@example.com', '10.0.0.1');
        $throttle->record(Throttle::PASSWORD_RESET, 'jana@example.com', '10.0.0.1');

        $throttle->clear(Throttle::LOGIN, 'jana@example.com');

        self::assertSame(0, $this->attemptsFor($connection, Throttle::LOGIN, 'jana@example.com'));
        self::assertSame(
            1,
            $this->attemptsFor($connection, Throttle::LOGIN, 'petr@example.com'),
            "Signing in cleared somebody else's budget, which makes the IP allowance avoidable.",
        );
        self::assertSame(
            1,
            $this->attemptsFor($connection, Throttle::PASSWORD_RESET, 'jana@example.com'),
            'Signing in cleared the reset budget as well, which is a different thing entirely.',
        );
    }

    #[DataProvider('connections')]
    public function testTheAddressIsCountedWithoutRegardToCaseOrSpace(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $throttle = new Throttle($connection, perEmail: 2, perIp: 20);

        $throttle->record(Throttle::LOGIN, 'Jana@Example.com', '10.0.0.1');
        $throttle->record(Throttle::LOGIN, '  jana@example.com ', '10.0.0.1');

        self::assertTrue(
            $throttle->isExhausted(Throttle::LOGIN, 'JANA@EXAMPLE.COM', '10.0.0.1'),
            'Typing the address differently bought a second allowance.',
        );
    }

    /**
     * `ra_login_attempts.ip` is VARCHAR(45), and under the strict `sql_mode`
     * this project tests with, MySQL refuses a longer value rather than
     * truncating it. A spoofed `X-Forwarded-For` must not turn the login page
     * into a 500.
     */
    #[DataProvider('connections')]
    public function testAnOverlongAddressIsTruncatedRatherThanRefused(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        (new Throttle($connection))->record(Throttle::LOGIN, 'jana@example.com', str_repeat('9', 200));

        self::assertSame(
            1,
            $this->attemptsFor($connection, Throttle::LOGIN, 'jana@example.com'),
            'The row was refused rather than written.',
        );
    }

    private function attemptsFor(Connection $connection, string $kind, string $email): int
    {
        $value = $connection->scalar(new Sql(
            'SELECT COUNT(*) FROM ra_login_attempts WHERE kind = ? AND email = ?',
            [$kind, $email],
        ));

        return \is_scalar($value) ? (int) $value : 0;
    }

    private function migrate(Connection $connection): void
    {
        $this->drop($connection);
        (new Migrator($connection))->migrate();
    }

    private function drop(Connection $connection): void
    {
        foreach (self::TABLES as $table) {
            $connection->execute(new Sql("DROP TABLE IF EXISTS {$table}"));
        }
    }
}
