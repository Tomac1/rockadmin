<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Auth\AuditLog;
use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\SqlAuditLog;
use RockAdmin\Config\Enums;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Migrator;
use RockAdmin\Db\RowReader;
use RockAdmin\Db\Sql;
use RockAdmin\Db\SqlRowSource;
use RockAdmin\Db\SqlWriteHandler;
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
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\Access;
use RockAdmin\Tests\Support\DatabaseTestCase;
use RockAdmin\View\Assets;
use RockAdmin\View\Escaper;
use RockAdmin\View\FlashBag;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * The audit log, driven the way it is actually reached: a POST to the action
 * route, a real `SqlWriteHandler` over a real connection, and the resulting
 * `ra_activity_log` row read back with plain SQL rather than through any of
 * the classes under test.
 *
 * It is driven through `ActionHandler` rather than through `SqlAuditLog`
 * alone on purpose. Where the log hooks is the decision this task makes, and
 * a test that called `SqlAuditLog::record()` itself would prove only that the
 * INSERT works — it would stay green if the hook were removed.
 *
 * Two fixtures matter and are deliberate:
 *
 * - `ra_test_audit.user_id` is the row's **owner**, and the acting user is
 *   somebody else. The log must name the person who pressed the button, and
 *   the only way to catch a reader of the row's own `user_id` column is to
 *   make the two values differ.
 * - `ra_test_audit.password` exists because a project is free to put a table
 *   with a password column on a page — pages are configuration — so a secret
 *   can reach this path today. It is not a hypothetical guard.
 *
 * Like `LoginTest`, this creates and drops the SDK's own `ra_` tables in both
 * `setUp()` and `tearDown()`, on every configured connection: a failing
 * assertion here must not leave `ra_activity_log` behind for the next test.
 */
#[CoversClass(SqlAuditLog::class)]
#[CoversClass(ActionHandler::class)]
final class AuditLogTest extends DatabaseTestCase
{
    /** @var list<string> dropped in dependency order, children first */
    private const TABLES = [
        'ra_activity_log',
        'ra_password_resets',
        'ra_login_attempts',
        'ra_users',
        'ra_migrations',
        'ra_test_audit',
    ];

    /** The row's owner — never the acting user. */
    private const OWNER_ID = 99;

    private string $root;

    private SessionStore $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-audit-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);
        $this->writePage();

        $this->session = new ArraySessionStore();

        $this->dropEverywhere();
    }

    protected function tearDown(): void
    {
        $this->dropEverywhere();

        $this->remove($this->root);

        parent::tearDown();
    }

    #[DataProvider('connections')]
    public function testACreateRecordsOneRowNamingTheUserPageActionKeyAndDiff(?Connection $connection): void
    {
        $connection = $this->fresh($connection);

        $response = $this->post($connection, 'create', [
            'title' => 'Horské kolo',
            'price' => '12000',
            'user_id' => (string) self::OWNER_ID,
            'password' => 'hunter2',
        ]);

        self::assertSame(302, $response->status);

        $log = $this->log($connection);
        self::assertCount(1, $log, 'A create did not write exactly one audit row.');

        $row = $log[0];
        self::assertSame('7', $this->text($row['user_id']), 'The audit row does not name the acting user.');
        self::assertSame('audit', $row['page']);
        self::assertSame('create', $row['action']);
        self::assertSame(
            $this->text($this->rows($connection)[0]['id']),
            $this->text($row['row_id']),
            'The audit row does not name the row that was written.',
        );
        self::assertNotNull($row['at'] ?? null, 'The audit row carries no timestamp.');

        $diff = $this->diff($row);
        self::assertSame([null, 'Horské kolo'], $diff['title'] ?? null);
        self::assertSame(null, ($diff['price'] ?? [null, null])[0]);
        self::assertSame(12000, (int) $this->text(($diff['price'] ?? [null, null])[1]));
    }

    #[DataProvider('connections')]
    public function testAnUpdateRecordsOneRowCarryingOnlyWhatChanged(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $key = $this->create($connection);

        $this->post($connection, 'update', [
            FormFields::ID => $key,
            'title' => 'Silniční kolo',
            'price' => '12000',
            'user_id' => (string) self::OWNER_ID,
            'password' => 'hunter2',
        ]);

        $log = $this->log($connection);
        self::assertCount(2, $log, 'An update did not write exactly one audit row.');

        $row = $log[1];
        self::assertSame('update', $row['action']);
        self::assertSame($key, $this->text($row['row_id']));

        $diff = $this->diff($row);
        self::assertSame(['title'], array_keys($diff), 'The diff names columns that did not change.');
        self::assertSame(['Horské kolo', 'Silniční kolo'], $diff['title']);
    }

    #[DataProvider('connections')]
    public function testADeleteRecordsOneRowCarryingTheRowAsItWas(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $key = $this->create($connection);

        $this->post($connection, 'delete', [FormFields::ID => $key]);

        $log = $this->log($connection);
        self::assertCount(2, $log, 'A delete did not write exactly one audit row.');

        $row = $log[1];
        self::assertSame('delete', $row['action']);
        self::assertSame('audit', $row['page']);
        self::assertSame($key, $this->text($row['row_id']));

        $diff = $this->diff($row);
        self::assertSame(['Horské kolo', null], $diff['title'] ?? null);
        self::assertSame([], $this->rows($connection), 'The row was not actually deleted.');
    }

    /**
     * The fact that somebody pressed Save is itself the record. A no-op update
     * that wrote nothing would leave "who opened this and decided it was
     * fine" unanswerable, which is one of the questions an audit log exists
     * for.
     */
    #[DataProvider('connections')]
    public function testAnUpdateThatChangedNothingRecordsAnEmptyDiffRatherThanNoRow(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $key = $this->create($connection);

        $this->post($connection, 'update', [
            FormFields::ID => $key,
            'title' => 'Horské kolo',
            'price' => '12000',
            'user_id' => (string) self::OWNER_ID,
            'password' => 'hunter2',
        ]);

        $log = $this->log($connection);
        self::assertCount(2, $log, 'An update that changed nothing wrote no audit row.');
        self::assertSame('update', $log[1]['action']);
        self::assertSame([], $this->diff($log[1]), 'An unchanged update recorded a non-empty diff.');
    }

    /**
     * `user_id` on the row is who owns it; the audit log records who changed
     * it. Reading the first for the second produces a trail that exonerates
     * the wrong person, which is worse than no trail at all.
     */
    #[DataProvider('connections')]
    public function testTheLogNamesTheActingUserAndNotTheRowsOwner(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $this->create($connection);

        $row = $this->log($connection)[0];

        self::assertSame(self::OWNER_ID, (int) $this->text($this->rows($connection)[0]['user_id']));
        self::assertSame('7', $this->text($row['user_id']));
    }

    /**
     * A page over a table with a password column is ordinary configuration,
     * so a secret can reach this path today. The column's presence in the
     * diff still records that it changed; its value is not stored.
     */
    #[DataProvider('connections')]
    public function testTheDiffRecordsThatASecretChangedWithoutRecordingTheSecret(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $key = $this->create($connection);

        $this->post($connection, 'update', [
            FormFields::ID => $key,
            'title' => 'Horské kolo',
            'price' => '12000',
            'user_id' => (string) self::OWNER_ID,
            'password' => 'swordfish',
        ]);

        $log = $this->log($connection);

        foreach ($log as $row) {
            $json = $this->text($row['diff']);

            self::assertStringNotContainsString('hunter2', $json, 'A secret reached the audit log.');
            self::assertStringNotContainsString('swordfish', $json, 'A secret reached the audit log.');
        }

        $diff = $this->diff($log[1]);
        self::assertSame(['password'], array_keys($diff));
        self::assertSame([SqlAuditLog::REDACTED, SqlAuditLog::REDACTED], $diff['password']);
    }

    /**
     * What happens when the log itself is broken: the write stands, and the
     * person who made it is told that the record of it does not. Swallowing
     * this would mean an installation whose audit log stopped working looks
     * exactly like one where nobody changed anything.
     */
    #[DataProvider('connections')]
    public function testAFailingLogInsertLeavesTheWriteInPlaceAndSaysSo(?Connection $connection): void
    {
        $connection = $this->fresh($connection);

        $connection->execute(new Sql('DROP TABLE ra_activity_log'));

        $response = $this->post($connection, 'create', [
            'title' => 'Horské kolo',
            'price' => '12000',
            'user_id' => (string) self::OWNER_ID,
            'password' => 'hunter2',
        ]);

        self::assertSame(302, $response->status, 'A failing audit insert failed the write it describes.');
        self::assertCount(1, $this->rows($connection), 'The row the audit log could not record was not stored.');

        $levels = [];
        $messages = [];

        foreach ((new FlashBag($this->session))->take() as $flash) {
            $levels[] = $flash->level;
            $messages[] = $flash->message;
        }

        self::assertContains('danger', $levels, 'A failing audit insert was not reported to anybody.');
        self::assertStringContainsString('audit log', implode("\n", $messages));
    }

    /**
     * The whole path, with a real audit log and a user who is not the row's owner.
     *
     * @param array<string, mixed> $body
     */
    private function post(Connection $connection, string $action, array $body): Response
    {
        return $this->actions($connection)->handle(
            new Route('action', ['page' => 'audit', 'action' => $action]),
            new Request(
                'POST',
                "a/audit/{$action}",
                body: [Csrf::FIELD => (new Csrf($this->session))->token()] + $body,
            ),
        );
    }

    private function actions(Connection $connection): ActionHandler
    {
        $urls = new UrlGenerator('/admin');
        $csrf = new Csrf($this->session);
        $flashes = new FlashBag($this->session);
        $region = new FormRegion(new RowReader(new SqlRowSource($connection)), $urls, $csrf, Access::gateFor($this->repository()), Access::identity());
        $renderer = new Renderer(new TemplateResolver([]), new Escaper(), $urls);

        return new ActionHandler(
            $this->repository(),
            $region,
            new FieldValidator(),
            new SqlWriteHandler($connection),
            new RowReader(new SqlRowSource($connection)),
            null,
            $connection,
            new FormHandler(
                $this->repository(),
                $region,
                $renderer,
                new Assets($urls),
                $flashes,
                $urls,
                'RockAdmin',
                'auto',
            ),
            $csrf,
            $flashes,
            $urls,
            $this->audit($connection),
            new AuthUser('7', 'jana@example.com', 'Jana', ['editor']),
        );
    }

    private function audit(Connection $connection): AuditLog
    {
        return new SqlAuditLog($connection);
    }

    private function create(Connection $connection): string
    {
        $this->post($connection, 'create', [
            'title' => 'Horské kolo',
            'price' => '12000',
            'user_id' => (string) self::OWNER_ID,
            'password' => 'hunter2',
        ]);

        return $this->text($this->rows($connection)[0]['id']);
    }

    private function repository(): PageRepository
    {
        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );
    }

    /** @return list<array<string, mixed>> */
    private function rows(Connection $connection): array
    {
        return $connection->select(new Sql('SELECT * FROM ra_test_audit ORDER BY id'));
    }

    /** @return list<array<string, mixed>> */
    private function log(Connection $connection): array
    {
        return $connection->select(new Sql('SELECT * FROM ra_activity_log ORDER BY id'));
    }

    /**
     * @param  array<string, mixed> $row
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function diff(array $row): array
    {
        $decoded = json_decode($this->text($row['diff'] ?? null), true);

        self::assertIsArray($decoded, 'The audit row does not carry a JSON diff.');

        /** @var array<string, array{0: mixed, 1: mixed}> $decoded */
        return $decoded;
    }

    private function text(mixed $value): string
    {
        if ($value === null || \is_scalar($value)) {
            return (string) $value;
        }

        $this->fail('Expected a scalar, got ' . get_debug_type($value));
    }

    private function fresh(?Connection $connection): Connection
    {
        $connection = $this->requireConnection($connection);

        (new Migrator($connection))->migrate();

        [$key, $owner] = $connection->dialect()->name() === 'mysql'
            ? ['BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'BIGINT UNSIGNED']
            : ['BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY', 'BIGINT'];

        $connection->execute(new Sql(
            "CREATE TABLE ra_test_audit (id {$key}, title VARCHAR(100) NOT NULL UNIQUE, "
                . "price INTEGER, user_id {$owner}, password VARCHAR(255))",
        ));

        return $connection;
    }

    private function writePage(): void
    {
        file_put_contents($this->root . '/pages/audit.php', <<<'PHP'
            <?php

            return [
                'title' => 'Audit',
                'entity' => ['table' => 'ra_test_audit'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['title' => []]],
                    'form' => [
                        'type' => 'form',
                        'form' => [
                            'fields' => [
                                'title' => ['type' => 'text', 'required' => true],
                                'price' => ['type' => 'number'],
                                'user_id' => ['type' => 'number'],
                                'password' => ['type' => 'text'],
                            ],
                        ],
                    ],
                ],
            ];
            PHP);
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
