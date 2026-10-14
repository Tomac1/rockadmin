<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Auth\AdminUsers;
use RockAdmin\Auth\Argon2idHasher;
use RockAdmin\Auth\PasswordResets;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Migrator;
use RockAdmin\Db\Sql;
use RockAdmin\Tests\Support\DatabaseTestCase;

/**
 * Tokens: issued, spent once, and never stored.
 *
 * Three claims are being checked, and each of them is the kind that is easy to
 * believe without being true:
 *
 * 1. **The database does not hold a working token.** A backup, a replica or an
 *    injection in the project's own code must not yield a set of live reset
 *    links. The test looks in the table for the token it was handed.
 * 2. **A link works once.** The claim is a single `UPDATE ... WHERE used_at IS
 *    NULL`, so two clicks race in the database rather than in PHP -- and the
 *    test claims the same token twice.
 * 3. **Unknown, expired and used are one answer.** Telling them apart tells
 *    whoever is guessing which of their guesses was once real.
 *
 * On both servers, because all three are SQL: a comparison against a timestamp
 * the application wrote, and a `rowCount()` that the two drivers could
 * perfectly well disagree about.
 */
#[CoversClass(PasswordResets::class)]
#[CoversClass(AdminUsers::class)]
final class PasswordResetsTest extends DatabaseTestCase
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
                $this->drop($connection);
                (new Migrator($connection))->migrate();
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
    public function testTheTokenIsNotInTheDatabase(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $token = (new PasswordResets($connection))->issue('jana@example.com');

        $rows = $connection->select(new Sql('SELECT * FROM ra_password_resets'));

        self::assertCount(1, $rows);
        self::assertStringNotContainsString(
            $token,
            json_encode($rows, JSON_THROW_ON_ERROR),
            'The row holds the token itself, so a leaked database is a set of working links.',
        );
        self::assertSame(hash('sha256', $token), $rows[0]['token_hash']);
    }

    #[DataProvider('connections')]
    public function testClaimingReturnsWhoseItWas(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $resets = new PasswordResets($connection);

        $token = $resets->issue('Jana@Example.com');

        self::assertSame('jana@example.com', $resets->claim($token));
    }

    #[DataProvider('connections')]
    public function testALinkWorksOnce(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $resets = new PasswordResets($connection);

        $token = $resets->issue('jana@example.com');

        self::assertSame('jana@example.com', $resets->claim($token));
        self::assertNull($resets->claim($token), 'The same link was spent twice.');
    }

    #[DataProvider('connections')]
    public function testAnExpiredLinkIsTheSameAnswerAsAnUnknownOne(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        // Issued with a lifetime already behind it, which is what a link that
        // sat in a mailbox for a day looks like.
        $token = (new PasswordResets($connection, lifetimeMinutes: -1))->issue('jana@example.com');
        $resets = new PasswordResets($connection);

        self::assertNull($resets->claim($token));
        self::assertNull($resets->claim(bin2hex(random_bytes(32))));
    }

    #[DataProvider('connections')]
    public function testALiveLinkCanBeCheckedWithoutSpendingIt(?Connection $connection): void
    {
        // The form behind a link is drawn before anything is spent: a GET that
        // consumed the token would mean a mail client's link preview or a
        // security scanner burned it before the person typed anything.
        $connection = $this->requireConnection($connection);
        $resets = new PasswordResets($connection);

        $token = $resets->issue('jana@example.com');

        self::assertTrue($resets->isLive($token));
        self::assertTrue($resets->isLive($token), 'Checking twice spent it.');
        self::assertSame('jana@example.com', $resets->claim($token));
        self::assertFalse($resets->isLive($token));
    }

    #[DataProvider('connections')]
    public function testAskingAgainRetiresTheLinkThatWasOutstanding(?Connection $connection): void
    {
        // One live link per administrator: the newest is the one the person is
        // looking at, and an older one in an older mail is one more thing that
        // can be forwarded or found later.
        $connection = $this->requireConnection($connection);
        $resets = new PasswordResets($connection);

        $first = $resets->issue('jana@example.com');
        $second = $resets->issue('jana@example.com');

        self::assertNull($resets->claim($first), 'An older link was still live.');
        self::assertSame('jana@example.com', $resets->claim($second));
    }

    #[DataProvider('connections')]
    public function testOneAddressesLinkIsNotAnothersConcern(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $resets = new PasswordResets($connection);

        $jana = $resets->issue('jana@example.com');
        $resets->issue('petr@example.com');

        self::assertSame('jana@example.com', $resets->claim($jana), "Somebody else's request retired it.");
    }

    #[DataProvider('connections')]
    public function testSettingAPasswordAnotherWayRetiresWhatWasInFlight(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $resets = new PasswordResets($connection);

        $token = $resets->issue('jana@example.com');
        $resets->invalidateAll('jana@example.com');

        self::assertNull($resets->claim($token));
    }

    #[DataProvider('connections')]
    public function testAUsedRowIsKeptRatherThanDeleted(?Connection $connection): void
    {
        // It is the record that a link was issued and used, which is the
        // question asked when somebody says they never requested one.
        $connection = $this->requireConnection($connection);
        $resets = new PasswordResets($connection);

        $resets->claim($resets->issue('jana@example.com'));

        $rows = $connection->select(new Sql('SELECT used_at FROM ra_password_resets'));

        self::assertCount(1, $rows);
        self::assertNotNull($rows[0]['used_at']);
    }

    // --- the user rows the reset writes to ---

    #[DataProvider('connections')]
    public function testAnAdministratorCanBeCreatedAndThenSignInWithTheirNewPassword(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $users = new AdminUsers($connection);

        self::assertTrue($users->none(), 'A fresh installation thinks it has administrators.');

        $id = $users->create('Jana@Example.com', 'Jana', null, ['superadmin'], []);

        self::assertNotSame('', $id, 'The new row has no id.');
        self::assertFalse($users->none());
        self::assertSame('jana@example.com', $users->activeByEmail('JANA@example.com')['email'] ?? null);

        self::assertSame(1, $users->setPassword('jana@example.com', 'a-new-password'));

        $stored = $connection->scalar(new Sql('SELECT password FROM ra_users WHERE id = ?', [$id]));

        self::assertIsString($stored);
        self::assertNotSame('a-new-password', $stored, 'The password was stored as typed.');
        self::assertTrue((new Argon2idHasher())->verify('a-new-password', $stored));
    }

    #[DataProvider('connections')]
    public function testAnInvitedAccountHoldsNoPasswordUntilOneIsSet(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $users = new AdminUsers($connection);

        $id = $users->create('jana@example.com', 'Jana', null, ['editor'], ['cyklobazar']);

        self::assertNull(
            $connection->scalar(new Sql('SELECT password FROM ra_users WHERE id = ?', [$id])),
            'An invited account was given a password nobody chose.',
        );
    }

    #[DataProvider('connections')]
    public function testADeactivatedAccountIsTheSameAnswerAsNoAccount(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $users = new AdminUsers($connection);

        $id = $users->create('jana@example.com', 'Jana', 'secret', ['editor'], []);
        $connection->execute(new Sql('UPDATE ra_users SET is_active = ? WHERE id = ?', [false, $id]));

        self::assertNull(
            $users->activeByEmail('jana@example.com'),
            'A deactivated administrator could learn their account still exists by asking for a link.',
        );
        self::assertNull($users->activeByEmail('nobody@example.com'));
    }

    private function drop(Connection $connection): void
    {
        foreach (self::TABLES as $table) {
            $connection->execute(new Sql("DROP TABLE IF EXISTS {$table}"));
        }
    }
}
