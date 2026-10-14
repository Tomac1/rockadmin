<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Auth\AdminUsers;
use RockAdmin\Auth\Identity;
use RockAdmin\Auth\LoginOutcome;
use RockAdmin\Auth\Roles;
use RockAdmin\Auth\SqlAuthenticator;
use RockAdmin\Auth\Workspaces;
use RockAdmin\Config\Enums;
use RockAdmin\Db\Connection;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Migrator;
use RockAdmin\Db\Sql;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\NotFoundException;
use RockAdmin\Http\Request;
use RockAdmin\Http\Response;
use RockAdmin\Http\Route;
use RockAdmin\Http\SessionStore;
use RockAdmin\Http\SetupHandler;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\DatabaseTestCase;
use RockAdmin\View\Assets;
use RockAdmin\View\Escaper;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * The first administrator, and the page that stops existing afterwards.
 *
 * Two claims, and the second is the one worth a test rather than a comment:
 *
 * 1. a freshly migrated admin can be got into without writing an Argon2id
 *    hash into the database by hand;
 * 2. the page is gone the moment that has happened -- **including the POST**.
 *    A guard on the form alone is a guard that a left-open tab walks past, and
 *    the obvious race is two people opening it on a new installation at the
 *    same moment.
 *
 * And it answers 404 rather than 403, because a 403 tells whoever found the
 * URL that the installation is live and somebody is already in it.
 */
#[CoversClass(SetupHandler::class)]
final class SetupTest extends DatabaseTestCase
{
    /** @var list<string> dropped in dependency order, children first */
    private const TABLES = [
        'ra_activity_log',
        'ra_password_resets',
        'ra_login_attempts',
        'ra_users',
        'ra_migrations',
    ];

    private string $root;

    private SessionStore $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = new ArraySessionStore();
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-setup-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);
        file_put_contents(
            $this->root . '/pages/ads.php',
            "<?php\n\nreturn ['title' => 'Ads', 'entity' => ['table' => 'ra_test_ads'], "
            . "'regions' => ['grid' => ['type' => 'list', 'columns' => ['id' => []]]]];\n",
        );

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

        $this->remove($this->root);

        parent::tearDown();
    }

    #[DataProvider('connections')]
    public function testAFreshInstallationOffersTheForm(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $response = $this->get($connection);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('This admin has no administrators yet', $response->body);
        self::assertStringContainsString('Superadmin', $response->body, 'The page does not say what it grants.');
    }

    #[DataProvider('connections')]
    public function testItCreatesASuperadminWhoCanSignIn(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $response = $this->post($connection, [
            SetupHandler::NAME_FIELD => 'Jana',
            SetupHandler::EMAIL_FIELD => 'Jana@Example.com',
            SetupHandler::PASSWORD_FIELD => 'the-first-password',
            SetupHandler::CONFIRM_FIELD => 'the-first-password',
        ]);

        self::assertSame(302, $response->status);

        $rows = $connection->select(new Sql('SELECT email, name, roles, workspaces FROM ra_users'));

        self::assertCount(1, $rows);
        self::assertSame('jana@example.com', $rows[0]['email'], 'The address was stored as typed.');
        self::assertSame('Jana', $rows[0]['name']);
        // The two JSON columns, read as whatever the driver hands back:
        // PostgreSQL returns JSONB as a string, MySQL returns JSON as one too,
        // and neither promises it.
        self::assertStringContainsString('superadmin', $this->text($rows[0]['roles'] ?? null));
        self::assertStringContainsString(
            'cyklobazar',
            $this->text($rows[0]['workspaces'] ?? null),
            'The first account was given no workspaces, so it can see nothing.',
        );

        self::assertSame(
            LoginOutcome::Success,
            (new SqlAuthenticator($connection))
                ->attempt('jana@example.com', 'the-first-password', '10.0.0.1')
                ->outcome,
        );
    }

    #[DataProvider('connections')]
    public function testTheNewAdministratorIsSignedInAlready(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->post($connection, [
            SetupHandler::NAME_FIELD => 'Jana',
            SetupHandler::EMAIL_FIELD => 'jana@example.com',
            SetupHandler::PASSWORD_FIELD => 'the-first-password',
            SetupHandler::CONFIRM_FIELD => 'the-first-password',
        ]);

        $identity = new Identity($this->session, new SqlAuthenticator($connection));

        self::assertTrue($identity->isAuthenticated(), 'They were made and then left at the login page.');
        self::assertSame('jana@example.com', $identity->user()?->email);
    }

    #[DataProvider('connections')]
    public function testOnceThereIsAnAdministratorTheFormIsGone(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        (new AdminUsers($connection))->create('petr@example.com', 'Petr', 'a-password-here', ['superadmin'], []);

        $this->expectException(NotFoundException::class);

        $this->get($connection);
    }

    /** The check that matters: on the write, not only on the form. */
    #[DataProvider('connections')]
    public function testOnceThereIsAnAdministratorThePostIsGoneToo(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        (new AdminUsers($connection))->create('petr@example.com', 'Petr', 'a-password-here', ['superadmin'], []);

        try {
            $this->post($connection, [
                SetupHandler::NAME_FIELD => 'Jana',
                SetupHandler::EMAIL_FIELD => 'jana@example.com',
                SetupHandler::PASSWORD_FIELD => 'the-first-password',
                SetupHandler::CONFIRM_FIELD => 'the-first-password',
            ]);

            self::fail('A second administrator was created through the setup page.');
        } catch (NotFoundException $e) {
            self::assertStringContainsString('already has an administrator', $e->getMessage());
        }

        self::assertCount(
            1,
            $connection->select(new Sql('SELECT id FROM ra_users')),
            'A form left open in a tab created a second administrator.',
        );
    }

    /**
     * The race the emptiness check cannot close, closed by an index.
     *
     * Two requests reaching `_setup` on a fresh installation both read "no
     * administrators" before either writes one, and their addresses differ --
     * so the unique index on the address lets both through. `founder` is the
     * column only one row may hold, and this is what it is for: an endpoint
     * that by its nature has no authentication, during the minute between
     * `migrate` and somebody filling the form in.
     */
    #[DataProvider('connections')]
    public function testOnlyOneAccountCanEverBeTheFounder(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $users = new AdminUsers($connection);

        $users->create('first@example.com', 'First', 'a-password-here', ['superadmin'], [], founder: true);

        try {
            $users->create('second@example.com', 'Second', 'a-password-here', ['superadmin'], [], founder: true);

            self::fail('A second founder was created, so two strangers can both set an admin up.');
        } catch (DbException $e) {
            self::assertStringContainsString('23', (string) $e->sqlState, 'Refused by something other than the index.');
        }

        self::assertCount(1, $connection->select(new Sql('SELECT id FROM ra_users')));
    }

    #[DataProvider('connections')]
    public function testAnOrdinaryAccountClaimsNothing(?Connection $connection): void
    {
        // Every other row writes NULL, and both servers permit any number of
        // those in a unique index -- otherwise the second administrator an
        // installation ever creates would be refused.
        $connection = $this->requireConnection($connection);
        $users = new AdminUsers($connection);

        $users->create('first@example.com', 'First', 'a-password-here', ['superadmin'], [], founder: true);
        $users->create('second@example.com', 'Second', 'a-password-here', ['viewer'], []);
        $users->create('third@example.com', 'Third', 'a-password-here', ['viewer'], []);

        self::assertCount(3, $connection->select(new Sql('SELECT id FROM ra_users')));
    }

    #[DataProvider('connections')]
    public function testAPostWithNoTokenCreatesNobody(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $response = $this->handler($connection)->handle(
            new Route('setup.submit', []),
            new Request('POST', '_setup', body: [
                SetupHandler::NAME_FIELD => 'Jana',
                SetupHandler::EMAIL_FIELD => 'jana@example.com',
                SetupHandler::PASSWORD_FIELD => 'the-first-password',
                SetupHandler::CONFIRM_FIELD => 'the-first-password',
            ]),
        );

        self::assertSame(403, $response->status);
        self::assertSame([], $connection->select(new Sql('SELECT id FROM ra_users')));
    }

    #[DataProvider('connections')]
    public function testAShortPasswordOrAMistypedConfirmationIsRefused(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $short = $this->post($connection, [
            SetupHandler::NAME_FIELD => 'Jana',
            SetupHandler::EMAIL_FIELD => 'jana@example.com',
            SetupHandler::PASSWORD_FIELD => 'short',
            SetupHandler::CONFIRM_FIELD => 'short',
        ]);

        self::assertSame(422, $short->status);
        self::assertStringContainsString('at least', $short->body);
        self::assertStringContainsString('value="Jana"', $short->body, 'The form lost what was typed.');

        $mismatched = $this->post($connection, [
            SetupHandler::NAME_FIELD => 'Jana',
            SetupHandler::EMAIL_FIELD => 'jana@example.com',
            SetupHandler::PASSWORD_FIELD => 'the-first-password',
            SetupHandler::CONFIRM_FIELD => 'something-else',
        ]);

        self::assertSame(422, $mismatched->status);
        self::assertStringContainsString('not the same', $mismatched->body);

        self::assertSame([], $connection->select(new Sql('SELECT id FROM ra_users')));
    }

    #[DataProvider('connections')]
    public function testTheFormNeverCarriesAPasswordBack(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $response = $this->post($connection, [
            SetupHandler::NAME_FIELD => 'Jana',
            SetupHandler::EMAIL_FIELD => 'jana@example.com',
            SetupHandler::PASSWORD_FIELD => 'a-perfectly-good-password',
            SetupHandler::CONFIRM_FIELD => 'something-else',
        ]);

        self::assertStringNotContainsString('a-perfectly-good-password', $response->body);
    }

    // --- harness ---

    private function get(Connection $connection): Response
    {
        return $this->handler($connection)->handle(new Route('setup.form', []), new Request('GET', '_setup'));
    }

    /** @param array<string, mixed> $body */
    private function post(Connection $connection, array $body): Response
    {
        return $this->handler($connection)->handle(
            new Route('setup.submit', []),
            new Request('POST', '_setup', body: [Csrf::FIELD => (new Csrf($this->session))->token()] + $body),
        );
    }

    private function handler(Connection $connection): SetupHandler
    {
        $urls = new UrlGenerator('/admin');
        $pages = new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );

        return new SetupHandler(
            new AdminUsers($connection),
            new Identity($this->session, new SqlAuthenticator($connection)),
            new SqlAuthenticator($connection),
            Roles::fromConfig([
                // Declared second on purpose: the role is chosen by what it
                // grants, not by being first and not by being called
                // `superadmin`, which an installation is free to rename.
                'viewer' => ['permissions' => ['*.view']],
                'superadmin' => ['label' => 'Superadmin', 'permissions' => ['*']],
            ], ['ads.view', 'ads.update']),
            Workspaces::fromConfig([
                'cyklobazar' => ['vars' => ['tenant_id' => 1], 'pages' => '@all'],
                'motobazar' => ['vars' => ['tenant_id' => 2], 'pages' => '@all'],
            ], $pages),
            new Csrf($this->session),
            new Renderer(new TemplateResolver([]), new Escaper(), $urls),
            new Assets($urls),
            $urls,
            'RockAdmin',
        );
    }

    private function text(mixed $value): string
    {
        if ($value === null || \is_scalar($value)) {
            return (string) $value;
        }

        $this->fail('Expected a scalar, got ' . get_debug_type($value));
    }

    private function drop(Connection $connection): void
    {
        foreach (self::TABLES as $table) {
            $connection->execute(new Sql("DROP TABLE IF EXISTS {$table}"));
        }
    }

    private function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $this->remove($path . '/' . $entry);
        }

        rmdir($path);
    }
}
