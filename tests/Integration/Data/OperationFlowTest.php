<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Data;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Auth\SqlAuditLog;
use RockAdmin\Config\Enums;
use RockAdmin\Data\Operation;
use RockAdmin\Data\OperationContext;
use RockAdmin\Data\OperationRefused;
use RockAdmin\Data\Operations;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Migrator;
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
use RockAdmin\Page\PageException;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\Access;
use RockAdmin\Tests\Support\DatabaseTestCase;
use RockAdmin\View\Assets;
use RockAdmin\View\Escaper;
use RockAdmin\View\FlashBag;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;
use RuntimeException;

/**
 * A project's own write logic, driven the way it is actually reached: a POST
 * to the action route, a real connection, a real audit log, and the rows read
 * back with plain SQL.
 *
 * The seam is only worth anything if three things are true at once, and all
 * three are about what happens when something goes wrong — which is why this
 * is an integration test over both drivers rather than a unit test with a
 * fake `WriteHandler`:
 *
 * 1. an operation that succeeds leaves a row, an audit row, and a side effect
 *    that ran *after* the write was durable;
 * 2. an operation that raises leaves none of the three, including the side
 *    effect — a transaction can take back a row and cannot take back an
 *    email, so the ordering is the guarantee;
 * 3. an operation that says no is answered as a refusal rather than as a
 *    crash, on the form the submission came from.
 *
 * The bulk case is where the ordering is actually load-bearing: forty rows in
 * one transaction, each queueing a side effect, and the thirty-ninth raising.
 * Anything that ran inline would by then have told somebody about
 * thirty-eight changes that are about to be rolled back.
 */
#[CoversClass(ActionHandler::class)]
#[CoversClass(OperationContext::class)]
#[CoversClass(Operations::class)]
final class OperationFlowTest extends DatabaseTestCase
{
    /** @var list<string> dropped in dependency order, children first */
    private const TABLES = [
        'ra_activity_log',
        'ra_password_resets',
        'ra_login_attempts',
        'ra_users',
        'ra_migrations',
        'ra_test_ops',
    ];

    private string $root;

    private SessionStore $session;

    /**
     * What ran, in the order it ran.
     *
     * The operations below append to this, and so do the side effects they
     * queue. Every assertion about ordering reads it, because "after the
     * commit" is a claim about sequence and nothing else can witness it.
     *
     * @var list<string>
     */
    private array $log = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-ops-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);
        $this->writePage();

        $this->session = new ArraySessionStore();
        $this->log = [];

        $this->dropEverywhere();
    }

    protected function tearDown(): void
    {
        $this->dropEverywhere();

        $this->remove($this->root);

        parent::tearDown();
    }

    /**
     * The default is not run as well, and not instead: the row carries what
     * the operation decided and nothing the form submitted for that column.
     */
    #[DataProvider('connections')]
    public function testAFormSaveRunsTheProjectsOperationRatherThanTheDefault(?Connection $connection): void
    {
        $connection = $this->fresh($connection);

        $response = $this->save($connection, [
            'title' => 'Horské kolo',
            'state' => 'active',
        ]);

        self::assertSame(302, $response->status);

        $rows = $this->rows($connection);
        self::assertCount(1, $rows, 'The operation did not write the row.');
        self::assertSame('Horské kolo', $this->text($rows[0]['title']));
        self::assertSame(
            'reviewed',
            $this->text($rows[0]['state']),
            "The default write ran: the submitted 'active' reached the row instead of what the "
                . 'operation decided.',
        );

        self::assertCount(1, $this->audit($connection), 'An operation wrote a row that was not audited.');
        self::assertSame(['wrote', 'mail'], $this->log, 'The side effect did not run after the write.');
    }

    /**
     * The ordinary write, through `writeDefault()`, which is the first line
     * of most operations: they want what RockAdmin does and something else.
     */
    #[DataProvider('connections')]
    public function testAnOperationCanAskForTheWriteRockAdminWouldHaveDone(?Connection $connection): void
    {
        $connection = $this->fresh($connection);

        $this->save($connection, ['title' => 'Silniční kolo', 'state' => 'active'], 'save_default');

        $rows = $this->rows($connection);
        self::assertCount(1, $rows);
        self::assertSame('Silniční kolo', $this->text($rows[0]['title']));
        self::assertSame('active', $this->text($rows[0]['state']), 'writeDefault() changed the values.');
    }

    /**
     * The whole point of `afterCommit()`. The operation writes, queues a side
     * effect and then raises: the row goes, the audit row is never written,
     * and the mail is never sent.
     */
    #[DataProvider('connections')]
    public function testAnOperationThatRaisesLeavesNoRowNoAuditRowAndNoSideEffect(?Connection $connection): void
    {
        $connection = $this->fresh($connection);

        try {
            $this->save($connection, ['title' => 'Horské kolo', 'state' => 'active'], 'save_then_fail');

            self::fail('An operation that raised was answered as a successful save.');
        } catch (RuntimeException $e) {
            self::assertSame('The project decided this cannot work.', $e->getMessage());
        }

        self::assertSame([], $this->rows($connection), 'The row an operation rolled back is still there.');
        self::assertSame([], $this->audit($connection), 'A write that rolled back was audited anyway.');
        self::assertSame(
            ['wrote'],
            $this->log,
            'A side effect queued by an operation that then raised ran anyway.',
        );
    }

    /**
     * Forty rows, one transaction: a side effect queued by the first row must
     * wait for the last. The second row raises here, so nothing at all
     * happened — including the first row's mail.
     */
    #[DataProvider('connections')]
    public function testASideEffectWaitsForEveryRowOfABulkAction(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $first = $this->insert($connection, 'Horské kolo');
        $second = $this->insert($connection, 'Silniční kolo');

        try {
            $this->bulk($connection, 'archive', [$first, $second], 'archive_failing_second');

            self::fail('A bulk action whose second row raised was answered as a success.');
        } catch (RuntimeException) {
            // The response is a fault, which is the right answer to logic
            // that did not expect its own input. What matters is below.
        }

        foreach ($this->rows($connection) as $row) {
            self::assertSame(
                'active',
                $this->text($row['state']),
                'A row written before a later row raised was not rolled back.',
            );
        }

        self::assertSame([], $this->audit($connection), 'A bulk action that rolled back was audited.');
        self::assertSame(
            ['wrote:' . $first, 'wrote:' . $second],
            $this->log,
            "The first row's side effect ran while the transaction was still open.",
        );
    }

    /** The same bulk action without the failure: two rows, two audit rows, two side effects. */
    #[DataProvider('connections')]
    public function testABulkActionRunsEveryRowsSideEffectOnceItHasCommitted(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $first = $this->insert($connection, 'Horské kolo');
        $second = $this->insert($connection, 'Silniční kolo');

        $this->bulk($connection, 'archive', [$first, $second]);

        foreach ($this->rows($connection) as $row) {
            self::assertSame('archived', $this->text($row['state']));
        }

        self::assertCount(2, $this->audit($connection));
        self::assertSame(
            ['wrote:' . $first, 'wrote:' . $second, 'mail:' . $first, 'mail:' . $second],
            $this->log,
            'The side effects did not all wait for the commit.',
        );
    }

    /**
     * A business rule is not a crash. The submission comes back on its own
     * form, with the operation's sentence above it and what was typed still
     * in the controls.
     */
    #[DataProvider('connections')]
    public function testARefusalComesBackOnTheFormWithItsOwnSentence(?Connection $connection): void
    {
        $connection = $this->fresh($connection);

        $response = $this->save($connection, ['title' => 'Horské kolo', 'state' => 'active'], 'save_refusing');

        self::assertSame(422, $response->status, 'A refused submission was not answered as a refusal.');
        self::assertStringContainsString('This ad is already sold', $response->body);
        self::assertStringContainsString('Horské kolo', $response->body, 'The submission was thrown away.');
        self::assertSame([], $this->rows($connection), 'A refused operation wrote a row.');
        self::assertSame([], $this->audit($connection));
    }

    /** A single action's refusal has no form to go back to, so it is a flash. */
    #[DataProvider('connections')]
    public function testARefusedActionSaysSoOnThePageItCameFrom(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $key = $this->insert($connection, 'Horské kolo');

        $response = $this->action($connection, 'archive', $key, 'archive_refusing');

        self::assertSame(302, $response->status);
        self::assertStringContainsString('This ad is already sold', $this->flashes());
        self::assertSame('active', $this->text($this->rows($connection)[0]['state']));
    }

    /**
     * Null means "nothing worth recording", and it has to mean exactly that:
     * no audit row claiming a change, and a message saying so rather than one
     * claiming a save.
     */
    #[DataProvider('connections')]
    public function testAnOperationThatWritesNothingRecordsNothingAndSaysSo(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $key = $this->insert($connection, 'Horské kolo');

        $response = $this->action($connection, 'archive', $key, 'archive_doing_nothing');

        self::assertSame(302, $response->status);
        self::assertSame([], $this->audit($connection), 'An operation that wrote nothing was audited.');
        self::assertSame('active', $this->text($this->rows($connection)[0]['state']));
        self::assertStringContainsString('nothing needed changing', $this->flashes());
    }

    /**
     * What an operation is told. The `when` conditions have already been
     * re-checked by the time it runs, so the row it is handed is the row they
     * were checked against — and `$values` is what the action named, not what
     * a browser sent.
     */
    #[DataProvider('connections')]
    public function testAnOperationIsToldWhichWriteItIsServingAndWhatTheRowWas(?Connection $connection): void
    {
        $connection = $this->fresh($connection);
        $key = $this->insert($connection, 'Horské kolo');

        $this->action($connection, 'archive', $key, 'archive_reporting');

        self::assertSame(
            ["archive {$key} state=active"],
            $this->log,
            'An operation was told the wrong operation, key or before-value.',
        );
    }

    /**
     * The timing that is the whole reason `Operations` exists: a page naming
     * logic nobody registered is refused when the registry is built, naming
     * the page and where it is written — not when somebody presses the button.
     */
    public function testAPageNamingLogicNobodyRegisteredIsRefusedWhenTheRegistryIsBuilt(): void
    {
        try {
            Operations::none()->assertEveryHandlerExists($this->repository());

            self::fail('A page naming logic nothing registered was accepted.');
        } catch (PageException $e) {
            self::assertStringContainsString("Page 'ops'", $e->getMessage());
            self::assertStringContainsString('@handler:', $e->getMessage());
            self::assertStringContainsString('nothing registered', $e->getMessage());
        }
    }

    /**
     * A form save, which is `create` because no key is posted.
     *
     * @param array<string, mixed> $body
     */
    private function save(Connection $connection, array $body, string $handler = 'save_reviewing'): Response
    {
        return $this->post($connection, 'create', $body, ['save_ad' => $this->operation($handler)]);
    }

    /** One row, one action. */
    private function action(
        Connection $connection,
        string $verb,
        string $key,
        string $handler = 'archive_archiving',
    ): Response {
        return $this->post(
            $connection,
            $verb,
            [FormFields::ID => $key],
            ['archive_ad' => $this->operation($handler)],
        );
    }

    /**
     * The same action over a selection.
     *
     * @param list<string> $keys
     */
    private function bulk(
        Connection $connection,
        string $verb,
        array $keys,
        string $handler = 'archive_archiving',
    ): Response {
        return $this->post(
            $connection,
            $verb,
            [FormFields::BULK => '1', FormFields::IDS => $keys],
            ['archive_ad' => $this->operation($handler)],
        );
    }

    /**
     * @param array<string, mixed>     $body
     * @param array<string, Operation> $operations
     */
    private function post(Connection $connection, string $verb, array $body, array $operations): Response
    {
        return $this->handler($connection, $operations)->handle(
            new Route('action', ['page' => 'ops', 'action' => $verb]),
            new Request(
                'POST',
                "a/ops/{$verb}",
                body: [Csrf::FIELD => (new Csrf($this->session))->token()] + $body,
            ),
        );
    }

    /** @param array<string, Operation> $operations */
    private function handler(Connection $connection, array $operations): ActionHandler
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
            new SqlAuditLog($connection),
            Access::identity()->user(),
            Operations::of($operations),
        );
    }

    /**
     * The project's logic, one per thing this test needs to prove.
     *
     * Written as methods rather than closures so each one carries the
     * signature `Operation` requires -- which is the thing a project has to
     * keep, and a test that did not keep it would be proving the seam works
     * for code the seam does not accept.
     *
     * They are deliberately small and deliberately real: each writes through
     * the `WriteHandler` it is handed, because an operation that writes any
     * other way is an operation with no audit row.
     */
    private function operation(string $which): Operation
    {
        return $this->operationOf(match ($which) {
            'save_reviewing' => $this->saveReviewing(...),
            'save_default' => $this->saveDefault(...),
            'save_then_fail' => $this->saveThenFail(...),
            'save_refusing' => $this->refuseWith('This ad is already sold, so it cannot be saved again.'),
            'archive_archiving' => $this->archive(...),
            'archive_failing_second' => $this->archiveFailingSecond(...),
            'archive_refusing' => $this->refuseWith('This ad is already sold, so it cannot be archived.'),
            'archive_doing_nothing' => $this->doNothing(...),
            'archive_reporting' => $this->report(...),
            default => self::fail("No such test operation '{$which}'."),
        });
    }

    /**
     * Writes something of its own choosing, over what was submitted, and
     * sends mail once the row is real.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $values
     */
    private function saveReviewing(
        OperationContext $context,
        array $row,
        array $values,
        WriteHandler $writes,
    ): ?WriteResult {
        $write = $context->writeDefault($writes, [...$values, 'state' => 'reviewed']);

        $this->log[] = 'wrote';
        $context->afterCommit(function (): void {
            $this->log[] = 'mail';
        });

        return $write;
    }

    /**
     * The ordinary write and nothing else, which is what `writeDefault()` is
     * for.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $values
     */
    private function saveDefault(
        OperationContext $context,
        array $row,
        array $values,
        WriteHandler $writes,
    ): ?WriteResult {
        return $context->writeDefault($writes, $values);
    }

    /**
     * Writes, queues a side effect, and then raises. None of the three may
     * survive.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $values
     */
    private function saveThenFail(
        OperationContext $context,
        array $row,
        array $values,
        WriteHandler $writes,
    ): never {
        $context->writeDefault($writes, $values);

        $this->log[] = 'wrote';
        $context->afterCommit(function (): void {
            $this->log[] = 'mail';
        });

        throw new RuntimeException('The project decided this cannot work.');
    }

    /**
     * An operation that says no, in words.
     *
     * @return Closure(OperationContext, array<string, mixed>, array<string, mixed>, WriteHandler): never
     */
    private function refuseWith(string $because): Closure
    {
        return static function (
            OperationContext $context,
            array $row,
            array $values,
            WriteHandler $writes,
        ) use ($because): never {
            throw new OperationRefused($because);
        };
    }

    /**
     * What the page's `archive` action would have done, plus the mail a
     * project would actually want.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $values
     */
    private function archive(
        OperationContext $context,
        array $row,
        array $values,
        WriteHandler $writes,
    ): WriteResult {
        $write = $writes->update(
            $context->page->entity,
            (string) $context->key,
            ['state' => 'archived'],
            $context->scope,
        );

        $this->log[] = 'wrote:' . $context->key;
        $context->afterCommit(function () use ($context): void {
            $this->log[] = 'mail:' . $context->key;
        });

        return $write;
    }

    /**
     * The same, until the second row of a selection — which is where the
     * ordering guarantee is actually load-bearing.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $values
     */
    private function archiveFailingSecond(
        OperationContext $context,
        array $row,
        array $values,
        WriteHandler $writes,
    ): WriteResult {
        $write = $this->archive($context, $row, $values, $writes);

        if (\count($this->log) >= 2) {
            throw new RuntimeException('The second row broke.');
        }

        return $write;
    }

    /**
     * Nothing needed doing, and it says so rather than inventing a change for
     * the log to claim.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $values
     */
    private function doNothing(
        OperationContext $context,
        array $row,
        array $values,
        WriteHandler $writes,
    ): null {
        return null;
    }

    /**
     * Reports what it was told, which is the other half of the contract: the
     * operation it is serving, the row it is about, and that row as it was.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $values
     */
    private function report(
        OperationContext $context,
        array $row,
        array $values,
        WriteHandler $writes,
    ): null {
        $this->log[] = \sprintf(
            '%s %s state=%s',
            $context->operation,
            (string) $context->key,
            \is_scalar($row['state'] ?? null) ? (string) $row['state'] : '',
        );

        return null;
    }

    /** @param Closure(OperationContext, array<string, mixed>, array<string, mixed>, WriteHandler): ?WriteResult $work */
    private function operationOf(Closure $work): Operation
    {
        return new class ($work) implements Operation {
            /** @param Closure(OperationContext, array<string, mixed>, array<string, mixed>, WriteHandler): ?WriteResult $work */
            public function __construct(private readonly Closure $work)
            {
            }

            public function perform(
                OperationContext $context,
                array $row,
                array $values,
                WriteHandler $writes,
            ): ?WriteResult {
                return ($this->work)($context, $row, $values, $writes);
            }
        };
    }

    private function insert(Connection $connection, string $title): string
    {
        $connection->execute(new Sql(
            'INSERT INTO ra_test_ops (title, state) VALUES (?, ?)',
            [$title, 'active'],
        ));

        $rows = $connection->select(new Sql('SELECT id FROM ra_test_ops ORDER BY id DESC'));

        return $this->text($rows[0]['id']);
    }

    /** Everything flashed, as one string to look for a sentence in. */
    private function flashes(): string
    {
        $messages = [];

        foreach ((new FlashBag($this->session))->take() as $flash) {
            $messages[] = $flash->message;
        }

        return implode("\n", $messages);
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
        return $connection->select(new Sql('SELECT * FROM ra_test_ops ORDER BY id'));
    }

    /** @return list<array<string, mixed>> */
    private function audit(Connection $connection): array
    {
        return $connection->select(new Sql('SELECT * FROM ra_activity_log ORDER BY id'));
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

        $key = $connection->dialect()->name() === 'mysql'
            ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : 'BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY';

        $connection->execute(new Sql(
            "CREATE TABLE ra_test_ops (id {$key}, title VARCHAR(100) NOT NULL, state VARCHAR(20) NOT NULL)",
        ));

        return $connection;
    }

    /**
     * A page whose form and whose action both name the project's own logic.
     *
     * The action carries a `when` and no `set`: the handler decides what is
     * written, and a file carrying both would be refused by `PageRepository`
     * because only one of them could run.
     */
    private function writePage(): void
    {
        file_put_contents($this->root . '/pages/ops.php', <<<'PHP'
            <?php

            return [
                'title' => 'Ad',
                'entity' => ['table' => 'ra_test_ops'],
                'actions' => [
                    'archive' => [
                        'type' => 'post',
                        'to' => '@action:archive',
                        'label' => 'Archive',
                        'confirm' => 'Archive this ad?',
                        'permission' => 'ops.update',
                        'when' => ['state' => 'active'],
                        'handler' => '@handler:archive_ad',
                    ],
                ],
                'bulk_actions' => ['archive'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['title' => [], 'state' => []]],
                    'form' => [
                        'type' => 'form',
                        'form' => [
                            'handler' => '@handler:save_ad',
                            'fields' => [
                                'title' => ['type' => 'text', 'required' => true],
                                'state' => ['type' => 'text'],
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
