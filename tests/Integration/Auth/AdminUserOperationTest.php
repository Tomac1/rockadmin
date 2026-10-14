<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Auth\AdminUserOperation;
use RockAdmin\Auth\AdminUsers;
use RockAdmin\Auth\Argon2idHasher;
use RockAdmin\Auth\PasswordResets;
use RockAdmin\Auth\Roles;
use RockAdmin\Config\Enums;
use RockAdmin\Data\Operations;
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
use RockAdmin\Mail\Mailbox;
use RockAdmin\Mail\Mailer;
use RockAdmin\Mail\Message;
use RockAdmin\Mail\MessageFactory;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\Access;
use RockAdmin\Tests\Support\DatabaseTestCase;
use RockAdmin\View\Assets;
use RockAdmin\View\Escaper;
use RockAdmin\View\FlashBag;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * Administrators managed from inside the admin, driven through the route that
 * manages them.
 *
 * The page is ordinary configuration over `ra_users`; this is the part a page
 * file cannot say, and every test here is one of the four things a plain form
 * save would get wrong:
 *
 * - an edit with the password box left empty must leave the stored hash alone,
 *   because the form cannot show what is there and so cannot ask to keep it;
 * - `roles` and `workspaces` are JSON columns and a multiselect is a list;
 * - the last account that can reach everything cannot be removed, by any of
 *   the three ways of removing it;
 * - an account created without a password is invited, after the commit.
 *
 * It is also the SDK using milestone 11's seam on its own table, which is the
 * best evidence available that the seam is wide enough to be worth having.
 */
#[CoversClass(AdminUserOperation::class)]
#[CoversClass(AdminUsers::class)]
final class AdminUserOperationTest extends DatabaseTestCase
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

    /** @var list<Message> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = new ArraySessionStore();
        $this->sent = [];
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-admins-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);
        $this->writePage();

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
    public function testCreatingAnAdministratorHashesThePasswordAndStoresTheListsAsJson(
        ?Connection $connection,
    ): void {
        $connection = $this->requireConnection($connection);

        $response = $this->post($connection, 'create', [
            'email' => 'Petr@Example.com',
            'name' => 'Petr',
            'password' => 'a-perfectly-good-password',
            'roles' => ['viewer'],
            'workspaces' => ['cyklobazar'],
            'is_active' => '1',
        ]);

        self::assertSame(302, $response->status, $response->body);

        $row = $this->rowFor($connection, 'petr@example.com');

        self::assertSame('Petr', $this->text($row['name']));
        self::assertNotSame('a-perfectly-good-password', $this->text($row['password']));
        self::assertTrue((new Argon2idHasher())->verify('a-perfectly-good-password', $this->text($row['password'])));
        self::assertSame(['viewer'], json_decode($this->text($row['roles']), true));
        self::assertSame(['cyklobazar'], json_decode($this->text($row['workspaces']), true));
    }

    /** The one that matters most, because the failure is silent and permanent. */
    #[DataProvider('connections')]
    public function testEditingWithTheBoxEmptyLeavesTheStoredPasswordAlone(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $id = $this->existing($connection, 'petr@example.com', 'the-original-password', ['viewer']);
        $before = $this->text($this->rowFor($connection, 'petr@example.com')['password']);

        $this->post($connection, 'update', [
            FormFields::ID => $id,
            'email' => 'petr@example.com',
            'name' => 'Petr Novak',
            'password' => '',
            'roles' => ['viewer'],
            'is_active' => '1',
        ]);

        $after = $this->rowFor($connection, 'petr@example.com');

        self::assertSame('Petr Novak', $this->text($after['name']), 'The edit itself was lost.');
        self::assertSame($before, $this->text($after['password']), "An empty box cleared somebody's password.");
        self::assertTrue((new Argon2idHasher())->verify('the-original-password', $this->text($after['password'])));
    }

    #[DataProvider('connections')]
    public function testEditingWithANewPasswordReplacesIt(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $id = $this->existing($connection, 'petr@example.com', 'the-original-password', ['viewer']);

        $this->post($connection, 'update', [
            FormFields::ID => $id,
            'email' => 'petr@example.com',
            'name' => 'Petr',
            'password' => 'a-replacement-password',
            'roles' => ['viewer'],
            'is_active' => '1',
        ]);

        $stored = $this->text($this->rowFor($connection, 'petr@example.com')['password']);

        self::assertTrue((new Argon2idHasher())->verify('a-replacement-password', $stored));
    }

    // --- the building, and locking oneself out of it ---

    #[DataProvider('connections')]
    public function testTheLastSuperadminCannotBeDeactivated(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $id = $this->existing($connection, 'jana@example.com', 'a-password-here', ['superadmin']);

        $response = $this->post($connection, 'update', [
            FormFields::ID => $id,
            'email' => 'jana@example.com',
            'name' => 'Jana',
            'password' => '',
            'roles' => ['superadmin'],
            // No is_active key at all, which is what an unticked box sends.
        ]);

        self::assertSame(422, $response->status, 'Deactivating the last superadmin was allowed.');
        self::assertStringContainsString('last active account', $response->body);
        self::assertTrue($this->isActive($connection, 'jana@example.com'));
    }

    #[DataProvider('connections')]
    public function testTheLastSuperadminCannotBeDemoted(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $id = $this->existing($connection, 'jana@example.com', 'a-password-here', ['superadmin']);

        $response = $this->post($connection, 'update', [
            FormFields::ID => $id,
            'email' => 'jana@example.com',
            'name' => 'Jana',
            'password' => '',
            'roles' => ['viewer'],
            'is_active' => '1',
        ]);

        self::assertSame(422, $response->status, 'The last superadmin demoted themselves.');
        self::assertSame(
            ['superadmin'],
            json_decode($this->text($this->rowFor($connection, 'jana@example.com')['roles']), true),
        );
    }

    #[DataProvider('connections')]
    public function testTheLastSuperadminCannotBeDeleted(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $id = $this->existing($connection, 'jana@example.com', 'a-password-here', ['superadmin']);

        $response = $this->post($connection, 'delete', [FormFields::ID => $id]);

        self::assertSame(302, $response->status);
        self::assertStringContainsString('last active account', $this->flashes());
        self::assertSame(1, $this->countFor($connection, 'jana@example.com'), 'The row was deleted anyway.');
    }

    #[DataProvider('connections')]
    public function testWithASecondSuperadminTheFirstMayGo(?Connection $connection): void
    {
        // The other half, and it matters as much: a rule that refuses
        // everything is not a safeguard, it is a broken page.
        $connection = $this->requireConnection($connection);
        $id = $this->existing($connection, 'jana@example.com', 'a-password-here', ['superadmin']);
        $this->existing($connection, 'petr@example.com', 'a-password-here', ['superadmin']);

        $response = $this->post($connection, 'update', [
            FormFields::ID => $id,
            'email' => 'jana@example.com',
            'name' => 'Jana',
            'password' => '',
            'roles' => ['viewer'],
            'is_active' => '1',
        ]);

        self::assertSame(302, $response->status, $response->body);
        self::assertSame(
            ['viewer'],
            json_decode($this->text($this->rowFor($connection, 'jana@example.com')['roles']), true),
        );
    }

    #[DataProvider('connections')]
    public function testADeactivatedSuperadminDoesNotCountAsCover(?Connection $connection): void
    {
        // Somebody who cannot sign in cannot undo anything, so an account that
        // is deactivated is not the second superadmin the check is looking for.
        $connection = $this->requireConnection($connection);
        $id = $this->existing($connection, 'jana@example.com', 'a-password-here', ['superadmin']);
        $other = $this->existing($connection, 'petr@example.com', 'a-password-here', ['superadmin']);
        $connection->execute(new Sql('UPDATE ra_users SET is_active = ? WHERE id = ?', [false, $other]));

        $response = $this->post($connection, 'delete', [FormFields::ID => $id]);

        self::assertStringContainsString('last active account', $this->flashes());
        self::assertSame(1, $this->countFor($connection, 'jana@example.com'), 'The row was deleted anyway.');
    }

    #[DataProvider('connections')]
    public function testSomebodyCannotDeleteTheAccountTheyAreSignedInWith(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->existing($connection, 'jana@example.com', 'a-password-here', ['superadmin']);
        $actorId = $this->existing($connection, 'actor@example.com', 'a-password-here', ['superadmin']);

        $response = $this->post($connection, 'delete', [FormFields::ID => $actorId], $actorId);

        self::assertSame(302, $response->status);
        self::assertStringContainsString('signed in with', $this->flashes());
        self::assertSame(1, $this->countFor($connection, 'actor@example.com'), 'The row was deleted anyway.');
    }

    // --- invitations ---

    #[DataProvider('connections')]
    public function testAnAccountCreatedWithNoPasswordIsInvited(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $response = $this->post($connection, 'create', [
            'email' => 'petr@example.com',
            'name' => 'Petr',
            'password' => '',
            'roles' => ['viewer'],
            'is_active' => '1',
        ]);

        self::assertSame(302, $response->status, $response->body);

        $row = $this->rowFor($connection, 'petr@example.com');

        self::assertNull($row['password'], 'An invited account was given a password nobody chose.');
        self::assertCount(1, $this->sent, 'Nobody was invited.');
        self::assertSame('invitation', $this->sent[0]->kind);
        self::assertSame('petr@example.com', $this->sent[0]->to->address);
        self::assertMatchesRegularExpression('#/password/reset/[a-f0-9]{64}#', $this->sent[0]->text);
        self::assertStringContainsString('2 days', $this->sent[0]->text, 'An invitation expires like a reset.');
    }

    #[DataProvider('connections')]
    public function testAnAccountCreatedWithAPasswordIsNotInvited(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->post($connection, 'create', [
            'email' => 'petr@example.com',
            'name' => 'Petr',
            'password' => 'a-perfectly-good-password',
            'roles' => ['viewer'],
            'is_active' => '1',
        ]);

        self::assertSame([], $this->sent, 'Somebody who was given a password was also sent a link.');
    }

    #[DataProvider('connections')]
    public function testTheInviteActionSendsAgainAndRecordsNothing(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $id = $this->existing($connection, 'petr@example.com', null, ['viewer']);

        $response = $this->post($connection, 'invite', [FormFields::ID => $id]);

        self::assertSame(302, $response->status);
        self::assertCount(1, $this->sent);
        self::assertSame('petr@example.com', $this->sent[0]->to->address);
        self::assertSame(
            [],
            $connection->select(new Sql('SELECT id FROM ra_activity_log')),
            'An invitation that changed no row was recorded as a change.',
        );
    }

    #[DataProvider('connections')]
    public function testAFailedWriteSendsNoInvitation(?Connection $connection): void
    {
        // The whole reason the mail is queued for after the commit: a
        // duplicate address is refused by the unique index, and nobody should
        // be told about an account that does not exist.
        $connection = $this->requireConnection($connection);
        $this->existing($connection, 'petr@example.com', 'a-password-here', ['viewer']);

        $response = $this->post($connection, 'create', [
            'email' => 'petr@example.com',
            'name' => 'Petr Again',
            'password' => '',
            'roles' => ['viewer'],
            'is_active' => '1',
        ]);

        self::assertSame(422, $response->status, 'A duplicate address was accepted.');
        self::assertSame([], $this->sent, 'A write that failed still sent an invitation.');
    }

    // --- harness ---

    /** @param array<string, mixed> $body */
    private function post(Connection $connection, string $verb, array $body, string $actorId = '1'): Response
    {
        return $this->actions($connection, $actorId)->handle(
            new Route('action', ['page' => 'administrators', 'action' => $verb]),
            new Request(
                'POST',
                "a/administrators/{$verb}",
                body: [Csrf::FIELD => (new Csrf($this->session))->token()] + $body,
            ),
        );
    }

    private function actions(Connection $connection, string $actorId): ActionHandler
    {
        $urls = new UrlGenerator('/admin');
        $csrf = new Csrf($this->session);
        $flashes = new FlashBag($this->session);
        $reader = new RowReader(new SqlRowSource($connection));
        $renderer = new Renderer(new TemplateResolver([]), new Escaper(), $urls);
        $region = new FormRegion($reader, $urls, $csrf, Access::gateFor($this->repository()), Access::identity());

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
            new \RockAdmin\Auth\SqlAuditLog($connection),
            new \RockAdmin\Auth\AuthUser($actorId, 'actor@example.com', 'Actor', ['superadmin']),
            Operations::of(['ra_admin_user' => $this->operation($connection, $renderer, $urls)]),
        );
    }

    private function operation(Connection $connection, Renderer $renderer, UrlGenerator $urls): AdminUserOperation
    {
        return new AdminUserOperation(
            new AdminUsers($connection),
            Roles::fromConfig([
                'viewer' => ['permissions' => ['*.view']],
                'superadmin' => ['label' => 'Superadmin', 'permissions' => ['*']],
            ], ['administrators.view', 'administrators.update', 'administrators.delete']),
            new PasswordResets($connection, 2880),
            new MessageFactory($renderer, new Mailbox('admin@example.com', 'Admin'), 'RockAdmin'),
            $this->mailer(),
            $urls,
            'https://admin.example.com',
            2880,
        );
    }

    private function mailer(): Mailer
    {
        $test = $this;

        return new class ($test) implements Mailer {
            public function __construct(private readonly AdminUserOperationTest $test)
            {
            }

            public function send(Message $message): void
            {
                $this->test->received($message);
            }
        };
    }

    public function received(Message $message): void
    {
        $this->sent[] = $message;
    }

    /** @param list<string> $roles */
    private function existing(Connection $connection, string $email, ?string $password, array $roles): string
    {
        return (new AdminUsers($connection))->create($email, 'Somebody', $password, $roles, ['cyklobazar']);
    }

    /** @return array<string, mixed> */
    private function rowFor(Connection $connection, string $email): array
    {
        $rows = $connection->select(new Sql('SELECT * FROM ra_users WHERE email = ?', [$email]));

        self::assertCount(1, $rows, "No row for {$email}.");

        return $rows[0];
    }

    private function countFor(Connection $connection, string $email): int
    {
        $value = $connection->scalar(new Sql('SELECT COUNT(*) FROM ra_users WHERE email = ?', [$email]));

        return \is_scalar($value) ? (int) $value : 0;
    }

    private function isActive(Connection $connection, string $email): bool
    {
        $value = $this->rowFor($connection, $email)['is_active'];

        return \is_bool($value) ? $value : \in_array($value, [1, '1', 't', 'true'], true);
    }

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

    private function text(mixed $value): string
    {
        if ($value === null || \is_scalar($value)) {
            return (string) $value;
        }

        $this->fail('Expected a scalar, got ' . get_debug_type($value));
    }

    /**
     * The administrators page, as a project would write it.
     *
     * It is the scaffold the guide carries, trimmed to what these assertions
     * need: the same entity, the same handler on the form and on the action,
     * and the same six fields.
     */
    private function writePage(): void
    {
        file_put_contents($this->root . '/pages/administrators.php', <<<'PHP'
            <?php

            return [
                'title' => 'Administrators',
                'entity' => ['table' => 'ra_users'],
                'actions' => [
                    'invite' => [
                        'type' => 'post',
                        'to' => '@action:invite',
                        'label' => 'Send the invitation again',
                        'confirm' => 'Send a new link to this administrator?',
                        'permission' => 'administrators.update',
                        'handler' => '@handler:ra_admin_user',
                    ],
                ],
                'regions' => [
                    'grid' => [
                        'type' => 'list',
                        'columns' => ['email' => [], 'name' => [], 'is_active' => []],
                    ],
                    'form' => [
                        'type' => 'form',
                        'form' => [
                            'handler' => '@handler:ra_admin_user',
                            'fields' => [
                                'email' => ['type' => 'text', 'required' => true],
                                'name' => ['type' => 'text', 'required' => true],
                                'password' => ['type' => 'password'],
                                'roles' => [
                                    'type' => 'multiselect',
                                    'options' => ['superadmin' => 'Superadmin', 'viewer' => 'Viewer'],
                                ],
                                'workspaces' => [
                                    'type' => 'multiselect',
                                    'options' => ['cyklobazar' => 'Cyklobazar'],
                                ],
                                'is_active' => ['type' => 'checkbox'],
                                'created_at' => ['type' => 'hidden', 'hidden' => true, 'default' => '@now'],
                            ],
                        ],
                    ],
                ],
            ];
            PHP);
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
