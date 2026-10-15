<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Data;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Auth\AdminUserOperation;
use RockAdmin\Auth\AdminUsers;
use RockAdmin\Auth\PasswordResets;
use RockAdmin\Auth\Permission;
use RockAdmin\Auth\RequestScope;
use RockAdmin\Auth\Roles;
use RockAdmin\Auth\RowWorkspace;
use RockAdmin\Auth\SessionWorkspace;
use RockAdmin\Auth\SqlAuditLog;
use RockAdmin\Auth\Workspaces;
use RockAdmin\Config\Loader;
use RockAdmin\Data\Operations;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Migrator;
use RockAdmin\Db\RowReader;
use RockAdmin\Db\Sql;
use RockAdmin\Db\SqlRowSource;
use RockAdmin\Db\SqlWriteHandler;
use RockAdmin\Demo\ArchiveAd;
use RockAdmin\Form\FieldValidator;
use RockAdmin\Form\FormFields;
use RockAdmin\Form\FormRegion;
use RockAdmin\Http\ActionHandler;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\FormHandler;
use RockAdmin\Http\Request;
use RockAdmin\Http\Response;
use RockAdmin\Http\Route;
use RockAdmin\Http\SessionStore;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Mail\LogMailer;
use RockAdmin\Mail\Mailbox;
use RockAdmin\Mail\MessageFactory;
use RockAdmin\Page\PageException;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\Access;
use RockAdmin\Tests\Support\DatabaseTestCase;
use RockAdmin\View\Assets;
use RockAdmin\View\Escaper;
use RockAdmin\View\FlashBag;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * The demo's own custom operation, driven through the route that reaches it.
 *
 * `DemoFormTest`'s sibling and for the same reason: `demo/config/pages/ads.php`
 * archives through `@handler:archive_ad` now, and the recipe in
 * `docs/guides/own-write-logic.md` points at `demo/src/ArchiveAd.php` as the
 * worked example. A worked example nothing runs is a worked example that stops
 * working, and whoever found out would be the next person to open the demo.
 *
 * It reads the demo's configuration as it ships -- the page file, the enums,
 * the workspaces -- and builds its own `ra_test_ads` in `demo/seed.php`'s
 * shape, putting `DatabaseTestCase`'s fixtures back in `tearDown()`.
 */
#[CoversClass(ArchiveAd::class)]
#[CoversClass(Operations::class)]
final class DemoOperationTest extends DatabaseTestCase
{
    /**
     * The SDK's own tables, dropped in dependency order, children first.
     *
     * Dropped before and after, like `AuditLogTest` does it: this test
     * migrates, and a failing assertion must not leave `ra_activity_log`
     * behind for whatever runs next.
     *
     * @var list<string>
     */
    private const TABLES = [
        'ra_activity_log',
        'ra_password_resets',
        'ra_login_attempts',
        'ra_users',
        'ra_migrations',
    ];

    /** An ad with a price, which may be archived. */
    private const string PRICED = '1';

    /** An ad with none, which the operation refuses. */
    private const string UNPRICED = '2';

    private SessionStore $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = new ArraySessionStore();

        foreach (self::connections() as [$connection]) {
            if ($connection !== null) {
                $this->createDemoFixture($connection);
            }
        }
    }

    protected function tearDown(): void
    {
        foreach (self::connections() as [$connection]) {
            if ($connection !== null) {
                $this->dropSdkTables($connection);
                $this->createFixtures($connection);
            }
        }

        parent::tearDown();
    }

    /**
     * The demo's wiring and the demo's page file agree about the name.
     *
     * This is the check that would have caught a typo in either, and it is the
     * one the demo itself makes at startup -- `assertEveryHandlerExists()`
     * before a single request is handled.
     */
    #[DataProvider('connections')]
    public function testTheDemoRegistersEveryHandlerItsPagesName(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        // Nothing to assert afterwards: the assertion is that this does not
        // raise, which is what the demo itself relies on at startup.
        $this->expectNotToPerformAssertions();

        $this->operations($connection)->assertEveryHandlerExists($this->repository());
    }

    /** And without the registry it is refused at startup, naming the page. */
    #[DataProvider('connections')]
    public function testADemoWithNothingRegisteredIsRefusedNamingThePage(?Connection $connection): void
    {
        $this->requireConnection($connection);

        try {
            Operations::none()->assertEveryHandlerExists($this->repository());

            self::fail('A page naming logic nothing registered was accepted.');
        } catch (PageException $e) {
            // Whichever page it reaches first: the claim is that it names one,
            // and names the key, rather than which of the demo's pages happens
            // to be walked first.
            self::assertMatchesRegularExpression("/Page '(ads|administrators)'/", $e->getMessage());
            self::assertMatchesRegularExpression('/@handler:(archive_ad|ra_admin_user)/', $e->getMessage());
            self::assertStringContainsString('nothing registered', $e->getMessage());
        }
    }

    /**
     * The operation's own work: both columns, one audit row, and the
     * notification queued for after the commit.
     */
    #[DataProvider('connections')]
    public function testArchivingAnAdRunsTheDemosOwnLogic(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $response = $this->post($connection, 'archive', [FormFields::ID => self::PRICED]);

        self::assertSame(302, $response->status);

        $ad = $this->ad($connection, self::PRICED);
        self::assertSame('archived', $this->text($ad['state']));
        self::assertFalse(
            $this->truthy($ad['featured']),
            'An archived ad was left featured, so it would still be on the front page.',
        );

        self::assertCount(1, $this->audit($connection), 'A custom operation wrote a row that was not audited.');

        $flashes = $this->flashes();
        self::assertStringContainsString('would be told it was archived', $flashes);
        self::assertStringContainsString('Cyklobazar.cz', $flashes, 'The notification named no workspace.');
    }

    /**
     * The refusal: a rule `when` cannot express, answered in words rather than
     * with a 500, and changing nothing.
     */
    #[DataProvider('connections')]
    public function testAnAdWithNoPriceIsRefusedInWords(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $response = $this->post($connection, 'archive', [FormFields::ID => self::UNPRICED]);

        self::assertSame(302, $response->status, 'A refusal was answered as a fault.');

        $ad = $this->ad($connection, self::UNPRICED);
        self::assertSame('active', $this->text($ad['state']), 'A refused operation wrote anyway.');

        self::assertCount(0, $this->audit($connection), 'A refused operation was audited.');

        $flashes = $this->flashes();
        self::assertStringContainsString('has no price', $flashes);
        self::assertStringNotContainsString('would be told', $flashes, 'A refused ad queued its notification.');
    }

    /** Both of them at once, which is what a selection does. */
    #[DataProvider('connections')]
    public function testASelectionArchivesWhatItCanAndSaysWhatItSkipped(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->post($connection, 'archive', [
            FormFields::BULK => '1',
            FormFields::IDS => [self::PRICED, self::UNPRICED],
        ]);

        self::assertSame('archived', $this->text($this->ad($connection, self::PRICED)['state']));
        self::assertSame('active', $this->text($this->ad($connection, self::UNPRICED)['state']));

        $flashes = $this->flashes();
        self::assertStringContainsString('Skipped', $flashes, 'The skipped ad was not reported.');
        self::assertStringContainsString('has no price', $flashes, 'The skip did not say why.');
        self::assertStringContainsString(
            'would be told it was archived',
            $flashes,
            "The archived ad's notification did not run.",
        );
    }

    // --- harness ---

    /**
     * The demo's own registry, as `demo/index.php` builds it.
     *
     * Both of them, because the assertion that matters is that the demo
     * registers *every* handler its pages name -- a registry listing one of
     * two would pass a test written around one of two.
     */
    private function operations(Connection $connection): Operations
    {
        $urls = new UrlGenerator('/');
        $renderer = new Renderer(new TemplateResolver([]), new Escaper(), $urls);
        $config = (new Loader(\dirname(__DIR__, 3) . '/demo/config', static fn (string $n): ?string => null))
            ->load();

        return Operations::of([
            'archive_ad' => new ArchiveAd(new FlashBag($this->session)),
            'ra_admin_user' => new AdminUserOperation(
                new AdminUsers($connection),
                Roles::fromConfig($config->roles(), Permission::catalogue($this->repository())),
                new PasswordResets($connection, 2880),
                new MessageFactory($renderer, new Mailbox('admin@example.com', 'Admin'), 'RockAdmin Demo'),
                new LogMailer(sys_get_temp_dir() . '/ra-demo-op-mail'),
                $urls,
                'https://admin.example.com',
                2880,
            ),
        ]);
    }

    /** @param array<string, mixed> $body */
    private function post(Connection $connection, string $action, array $body): Response
    {
        return $this->actions($connection)->handle(
            new Route('action', ['page' => 'ads', 'action' => $action]),
            new Request(
                'POST',
                "a/ads/{$action}",
                body: [Csrf::FIELD => (new Csrf($this->session))->token()] + $body,
            ),
        );
    }

    private function actions(Connection $connection): ActionHandler
    {
        $urls = new UrlGenerator('/');
        $csrf = new Csrf($this->session);
        $scope = $this->requestScope();
        $reader = new RowReader(new SqlRowSource($connection));
        $region = new FormRegion(
            $reader,
            $urls,
            $csrf,
            Access::gateFor($this->repository()),
            Access::identity(),
            $scope,
        );

        return new ActionHandler(
            $this->repository(),
            $region,
            new FieldValidator(),
            new SqlWriteHandler($connection),
            $reader,
            $scope,
            $connection,
            new FormHandler(
                $this->repository(),
                $region,
                new Renderer(new TemplateResolver([]), new Escaper(), $urls),
                new Assets($urls),
                new FlashBag($this->session),
                $urls,
                'RockAdmin Demo',
                'auto',
            ),
            $csrf,
            new FlashBag($this->session),
            $urls,
            new SqlAuditLog($connection),
            Access::identity()->user(),
            $this->operations($connection),
            new RowWorkspace($scope, $reader),
        );
    }

    /** The scope a reader in Cyklobazar gets, from the demo's own workspaces. */
    private function requestScope(): RequestScope
    {
        $workspaces = Workspaces::fromConfig(
            (new Loader(\dirname(__DIR__, 3) . '/demo/config', static fn (string $n): ?string => null))
                ->load()
                ->workspaces(),
            $this->repository(),
        );

        return new RequestScope(
            new SessionWorkspace(new ArraySessionStore(), $workspaces),
            Access::identityFor(Access::user(workspaces: ['cyklobazar'])),
            $workspaces,
        );
    }

    /** The demo's configuration, read from the files the demo ships. */
    private function repository(): PageRepository
    {
        $root = \dirname(__DIR__, 3) . '/demo/config';
        $env = static fn (string $name): ?string => null;
        $config = (new Loader($root, $env))->load();

        return new PageRepository($root . '/pages', $env, $config->enums());
    }

    /** @return array<string, mixed> */
    private function ad(Connection $connection, string $id): array
    {
        $rows = $connection->select(new Sql('SELECT * FROM ra_test_ads WHERE id = ?', [$id]));

        self::assertCount(1, $rows, "No ad {$id}.");

        return $rows[0];
    }

    /** @return list<array<string, mixed>> */
    private function audit(Connection $connection): array
    {
        return $connection->select(new Sql('SELECT * FROM ra_activity_log ORDER BY id'));
    }

    private function flashes(): string
    {
        $messages = [];

        foreach ((new FlashBag($this->session))->take() as $flash) {
            $messages[] = $flash->message;
        }

        return implode("\n", $messages);
    }

    private function dropSdkTables(Connection $connection): void
    {
        foreach (self::TABLES as $table) {
            $connection->execute(new Sql("DROP TABLE IF EXISTS {$table}"));
        }
    }

    private function text(mixed $value): string
    {
        if ($value === null || \is_scalar($value)) {
            return (string) $value;
        }

        $this->fail('Expected a scalar, got ' . get_debug_type($value));
    }

    /** PostgreSQL returns a BOOLEAN as 't'/'f'; MySQL returns TINYINT(1) as 0/1. */
    private function truthy(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        return \in_array($value, [1, '1', 't', 'true'], true);
    }

    /**
     * `demo/seed.php`'s own table shape, with two ads in Cyklobazar: one
     * priced and featured, one with no price at all.
     */
    private function createDemoFixture(Connection $connection): void
    {
        $this->dropFixtures($connection);

        $mysql = $connection->dialect()->name() === 'mysql';
        $json = $mysql ? 'JSON' : 'JSONB';
        $key = $mysql
            ? 'INTEGER AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY';
        $boolean = $mysql ? 'TINYINT(1)' : 'BOOLEAN';
        $false = $mysql ? '0' : 'FALSE';
        $true = $mysql ? '1' : 'TRUE';

        $connection->execute(new Sql(
            'CREATE TABLE ra_test_users (id INTEGER PRIMARY KEY, company_id INTEGER, '
            . 'name VARCHAR(100) NOT NULL, email VARCHAR(100))',
        ));
        $connection->execute(new Sql(
            "CREATE TABLE ra_test_ads (id {$key}, tenant_id INTEGER NOT NULL DEFAULT 1, user_id INTEGER, "
            . 'title VARCHAR(200) NOT NULL, description TEXT, price INTEGER, state VARCHAR(20), '
            . "featured {$boolean} NOT NULL DEFAULT {$false}, published_on DATE, stats {$json}, "
            // `photo` holds a storage key, as demo/seed.php creates it. The
            // demo's ads page shows it in the grid and uploads to it from the
            // form, so a fixture without it cannot answer the query that page
            // actually builds.
            . 'photo VARCHAR(255), created_at TIMESTAMP)',
        ));
        $connection->execute(new Sql(
            'INSERT INTO ra_test_users (id, company_id, name, email) VALUES (?, ?, ?, ?)',
            [1, 1, 'Jana', 'jana@example.com'],
        ));
        $connection->execute(new Sql(
            'INSERT INTO ra_test_ads (id, tenant_id, user_id, title, price, state, featured) '
            . "VALUES (1, 1, 1, 'Horské kolo', 12000, 'active', {$true})",
        ));
        $connection->execute(new Sql(
            'INSERT INTO ra_test_ads (id, tenant_id, user_id, title, price, state, featured) '
            . "VALUES (2, 1, 1, 'Koloběžka bez ceny', NULL, 'active', {$false})",
        ));

        // The audit log is part of what the demo's write path produces, so it
        // is here: an operation that wrote a row and no audit row would be a
        // rule-7 failure this test is in a position to notice.
        $this->dropSdkTables($connection);
        (new Migrator($connection))->migrate();
    }
}
