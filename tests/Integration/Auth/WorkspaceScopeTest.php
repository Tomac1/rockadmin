<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Auth;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Auth\RequestScope;
use RockAdmin\Auth\SessionWorkspace;
use RockAdmin\Auth\Workspaces;
use RockAdmin\Config\Enums;
use RockAdmin\Db\Connection;
use RockAdmin\Db\NoSuchRowException;
use RockAdmin\Db\RowReader;
use RockAdmin\Db\Sql;
use RockAdmin\Db\SqlRowSource;
use RockAdmin\Db\SqlWriteHandler;
use RockAdmin\Form\FieldValidator;
use RockAdmin\Form\FormFields;
use RockAdmin\Form\FormRegion;
use RockAdmin\Grid\CellFormatter;
use RockAdmin\Grid\GridState;
use RockAdmin\Grid\ListRegion;
use RockAdmin\Grid\PreviewRegion;
use RockAdmin\Grid\QueryFactory;
use RockAdmin\Http\ActionHandler;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\FormHandler;
use RockAdmin\Http\NotFoundException;
use RockAdmin\Http\Request;
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
 * The test this milestone exists for.
 *
 * Two workspaces, a row in each, and a user holding one. Then, as that user,
 * every route that touches a row is pointed at the *other* workspace's row —
 * by id, by URL, by posted body — and every one of them has to answer 404
 * and change nothing.
 *
 * It is an integration test because the claim is about SQL. Specification
 * 9.3's reason for putting the scope in `WHERE` rather than in a filter is
 * that "a user could edit it in the URL", and the only way to prove the
 * WHERE is really there is to run it against a database and look at what
 * came back. A fake write handler would assert that this code passes a scope
 * along, which is a different and much weaker claim.
 *
 * Both drivers, because a boundary that holds on MySQL and leaks on
 * PostgreSQL is a boundary that leaks.
 */
#[CoversNothing]
final class WorkspaceScopeTest extends DatabaseTestCase
{
    private string $root;

    private SessionStore $session;

    /** Cyklobazar's row. The user under test is in Motobazar and may not touch it. */
    private const string THEIRS = '1';

    /** Motobazar's row, which the same user may do anything to. */
    private const string OURS = '2';

    // --- reading ---

    #[DataProvider('connections')]
    public function testTheGridShowsOnlyThisWorkspacesRows(?Connection $connection): void
    {
        $view = $this->grid($this->require($connection));

        $this->assertSame(
            ['Motobazar ad'],
            array_map(static fn ($row): string => (string) $row->cells[1]->text, $view->rows),
        );
    }

    #[DataProvider('connections')]
    public function testAPreviewOfAnotherWorkspacesRowIsNotFound(?Connection $connection): void
    {
        $connection = $this->require($connection);

        $this->assertNotNull($this->preview($connection, self::OURS), 'Our own row should preview.');
        $this->assertNull(
            $this->preview($connection, self::THEIRS),
            'A preview reached another workspace by id.',
        );
    }

    #[DataProvider('connections')]
    public function testAFormForAnotherWorkspacesRowIsNotFound(?Connection $connection): void
    {
        $connection = $this->require($connection);

        // Not merely empty: a form that loaded the row would offer to edit it
        // and the write behind it would then be refused, which looks like a
        // bug in the admin rather than a row somebody may not touch.
        $this->assertNotNull($this->editForm($connection, self::OURS));
        $this->assertNull($this->editForm($connection, self::THEIRS));
    }

    // --- writing: the hole this milestone closed ---

    #[DataProvider('connections')]
    public function testUpdatingAnotherWorkspacesRowIsRefusedAndChangesNothing(?Connection $connection): void
    {
        $connection = $this->require($connection);

        $this->expectException(NotFoundException::class);

        try {
            $this->post($connection, 'update', [
                FormFields::ID => self::THEIRS,
                'title' => 'taken over',
            ]);
        } finally {
            $this->assertSame(
                ['Cyklobazar ad', 'Motobazar ad'],
                $this->titles($connection),
                'A row in another workspace was written to.',
            );
        }
    }

    #[DataProvider('connections')]
    public function testDeletingAnotherWorkspacesRowIsRefusedAndChangesNothing(?Connection $connection): void
    {
        $connection = $this->require($connection);

        $this->expectException(NotFoundException::class);

        try {
            $this->post($connection, 'delete', [FormFields::ID => self::THEIRS]);
        } finally {
            $this->assertSame(['Cyklobazar ad', 'Motobazar ad'], $this->titles($connection));
        }
    }

    #[DataProvider('connections')]
    public function testAConfiguredActionOnAnotherWorkspacesRowIsRefused(?Connection $connection): void
    {
        $connection = $this->require($connection);

        $this->expectException(NotFoundException::class);

        try {
            $this->post($connection, 'archive', [FormFields::ID => self::THEIRS]);
        } finally {
            $this->assertSame(
                ['active', 'active'],
                $this->states($connection),
                "A `post` action reached another workspace's row.",
            );
        }
    }

    #[DataProvider('connections')]
    public function testABulkActionSkipsRowsOutsideTheWorkspace(?Connection $connection): void
    {
        $connection = $this->require($connection);

        // Both rows selected, by somebody who may only touch one. A selection
        // is an argument, never an authorisation, and the one they may not
        // reach must not be reachable by putting it in a list.
        $this->post($connection, 'archive', [
            FormFields::BULK => '1',
            FormFields::IDS => [self::THEIRS, self::OURS],
        ]);

        $this->assertSame(
            ['active', 'archived'],
            $this->states($connection),
            'A bulk action reached across the workspace boundary.',
        );
    }

    // --- creating into a workspace, and not out of one ---

    #[DataProvider('connections')]
    public function testACreateLandsInTheActiveWorkspaceWhateverWasSubmitted(?Connection $connection): void
    {
        // Milestone 7 recorded this as a hole and called it "safe until a row
        // scope check exists". A form in a browser is editable, so a hidden
        // input carrying `tenant_id` is a suggestion -- and submitting
        // somebody else's is the first thing anybody tries.
        $connection = $this->require($connection);

        $this->post($connection, 'create', ['title' => 'new one', 'tenant_id' => 1]);

        $this->assertSame(
            [1, 2, 2],
            $this->tenants($connection),
            'A create was placed in a workspace from the submitted body rather than the active one.',
        );
    }

    #[DataProvider('connections')]
    public function testAnUpdateCannotMoveARowToAnotherWorkspace(?Connection $connection): void
    {
        // Moving a row between workspaces is a real operation and this is not
        // it. If it is ever wanted it is an action with its own permission,
        // not a side effect of editing a title.
        $connection = $this->require($connection);

        $this->post($connection, 'update', [
            FormFields::ID => self::OURS,
            'title' => 'edited',
            'tenant_id' => 1,
        ]);

        $this->assertSame([1, 2], $this->tenants($connection), 'An update moved a row out of its workspace.');
        $this->assertSame(['Cyklobazar ad', 'edited'], $this->titles($connection), 'The edit itself was lost.');
    }

    #[DataProvider('connections')]
    public function testEveryWriteOnOurOwnRowStillWorks(?Connection $connection): void
    {
        // The other half, and it matters as much: a boundary that refuses
        // everything is not a boundary, it is an outage.
        $connection = $this->require($connection);

        $this->post($connection, 'update', [FormFields::ID => self::OURS, 'title' => 'edited']);

        $this->assertSame(['Cyklobazar ad', 'edited'], $this->titles($connection));

        $this->post($connection, 'delete', [FormFields::ID => self::OURS]);

        $this->assertSame(['Cyklobazar ad'], $this->titles($connection));
    }

    #[DataProvider('connections')]
    public function testTheScopeReachesTheSqlRatherThanOnlyTheHandler(?Connection $connection): void
    {
        // Driven at the write handler directly, below everything that might
        // be filtering on its behalf. This is the claim 9.3 actually makes —
        // the boundary is in the WHERE — and the only way to see it is to ask
        // the database.
        $connection = $this->require($connection);

        $writes = new SqlWriteHandler($connection);
        $entity = $this->repository()->get('ads')->entity;

        $this->expectException(NoSuchRowException::class);

        try {
            $writes->update($entity, self::THEIRS, ['title' => 'x'], ['tenant_id' => 2]);
        } finally {
            $this->assertSame(['Cyklobazar ad', 'Motobazar ad'], $this->titles($connection));
        }
    }

    // --- harness ---

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-wsscope-' . bin2hex(random_bytes(6));
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
                $connection->execute(new Sql('DROP TABLE IF EXISTS ra_test_ads'));
                $this->createFixtures($connection);
            }
        }

        $this->remove($this->root);

        parent::tearDown();
    }

    private function require(?Connection $connection): Connection
    {
        if ($connection === null) {
            $this->markTestSkipped('No connection configured.');
        }

        return $connection;
    }

    private function createFixture(Connection $connection): void
    {
        $this->dropFixtures($connection);

        $key = $connection->dialect()->name() === 'mysql'
            ? 'INTEGER AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY';

        $connection->execute(new Sql(
            "CREATE TABLE ra_test_ads (id {$key}, tenant_id INTEGER NOT NULL, "
            . "title VARCHAR(200) NOT NULL, state VARCHAR(20) NOT NULL DEFAULT 'active')",
        ));

        foreach ([[1, 1, 'Cyklobazar ad'], [2, 2, 'Motobazar ad']] as [$id, $tenant, $title]) {
            $connection->execute(new Sql(
                'INSERT INTO ra_test_ads (id, tenant_id, title, state) VALUES (?, ?, ?, ?)',
                [$id, $tenant, $title, 'active'],
            ));
        }

        if ($connection->dialect()->name() !== 'mysql') {
            // The rows above were inserted with explicit ids, and PostgreSQL's
            // identity sequence does not notice: it still hands out 1 next, so
            // the create below collides with row 1 and is refused as a
            // duplicate. MySQL's AUTO_INCREMENT moves itself, which is why
            // this only ever failed on one driver -- and why the fixture says
            // so out loud rather than being quietly different.
            $connection->execute(new Sql(
                "SELECT setval(pg_get_serial_sequence('ra_test_ads', 'id'), 2)",
            ));
        }
    }

    private function writePage(): void
    {
        file_put_contents($this->root . '/pages/ads.php', <<<'PHP'
            <?php

            return [
                'title' => 'Ads',
                'entity' => [
                    'table' => 'ra_test_ads',
                    'scope' => ['tenant_id' => '{{workspace.tenant_id}}'],
                ],
                'actions' => [
                    'archive' => [
                        'type' => 'post',
                        'to' => '@action:archive',
                        'confirm' => 'Archive?',
                        'permission' => 'ads.update',
                        'set' => ['state' => 'archived'],
                    ],
                ],
                'bulk_actions' => ['archive'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['id' => [], 'title' => [], 'state' => []]],
                    'preview' => ['type' => 'preview'],
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

    /** The scope of somebody in Motobazar, which is tenant 2. */
    private function scope(): RequestScope
    {
        $workspaces = Workspaces::fromConfig([
            'cyklobazar' => ['vars' => ['tenant_id' => 1], 'pages' => '@all'],
            'motobazar' => ['vars' => ['tenant_id' => 2], 'pages' => '@all'],
        ], $this->repository());

        return new RequestScope(
            new SessionWorkspace($this->session, $workspaces),
            Access::identityFor(Access::user(workspaces: ['motobazar'])),
        );
    }

    private function grid(Connection $connection): \RockAdmin\Grid\ListView
    {
        $page = $this->repository()->get('ads');
        $region = $page->regions['grid'];

        $list = new ListRegion(
            new SqlRowSource($connection),
            new QueryFactory(scope: $this->scope()),
            new CellFormatter(),
            new UrlGenerator('/admin'),
            Access::gateFor($this->repository()),
            Access::identity(),
            new Csrf($this->session),
        );

        return $list->render($page, $region, GridState::fromQuery([], 'grid', $region));
    }

    private function preview(Connection $connection, string $id): ?\RockAdmin\Grid\PreviewView
    {
        $page = $this->repository()->get('ads');

        return (new PreviewRegion(new SqlRowSource($connection), new CellFormatter(), $this->scope()))
            ->render($page, $page->regions['preview'], $id);
    }

    private function editForm(Connection $connection, string $id): ?\RockAdmin\Form\FormView
    {
        $page = $this->repository()->get('ads');

        $region = new FormRegion(
            new RowReader(new SqlRowSource($connection)),
            new UrlGenerator('/admin'),
            new Csrf($this->session),
            Access::gateFor($this->repository()),
            Access::identity(),
            $this->scope(),
        );

        return $region->edit($page, $page->regions['form'], $id, null);
    }

    /** @param array<string, mixed> $body */
    private function post(Connection $connection, string $verb, array $body): void
    {
        $urls = new UrlGenerator('/admin');
        $csrf = new Csrf($this->session);
        $flashes = new FlashBag($this->session);
        $reader = new RowReader(new SqlRowSource($connection));
        $pages = $this->repository();

        $formRegion = new FormRegion(
            $reader,
            $urls,
            $csrf,
            Access::gateFor($pages),
            Access::identity(),
            $this->scope(),
        );

        $handler = new ActionHandler(
            $pages,
            $formRegion,
            new FieldValidator(),
            new SqlWriteHandler($connection),
            $reader,
            $this->scope(),
            $connection,
            new FormHandler(
                $pages,
                $formRegion,
                new Renderer(new TemplateResolver([]), new Escaper(), $urls),
                new Assets($urls),
                $flashes,
                $urls,
                'RockAdmin',
                'auto',
            ),
            $csrf,
            $flashes,
            $urls,
        );

        $handler->handle(
            new Route('action', ['page' => 'ads', 'action' => $verb]),
            new Request('POST', "a/ads/{$verb}", body: [Csrf::FIELD => $csrf->token()] + $body),
        );
    }

    /** @return list<string> read with plain SQL, never through the classes under test */
    private function titles(Connection $connection): array
    {
        return array_map(
            static fn (array $row): string => \is_scalar($row['title']) ? (string) $row['title'] : '',
            $connection->select(new Sql('SELECT title FROM ra_test_ads ORDER BY id')),
        );
    }

    /** @return list<int> which workspace each row is in, read with plain SQL */
    private function tenants(Connection $connection): array
    {
        return array_map(
            static fn (array $row): int => \is_scalar($row['tenant_id']) ? (int) $row['tenant_id'] : 0,
            $connection->select(new Sql('SELECT tenant_id FROM ra_test_ads ORDER BY id')),
        );
    }

    /** @return list<string> */
    private function states(Connection $connection): array
    {
        return array_map(
            static fn (array $row): string => \is_scalar($row['state']) ? (string) $row['state'] : '',
            $connection->select(new Sql('SELECT state FROM ra_test_ads ORDER BY id')),
        );
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
