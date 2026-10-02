<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Auth;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Auth\RequestScope;
use RockAdmin\Auth\RowWorkspace;
use RockAdmin\Auth\SessionWorkspace;
use RockAdmin\Auth\Workspaces;
use RockAdmin\Config\Enums;
use RockAdmin\Data\Operation;
use RockAdmin\Data\OperationContext;
use RockAdmin\Data\Operations;
use RockAdmin\Db\Connection;
use RockAdmin\Db\RowReader;
use RockAdmin\Db\Sql;
use RockAdmin\Db\SqlRowSource;
use RockAdmin\Db\SqlWriteHandler;
use RockAdmin\Db\WriteHandler;
use RockAdmin\Db\WriteResult;
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
use RockAdmin\Tests\Support\RecordingWorkspaceContext;
use RockAdmin\View\Assets;
use RockAdmin\View\Escaper;
use RockAdmin\View\FlashBag;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * The host being told which workspace this write, and this form, belongs to.
 *
 * The case that makes it necessary is "all workspaces": nothing is active,
 * every row still belongs to a tenant, and a project's own code follows its
 * own idea of the current tenant rather than RockAdmin's scope. A superadmin
 * editing one ad of Motobazar.cz must have Motobazar.cz active while that
 * runs — not none of them, and above all not the other one.
 *
 * The `WorkspaceContext` here records what it was told and what was active
 * when, because the claim is about *when*: a switch that happened after the
 * write, or that stayed switched afterwards, would both pass a test that only
 * looked at the end state.
 *
 * Both drivers, and a real database, because the row's workspace is read out
 * of the row.
 */
#[CoversClass(RowWorkspace::class)]
#[CoversClass(ActionHandler::class)]
#[CoversClass(FormHandler::class)]
final class WorkspaceContextTest extends DatabaseTestCase
{
    /** Cyklobazar's row, which is tenant 1. */
    private const string THEIRS = '1';

    /** Motobazar's row, which is tenant 2. */
    private const string OURS = '2';

    private string $root;

    private SessionStore $session;

    /** What the host was told, and when. */
    private RecordingWorkspaceContext $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-wsctx-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);
        $this->writePages();

        $this->session = new ArraySessionStore();
        $this->host = new RecordingWorkspaceContext();

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
            }
        }

        $this->remove($this->root);

        parent::tearDown();
    }

    /**
     * The test this task exists for. Nothing active, and a write to a row of
     * each workspace in turn: each runs in its own.
     */
    #[DataProvider('connections')]
    public function testUnderAllWorkspacesAWriteRunsInTheRowsOwnWorkspace(?Connection $connection): void
    {
        $connection = $this->require($connection);
        $this->allWorkspaces();

        $this->post($connection, 'update', [FormFields::ID => self::THEIRS, 'title' => 'edited']);

        self::assertSame(
            ['cyklobazar'],
            $this->host->seen,
            'A write to another workspace\'s row did not run in that workspace.',
        );

        $this->host->seen = [];
        $this->post($connection, 'update', [FormFields::ID => self::OURS, 'title' => 'edited too']);

        self::assertSame(['motobazar'], $this->host->seen);
    }

    /**
     * With one workspace active, that is the answer and the database is not
     * asked: the scope is in the `WHERE`, so a row this request can reach is
     * a row in this workspace.
     */
    #[DataProvider('connections')]
    public function testWithAWorkspaceActiveTheWriteRunsInItWithoutReadingTheRow(?Connection $connection): void
    {
        $connection = $this->require($connection);
        $this->inWorkspace('motobazar');

        $this->post($connection, 'update', [FormFields::ID => self::OURS, 'title' => 'edited']);

        self::assertSame(['motobazar'], $this->host->seen);
    }

    /** Whatever was active before the write is active again afterwards, raise or no raise. */
    #[DataProvider('connections')]
    public function testTheHostIsPutBackWhereItWas(?Connection $connection): void
    {
        $connection = $this->require($connection);
        $this->allWorkspaces();

        $this->post($connection, 'update', [FormFields::ID => self::THEIRS, 'title' => 'edited']);

        self::assertNull($this->host->active, 'A write left a workspace switched on behind it.');
    }

    /** A page whose rows are not divided by workspace switches nothing. */
    #[DataProvider('connections')]
    public function testAPageWithNoWorkspaceInItsScopeSwitchesNothing(?Connection $connection): void
    {
        $connection = $this->require($connection);
        $this->allWorkspaces();

        $this->post($connection, 'update', [FormFields::ID => self::THEIRS, 'title' => 'edited'], 'notes');

        self::assertSame(
            ['(none)'],
            $this->host->seen,
            'A page nobody divided by workspace told the host to switch to one.',
        );
    }

    /**
     * What the operation itself is told, which must be the row's workspace and
     * not the one the reader is looking at — there is no such thing here.
     */
    #[DataProvider('connections')]
    public function testTheOperationIsHandedTheRowsOwnWorkspace(?Connection $connection): void
    {
        $connection = $this->require($connection);
        $this->allWorkspaces();

        $this->post($connection, 'archive', [FormFields::ID => self::THEIRS], 'ads', $this->reporting());

        self::assertSame(
            ['context=cyklobazar', 'host=cyklobazar'],
            $this->host->reported,
            'The operation was told the wrong workspace, or ran outside it.',
        );
    }

    /**
     * A bulk action over all workspaces: each row's side effect runs in that
     * row's own workspace, after the commit. Running them all in whichever was
     * last would send one tenant's mail as another.
     */
    #[DataProvider('connections')]
    public function testEachRowsSideEffectRunsInThatRowsWorkspace(?Connection $connection): void
    {
        $connection = $this->require($connection);
        $this->allWorkspaces();

        $this->post(
            $connection,
            'archive',
            [FormFields::BULK => '1', FormFields::IDS => [self::THEIRS, self::OURS]],
            'ads',
            $this->mailing(),
        );

        self::assertSame(
            ['mail:cyklobazar', 'mail:motobazar'],
            $this->host->reported,
            'The side effects did not each run in their own row\'s workspace.',
        );
    }

    /** A form render, which asks the project for things a scope cannot reach. */
    #[DataProvider('connections')]
    public function testAFormRenderRunsInTheRowsWorkspace(?Connection $connection): void
    {
        $connection = $this->require($connection);
        $this->allWorkspaces();

        $response = $this->forms($connection)->handle(
            new Route('page.edit', ['page' => 'ads', 'id' => self::THEIRS]),
            new Request('GET', 'p/ads/' . self::THEIRS . '/edit'),
        );

        self::assertSame(200, $response->status);
        self::assertContains('cyklobazar', $this->host->seen, 'The form was drawn outside the row\'s workspace.');
        self::assertNotContains('(none)', $this->host->seen, 'Part of the render ran in no workspace.');
        self::assertNull($this->host->active, 'A render left a workspace switched on behind it.');
    }

    /** A create form has no row, so there is no row's workspace to find. */
    #[DataProvider('connections')]
    public function testACreateFormUnderAllWorkspacesRunsInNoneOfThem(?Connection $connection): void
    {
        $connection = $this->require($connection);
        $this->allWorkspaces();

        $this->forms($connection)->handle(
            new Route('page.create', ['page' => 'ads']),
            new Request('GET', 'p/ads/new'),
        );

        self::assertSame(['(none)', '(none)'], $this->host->seen);
    }

    // --- harness ---

    /** Nothing active: the state only somebody holding every workspace can reach. */
    private function allWorkspaces(): void
    {
        $this->active()->switchTo(null);
    }

    private function inWorkspace(string $name): void
    {
        $this->active()->switchTo($this->workspaces()->get($name));
    }

    private function active(): SessionWorkspace
    {
        return new SessionWorkspace($this->session, $this->workspaces());
    }

    private function workspaces(): Workspaces
    {
        return Workspaces::fromConfig([
            'cyklobazar' => ['vars' => ['tenant_id' => 1], 'pages' => '@all'],
            'motobazar' => ['vars' => ['tenant_id' => 2], 'pages' => '@all'],
        ], $this->repository());
    }

    /** Somebody holding both, which is what "all workspaces" requires. */
    private function scope(): RequestScope
    {
        return new RequestScope(
            $this->active(),
            Access::identityFor(Access::user(workspaces: ['cyklobazar', 'motobazar'])),
            $this->workspaces(),
        );
    }

    /**
     * @param array<string, mixed>     $body
     * @param array<string, Operation> $operations
     */
    private function post(
        Connection $connection,
        string $verb,
        array $body,
        string $page = 'ads',
        array $operations = [],
    ): Response {
        return $this->actions($connection, $operations)->handle(
            new Route('action', ['page' => $page, 'action' => $verb]),
            new Request(
                'POST',
                "a/{$page}/{$verb}",
                body: [Csrf::FIELD => (new Csrf($this->session))->token()] + $body,
            ),
        );
    }

    /** @param array<string, Operation> $operations */
    private function actions(Connection $connection, array $operations = []): ActionHandler
    {
        $urls = new UrlGenerator('/admin');
        $csrf = new Csrf($this->session);
        $flashes = new FlashBag($this->session);
        $scope = $this->scope();
        $reader = new RowReader(new SqlRowSource($connection));

        return new ActionHandler(
            $this->repository(),
            new FormRegion($reader, $urls, $csrf, Access::gateFor($this->repository()), Access::identity(), $scope),
            new FieldValidator(),
            new SqlWriteHandler($connection),
            $reader,
            $scope,
            $connection,
            $this->forms($connection),
            $csrf,
            $flashes,
            $urls,
            null,
            Access::identity()->user(),
            Operations::of($operations),
            new RowWorkspace($scope, $reader),
            $this->host,
        );
    }

    private function forms(Connection $connection): FormHandler
    {
        $urls = new UrlGenerator('/admin');
        $scope = $this->scope();
        $reader = new RowReader(new SqlRowSource($connection));

        return new FormHandler(
            $this->repository(),
            new FormRegion(
                $reader,
                $urls,
                new Csrf($this->session),
                Access::gateFor($this->repository()),
                Access::identity(),
                $scope,
            ),
            new Renderer(new TemplateResolver([]), new Escaper(), $urls),
            new Assets($urls),
            new FlashBag($this->session),
            $urls,
            'RockAdmin',
            'auto',
            null,
            null,
            new RowWorkspace($scope, $reader),
            $this->host,
        );
    }

    /**
     * An operation that says what it was told and what the host thinks is active.
     *
     * @return array<string, Operation>
     */
    private function reporting(): array
    {
        $host = $this->host;

        return ['archive_ad' => $this->operation(static function (OperationContext $context) use ($host): null {
            $host->reported[] = 'context=' . ($context->workspace->name ?? '(none)');
            $host->reported[] = 'host=' . ($host->active ?? '(none)');

            return null;
        })];
    }

    /**
     * An operation whose side effect reports which workspace it ran in.
     *
     * @return array<string, Operation>
     */
    private function mailing(): array
    {
        $host = $this->host;

        return ['archive_ad' => $this->operation(static function (OperationContext $context) use ($host): null {
            $context->afterCommit(static function () use ($host): void {
                $host->reported[] = 'mail:' . ($host->active ?? '(none)');
            });

            return null;
        })];
    }

    /** @param Closure(OperationContext): null $work */
    private function operation(Closure $work): Operation
    {
        return new class ($work) implements Operation {
            /** @param Closure(OperationContext): null $work */
            public function __construct(private readonly Closure $work)
            {
            }

            public function perform(
                OperationContext $context,
                array $row,
                array $values,
                WriteHandler $writes,
            ): ?WriteResult {
                return ($this->work)($context);
            }
        };
    }

    private function repository(): PageRepository
    {
        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );
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
        $connection->execute(new Sql('DROP TABLE IF EXISTS ra_test_ads'));

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
    }

    /**
     * Two pages: one divided by workspace and one not.
     *
     * The second is the control. "Switch to the row's workspace" has to mean
     * nothing at all on a page whose rows are shared — a table of categories,
     * a list of countries — and a host told to switch there would be a host
     * switching on every write in the admin.
     */
    private function writePages(): void
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
                        'handler' => '@handler:archive_ad',
                    ],
                ],
                'bulk_actions' => ['archive'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['id' => [], 'title' => [], 'state' => []]],
                    'form' => [
                        'type' => 'form',
                        'form' => ['fields' => ['title' => ['type' => 'text', 'required' => true]]],
                    ],
                ],
            ];
            PHP);

        file_put_contents($this->root . '/pages/notes.php', <<<'PHP'
            <?php

            return [
                'title' => 'Notes',
                'entity' => ['table' => 'ra_test_ads'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['title' => []]],
                    'form' => [
                        'type' => 'form',
                        'form' => ['fields' => ['title' => ['type' => 'text', 'required' => true]]],
                    ],
                ],
            ];
            PHP);
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
