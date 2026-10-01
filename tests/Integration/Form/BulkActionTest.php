<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
use RockAdmin\Http\NotFoundException;
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
 * A bulk action over a real database, on every configured driver.
 *
 * The thing being proved is atomicity, and it cannot be proved against a fake
 * write handler: a transaction is the database's, and whether forty rows roll
 * back when the fortieth refuses is a question about `Connection::transaction()`
 * and the driver underneath it. Everything else here could be a unit test and
 * is not, because it would then be asserting about the same stubs that make
 * the atomicity question untestable.
 *
 * Its own table, created in `setUp()` and dropped in `tearDown()`, for the
 * reason `WriteFlowTest` has one: a failing assertion must not leave the
 * shared read-only fixtures half-written.
 */
#[CoversClass(ActionHandler::class)]
final class BulkActionTest extends DatabaseTestCase
{
    /** Dropped before and after, so a failing assertion leaves nothing behind. */
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

        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-bulk-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);
        $this->writePage();

        $this->session = new ArraySessionStore();

        foreach (self::connections() as [$connection]) {
            if ($connection !== null) {
                $this->createFixture($connection);
            }
        }
    }

    protected function tearDown(): void
    {
        foreach (self::connections() as [$connection]) {
            if ($connection !== null) {
                $connection->execute(new Sql('DROP TABLE IF EXISTS ra_test_bulk'));

                foreach (self::TABLES as $table) {
                    $connection->execute(new Sql("DROP TABLE IF EXISTS {$table}"));
                }
            }
        }

        $this->remove($this->root);

        parent::tearDown();
    }

    #[DataProvider('connections')]
    public function testASelectionIsArchivedInOneGo(?Connection $connection): void
    {
        $connection = $this->require($connection);

        $this->post($connection, 'archive', [FormFields::IDS => ['1', '2', '3']]);

        $this->assertSame(
            ['archived', 'archived', 'archived', 'active', 'sold'],
            array_column($this->rows($connection), 'state'),
        );
    }

    #[DataProvider('connections')]
    public function testARowThatDoesNotQualifyIsSkippedAndTheRestStillApply(?Connection $connection): void
    {
        $connection = $this->require($connection);

        // Row 5 is sold, and `archive` is offered only while a row is active.
        // A selection is an argument, not an authorisation: ticking five boxes
        // says "these ones", never "do it to these whatever they are".
        $this->post($connection, 'archive', [FormFields::IDS => ['1', '5']]);

        $this->assertSame(
            ['archived', 'active', 'active', 'active', 'sold'],
            array_column($this->rows($connection), 'state'),
        );
    }

    #[DataProvider('connections')]
    public function testTheReportNamesTheRowsItSkipped(?Connection $connection): void
    {
        $connection = $this->require($connection);

        $this->post($connection, 'archive', [FormFields::IDS => ['1', '5']]);

        $message = implode(' ', $this->flashes());

        $this->assertStringContainsString('1 of 2', $message);
        $this->assertStringContainsString('5', $message, 'A skipped row must be named, not only counted.');
    }

    #[DataProvider('connections')]
    public function testARefusalPartWayThroughLeavesNothingApplied(?Connection $connection): void
    {
        $connection = $this->require($connection);

        // `title` is UNIQUE. Setting every selected row's title to the same
        // value succeeds for the first and refuses on the second, which is a
        // refusal arriving after a write has already happened -- the exact
        // shape the transaction exists for.
        $this->post($connection, 'rename', [FormFields::IDS => ['1', '2', '3']]);

        $this->assertSame(
            ['one', 'two', 'three', 'four', 'five'],
            array_column($this->rows($connection), 'title'),
            'A refusal part way through must leave nothing applied, not the rows before it.',
        );

        // And it reached the refusal rather than passing because nothing was
        // attempted: the assertion above is equally true of a bulk action that
        // skipped every row, or never ran.
        $this->assertStringContainsString(
            'applied to nothing',
            implode(' ', $this->flashes()),
            'The rows are unchanged, but nothing reported a refusal — so this may be passing for the '
            . 'wrong reason.',
        );
    }

    #[DataProvider('connections')]
    public function testARefusalPartWayThroughLeavesNoAuditRowsEither(?Connection $connection): void
    {
        $connection = $this->require($connection);

        $before = $this->auditCount($connection);

        $this->post($connection, 'rename', [FormFields::IDS => ['1', '2', '3']]);

        // The audit row is written inside the same transaction as the write it
        // describes. A log recording a change that rolled back is worse than
        // no log: it is a log that is wrong.
        $this->assertSame($before, $this->auditCount($connection));
    }

    #[DataProvider('connections')]
    public function testEveryRowWritesItsOwnAuditRow(?Connection $connection): void
    {
        $connection = $this->require($connection);

        $before = $this->auditCount($connection);

        $this->post($connection, 'archive', [FormFields::IDS => ['1', '2', '3']]);

        // Three rows, three audit rows. The log answers "what happened to this
        // row", and a row whose change is recorded only in a summary cannot
        // answer it.
        $this->assertSame($before + 3, $this->auditCount($connection));
    }

    #[DataProvider('connections')]
    public function testABulkDeleteRemovesTheSelectionAndNothingElse(?Connection $connection): void
    {
        $connection = $this->require($connection);

        $this->post($connection, 'delete', [FormFields::IDS => ['2', '4']]);

        $this->assertSame(
            ['one', 'three', 'five'],
            array_column($this->rows($connection), 'title'),
        );
    }

    #[DataProvider('connections')]
    public function testAnEmptySelectionSaysSoRatherThanAnsweringNotFound(?Connection $connection): void
    {
        $connection = $this->require($connection);

        // An unchecked checkbox sends nothing, so a selection form submitted
        // with nothing ticked carries no `_ids` at all. Without the marker the
        // form also sends, that body is identical to a single-row action
        // naming no row, and the answer would be 404 "this request names no
        // row" when the truth is "nothing was selected".
        $response = $this->post($connection, 'archive', []);

        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('Nothing was selected', implode(' ', $this->flashes()));
        $this->assertSame(
            ['active', 'active', 'active', 'active', 'sold'],
            array_column($this->rows($connection), 'state'),
        );
    }

    #[DataProvider('connections')]
    public function testADuplicatedKeyIsAppliedOnce(?Connection $connection): void
    {
        $connection = $this->require($connection);

        $before = $this->auditCount($connection);

        $this->post($connection, 'archive', [FormFields::IDS => ['1', '1', '1']]);

        $this->assertSame($before + 1, $this->auditCount($connection), 'One row changed once, so one audit row.');
    }

    #[DataProvider('connections')]
    public function testAnActionThePageDoesNotOfferInBulkIsRefused(?Connection $connection): void
    {
        $connection = $this->require($connection);

        // `touch` is a `post` action of this page, performable one row at a
        // time, and deliberately absent from `bulk_actions`. Offered on a row
        // and offered over a selection are two different permissions, and a
        // page says which by what it lists.
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('no bulk action');

        $this->post($connection, 'touch', [FormFields::IDS => ['1', '2']]);
    }

    // --- harness ---

    private function require(?Connection $connection): Connection
    {
        if ($connection === null) {
            $this->markTestSkipped('No connection configured.');
        }

        return $connection;
    }

    private function createFixture(Connection $connection): void
    {
        // The SDK's own tables, because the audit assertions below are the
        // point of half this file: a bulk action that wrote one summary row,
        // or none, would pass every other test here.
        foreach (self::TABLES as $table) {
            $connection->execute(new Sql("DROP TABLE IF EXISTS {$table}"));
        }

        (new Migrator($connection))->migrate();

        $connection->execute(new Sql('DROP TABLE IF EXISTS ra_test_bulk'));

        $key = $connection->dialect()->name() === 'mysql'
            ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : 'BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY';

        $connection->execute(new Sql(
            "CREATE TABLE ra_test_bulk (id {$key}, title VARCHAR(100) NOT NULL UNIQUE, "
                . 'state VARCHAR(20) NOT NULL)',
        ));

        foreach ([[1, 'one', 'active'], [2, 'two', 'active'], [3, 'three', 'active'],
            [4, 'four', 'active'], [5, 'five', 'sold']] as [$id, $title, $state]) {
            $connection->execute(new Sql(
                'INSERT INTO ra_test_bulk (id, title, state) VALUES (?, ?, ?)',
                [$id, $title, $state],
            ));
        }
    }

    private function writePage(): void
    {
        file_put_contents($this->root . '/pages/bulk.php', <<<'PHP'
            <?php

            return [
                'title' => 'Bulk',
                'entity' => ['table' => 'ra_test_bulk'],
                'actions' => [
                    'archive' => [
                        'type' => 'post',
                        'to' => '@action:archive',
                        'label' => 'Archive',
                        'confirm' => 'Archive these?',
                        'permission' => 'bulk.update',
                        'when' => ['state' => 'active'],
                        'set' => ['state' => 'archived'],
                    ],
                    'rename' => [
                        'type' => 'post',
                        'to' => '@action:rename',
                        'label' => 'Rename',
                        'confirm' => 'Rename these?',
                        'permission' => 'bulk.update',
                        'set' => ['title' => 'collide'],
                    ],
                    'bin' => [
                        'type' => 'post',
                        'to' => '@action:delete',
                        'label' => 'Delete',
                        'confirm' => 'Delete these?',
                        'permission' => 'bulk.delete',
                    ],
                    'touch' => [
                        'type' => 'post',
                        'to' => '@action:touch',
                        'label' => 'Touch',
                        'confirm' => 'Touch it?',
                        'permission' => 'bulk.update',
                        'set' => ['state' => 'touched'],
                    ],
                ],
                'bulk_actions' => ['archive', 'rename', 'bin'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['title' => [], 'state' => []]],
                    'form' => [
                        'type' => 'form',
                        'form' => ['fields' => ['title' => ['type' => 'text', 'required' => true]]],
                    ],
                ],
            ];
            PHP);
    }

    private function repository(): PageRepository
    {
        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );
    }

    /** @param array<string, mixed> $body */
    private function post(Connection $connection, string $verb, array $body): Response
    {
        return $this->handler($connection)->handle(
            new Route('action', ['page' => 'bulk', 'action' => $verb]),
            new Request(
                'POST',
                "a/bulk/{$verb}",
                body: [
                    Csrf::FIELD => (new Csrf($this->session))->token(),
                    FormFields::BULK => '1',
                ] + $body,
            ),
        );
    }

    private function handler(Connection $connection): ActionHandler
    {
        $urls = new UrlGenerator('/admin');
        $csrf = new Csrf($this->session);
        $flashes = new FlashBag($this->session);
        $reader = new RowReader(new SqlRowSource($connection));
        $region = new FormRegion($reader, $urls, $csrf, Access::gateFor($this->repository()), Access::identity());
        $renderer = new Renderer(new TemplateResolver([]), new Escaper(), $urls);

        return new ActionHandler(
            $this->repository(),
            $region,
            new FieldValidator(),
            new SqlWriteHandler($connection),
            $reader,
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
            new SqlAuditLog($connection),
            Access::identity()->user(),
        );
    }

    /** @return list<array<string, mixed>> read with plain SQL, never through the classes under test */
    private function rows(Connection $connection): array
    {
        return $connection->select(new Sql('SELECT * FROM ra_test_bulk ORDER BY id'));
    }

    /** Read with plain SQL, never through the classes under test. */
    private function auditCount(Connection $connection): int
    {
        $count = $connection->scalar(new Sql('SELECT COUNT(*) FROM ra_activity_log'));

        return is_numeric($count) ? (int) $count : 0;
    }

    /** @return list<string> */
    private function flashes(): array
    {
        $messages = [];

        foreach ((new FlashBag($this->session))->take() as $flash) {
            $messages[] = $flash->message;
        }

        return $messages;
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
