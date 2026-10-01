<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Auth;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\Gate;
use RockAdmin\Auth\LoginOutcome;
use RockAdmin\Auth\SqlAuthenticator;
use RockAdmin\Config\Config;
use RockAdmin\Config\Loader;
use RockAdmin\Config\RootSchema;
use RockAdmin\Config\SchemaKey;
use RockAdmin\Config\ValueType;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Sql;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\DatabaseTestCase;

/**
 * The demo's three administrators, seeded by the script that ships.
 *
 * `DemoFormTest` reads `demo/config/pages/ads.php` as it ships; this reads
 * `demo/config/roles.php` and runs `demo/seed.php` itself, as a subprocess,
 * rather than repeating what that script does. Repeating it is the trap: a
 * seed script and a test that each insert their own three users agree until
 * one of them is edited, and then the test goes on passing against rows the
 * demo no longer creates. What is being asserted here is that somebody who
 * clones this repository, exports the variables `CLAUDE.md` names and runs
 * `php demo/seed.php` can sign in — which is a fact about the script.
 *
 * `#[CoversNothing]` for the same reason as `DemoFormTest`: what it covers is
 * the demo's own configuration and its seed script, neither of which is a
 * class.
 *
 * **Fixtures.** `demo/seed.php` drops and recreates `ra_test_*` in the shape
 * the demo's form needs, and migrates the `ra_*` tables. `tearDown()` puts
 * `DatabaseTestCase`'s own fixtures back and drops the `ra_*` tables again, so
 * neither this test's users nor its schema reaches `LoginTest`, `MigratorTest`
 * or anything else that runs afterwards.
 */
#[CoversNothing]
final class DemoAuthTest extends DatabaseTestCase
{
    /** Dropped in dependency order, children first. */
    private const array RA_TABLES = [
        'ra_activity_log',
        'ra_password_resets',
        'ra_login_attempts',
        'ra_users',
        'ra_migrations',
    ];

    /** What `demo/seed.php` prints, and what `demo/README.md` documents. */
    private const string PASSWORD = 'rockadmin';

    /** @var array<string, list<string>> the addresses the demo seeds, and their roles */
    private const array USERS = [
        'super@example.com' => ['superadmin'],
        'editor@example.com' => ['editor'],
        'viewer@example.com' => ['viewer'],
    ];

    private static bool $seeded = false;

    protected function tearDown(): void
    {
        foreach (self::connections() as [$connection]) {
            if ($connection === null) {
                continue;
            }

            $this->createFixtures($connection);

            foreach (self::RA_TABLES as $table) {
                $connection->execute(new Sql("DROP TABLE IF EXISTS {$table}"));
            }
        }

        self::$seeded = false;

        parent::tearDown();
    }

    #[DataProvider('connections')]
    public function testEachSeededAdministratorCanSignIn(?Connection $connection): void
    {
        $connection = $this->seeded($connection);
        $authenticator = new SqlAuthenticator($connection);

        foreach (self::USERS as $email => $roles) {
            $result = $authenticator->attempt($email, self::PASSWORD, '10.0.0.1');

            self::assertSame(
                LoginOutcome::Success,
                $result->outcome,
                "demo/seed.php seeded {$email} but it cannot sign in: {$result->message}",
            );
            self::assertNotNull($result->user);
            self::assertSame($roles, $result->user->roles, "Wrong roles for {$email}.");
            self::assertNotSame('', $result->user->name, "No name was seeded for {$email}.");
        }
    }

    /**
     * Argon2id, not bcrypt and not whatever `PASSWORD_DEFAULT` happens to be
     * this release.
     *
     * Asserted over the stored hash rather than over the code that wrote it:
     * `password_hash()` succeeds with any algorithm, so the only place the
     * choice is observable is the column.
     */
    #[DataProvider('connections')]
    public function testTheSeededHashesAreArgon2id(?Connection $connection): void
    {
        $connection = $this->seeded($connection);

        foreach (array_keys(self::USERS) as $email) {
            $hash = $connection->scalar(new Sql('SELECT password FROM ra_users WHERE email = ?', [$email]));

            self::assertIsString($hash);
            self::assertStringStartsWith('$argon2id$', $hash, "The hash for {$email} is not Argon2id.");
            self::assertTrue(password_verify(self::PASSWORD, $hash));
        }
    }

    /**
     * Re-runnable, because that is how it is actually used: somebody reseeds
     * after playing with the form and must not be met by a unique-index
     * violation on the address.
     */
    #[DataProvider('connections')]
    public function testSeedingTwiceReplacesTheAdministratorsRatherThanFailing(?Connection $connection): void
    {
        $connection = $this->seeded($connection);

        self::runSeed();

        // MySQL hands COUNT(*) back as a string and PostgreSQL as an int, so
        // the narrowing is asserted rather than cast: a cast would turn a
        // driver returning something unexpected into a silent zero.
        $count = $connection->scalar(new Sql(
            'SELECT COUNT(*) FROM ra_users WHERE email IN (?, ?, ?)',
            array_keys(self::USERS),
        ));

        self::assertIsNumeric($count);
        self::assertSame(
            \count(self::USERS),
            (int) $count,
            'Running demo/seed.php twice did not leave exactly one row per administrator.',
        );
    }

    /**
     * What each role may do, resolved through the real `Gate` over the real
     * `demo/config/roles.php` and the real pages.
     *
     * This is the fact the browser pass looks at: the viewer sees no actions
     * column because `ads.update` is denied, and the editor sees Edit and no
     * Delete because `!ads.delete` subtracts after `ads.*` widens.
     */
    public function testTheDemoRolesGrantWhatTheBrowserPassLooksFor(): void
    {
        $gate = Gate::fromConfig(...$this->demoConfiguration());

        $expected = [
            'superadmin' => ['ads.view' => true, 'ads.create' => true, 'ads.update' => true, 'ads.delete' => true],
            'editor' => ['ads.view' => true, 'ads.create' => true, 'ads.update' => true, 'ads.delete' => false],
            'viewer' => ['ads.view' => true, 'ads.create' => false, 'ads.update' => false, 'ads.delete' => false],
        ];

        foreach ($expected as $role => $permissions) {
            foreach ($permissions as $permission => $allowed) {
                self::assertSame(
                    $allowed,
                    $gate->allows(new AuthUser('1', "{$role}@example.com", 'Somebody', [$role]), $permission),
                    "The demo's '{$role}' role should " . ($allowed ? 'allow' : 'deny') . " '{$permission}'.",
                );
            }
        }
    }

    /**
     * Every role the demo seeds a user for is declared, and every declared
     * role has somebody to sign in as.
     *
     * Both directions, because each failure is invisible on its own: a seeded
     * role that `roles.php` does not declare grants nothing and looks like a
     * gate bug, and a declared role nobody holds is a rule the browser pass
     * cannot see.
     */
    public function testTheSeededRolesAndTheDeclaredRolesAreTheSameSet(): void
    {
        [$config] = $this->demoConfiguration();

        $declared = array_keys($config->roles());
        $seeded = array_merge(...array_values(self::USERS));

        sort($declared);
        sort($seeded);

        self::assertSame($declared, $seeded);
    }

    /**
     * `audit.enabled` is a declared key and its default is on (spec 9.4).
     *
     * Asserted against `RootSchema` rather than against a loaded `Config`, and
     * the difference is a real one worth stating. `Loader` applies a child's
     * default only when the file names the parent — `$config->get('mail.port')`
     * and `$config->get('assets.css')` are both null against the demo's
     * configuration for exactly the same reason, so this is the loader's
     * existing behaviour and not something about this key. What follows is that
     * "defaults to on" is carried jointly: the schema declares `true`, which is
     * what `docs/reference/configuration.md` documents and what a reader
     * passing a fallback must agree with, and `demo/index.php` does.
     */
    public function testTheAuditLogIsADeclaredKeyThatDefaultsToOn(): void
    {
        $audit = RootSchema::create()->key('audit');

        self::assertInstanceOf(SchemaKey::class, $audit, 'RootSchema declares no audit key.');
        self::assertNotNull($audit->children, 'audit declares no children, so audit.enabled cannot exist.');

        $enabled = $audit->children->key('enabled');

        self::assertInstanceOf(SchemaKey::class, $enabled, 'RootSchema declares no audit.enabled.');
        self::assertSame(ValueType::Bool, $enabled->type);
        self::assertTrue($enabled->default, 'Specification 9.4: the audit log defaults to on.');
        self::assertNotSame('', $enabled->description, 'An undocumented key fails ReferenceIsCurrentTest.');
    }

    /** @return array{Config, PageRepository} */
    private function demoConfiguration(): array
    {
        $root = \dirname(__DIR__, 3) . '/demo/config';
        $env = static fn (string $name): ?string => null;
        $config = (new Loader($root, $env))->load();

        return [$config, new PageRepository($root . '/pages', $env, $config->enums())];
    }

    private function seeded(?Connection $connection): Connection
    {
        $connection = $this->requireConnection($connection);

        if (!self::$seeded) {
            self::runSeed();

            self::$seeded = true;
        }

        return $connection;
    }

    /**
     * Runs the shipped script, against every server the environment names.
     *
     * A subprocess rather than `require`, because the script is a script: it
     * echoes, it exits, and calling it in-process would put its functions and
     * its constant into the test run. `PHP_BINARY` is the interpreter running
     * the suite, so it is the one the person reading a failure would type.
     */
    private static function runSeed(): void
    {
        $script = \dirname(__DIR__, 3) . '/demo/seed.php';
        $command = escapeshellarg(\PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1';
        $output = [];
        $status = 0;

        exec($command, $output, $status);

        self::assertSame(
            0,
            $status,
            "demo/seed.php failed (exit {$status}):\n" . implode("\n", $output),
        );
    }
}
