<?php

declare(strict_types=1);

namespace RockAdmin;

use Closure;
use PDO;
use PDOException;
use RockAdmin\Auth\AdminUserOperation;
use RockAdmin\Auth\AdminUsers;
use RockAdmin\Auth\Authenticator;
use RockAdmin\Auth\Gate;
use RockAdmin\Auth\Identity;
use RockAdmin\Auth\PasswordResets;
use RockAdmin\Auth\Permission;
use RockAdmin\Auth\RefusingAuthenticator;
use RockAdmin\Auth\RequestScope;
use RockAdmin\Auth\Roles;
use RockAdmin\Auth\RowWorkspace;
use RockAdmin\Auth\SessionWorkspace;
use RockAdmin\Auth\SqlAuditLog;
use RockAdmin\Auth\SqlAuthenticator;
use RockAdmin\Auth\Throttle;
use RockAdmin\Auth\Workspaces;
use RockAdmin\Config\Config;
use RockAdmin\Config\Loader;
use RockAdmin\Data\Operation;
use RockAdmin\Data\Operations;
use RockAdmin\Db\Connection;
use RockAdmin\Db\RowReader;
use RockAdmin\Db\SqlRowSource;
use RockAdmin\Db\SqlWriteHandler;
use RockAdmin\Form\FieldValidator;
use RockAdmin\Form\FileWrites;
use RockAdmin\Form\FormRegion;
use RockAdmin\Form\UploadLimits;
use RockAdmin\Grid\ActionBuilder;
use RockAdmin\Grid\CellFormatter;
use RockAdmin\Grid\ListRegion;
use RockAdmin\Grid\PreviewRegion;
use RockAdmin\Grid\QueryFactory;
use RockAdmin\Http\ActionHandler;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\DashboardHandler;
use RockAdmin\Http\DetailHandler;
use RockAdmin\Http\ErrorHandler;
use RockAdmin\Http\FileHandler;
use RockAdmin\Http\FormHandler;
use RockAdmin\Http\Handler;
use RockAdmin\Http\HandlerRegistry;
use RockAdmin\Http\Kernel;
use RockAdmin\Http\LoginHandler;
use RockAdmin\Http\LogoutHandler;
use RockAdmin\Http\NativeSessionStore;
use RockAdmin\Http\PageHandler;
use RockAdmin\Http\PasswordResetHandler;
use RockAdmin\Http\RegionHandler;
use RockAdmin\Http\Request;
use RockAdmin\Http\Response;
use RockAdmin\Http\Route;
use RockAdmin\Http\Router;
use RockAdmin\Http\SetupHandler;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Http\WorkspaceHandler;
use RockAdmin\Mail\Mailbox;
use RockAdmin\Mail\Mailers;
use RockAdmin\Mail\MailException;
use RockAdmin\Mail\MessageFactory;
use RockAdmin\Page\PageRepository;
use RockAdmin\Storage\FileLink;
use RockAdmin\Storage\StorageException;
use RockAdmin\Storage\Storages;
use RockAdmin\Storage\UnconfiguredStorage;
use RockAdmin\View\AssetHandler;
use RockAdmin\View\FlashBag;
use RockAdmin\View\Menu;
use RockAdmin\View\MenuItems;
use RockAdmin\View\ShellAccount;
use RockAdmin\View\ShellWorkspaces;
use RockAdmin\View\TemplateErrorPage;
use RockAdmin\View\ViewFactory;

/**
 * The composition root: everything this SDK is, assembled in one place.
 *
 * It is the only class in the project allowed to reach every layer, because
 * composing them is what it is for — rule 4 governs code that does work, and
 * this does none. Everything here was, until it existed, 800 lines inside
 * `demo/index.php`: which meant that the only way to integrate RockAdmin was
 * to copy a demo and own it forever, and that every handler added to this SDK
 * afterwards was a line each project had to be told to write.
 *
 * **One admin belongs to one request.** `boot()` reads the configuration,
 * opens the database and builds the handlers; `handle()` answers with what
 * was built. Booting twice in a process is supported and is what the tests
 * do — but an `Admin` must not be *kept* across requests, because the
 * session, the flash bag, the CSRF token and the signed-in user all belong
 * to the request it was built in. Where something must survive that anyway,
 * it is passed as a question rather than an answer: the audit log is given a
 * closure that asks who is writing, not the user who was signed in when the
 * handlers were wired.
 *
 * Nothing here touches a superglobal — `RockAdmin::handle()` does that, and
 * is short enough to read in one go.
 *
 * **An installation with no database still answers.** Every route that needs
 * rows is registered only when there is a connection, and the kernel's 404
 * explains the absence on the admin's own themed error page. That is not a
 * courtesy to the demo: a deployment whose credentials are not set yet is a
 * real state, and a fatal error before the first byte of HTML tells whoever
 * is looking at it nothing.
 */
final class Admin
{
    /** @param list<string> $warnings things worth saying that are not worth refusing over */
    private function __construct(
        private readonly Kernel $kernel,
        private readonly HandlerRegistry $handlers,
        public readonly Services $services,
        public readonly array $warnings,
    ) {
    }

    public static function boot(Setup $setup): self
    {
        $env = $setup->env ?? self::environment(...);

        $loader = new Loader($setup->configDirectory(), $env, $setup->basePath);
        $config = $loader->load();
        $warnings = $loader->warnings();

        $session = $setup->session ?? new NativeSessionStore();
        $urlMode = self::str($config, 'url_mode', 'path');
        $urls = new UrlGenerator($setup->baseUrl, $urlMode);

        $views = new ViewFactory($config, $urls);
        $renderer = $views->renderer();
        $assets = $views->assets();
        $brand = $views->brand();
        $darkMode = $views->darkMode();
        $flashes = new FlashBag($session);

        $connection = self::connect($setup, $config);

        $pages = new PageRepository(
            $setup->configDirectory() . '/' . self::str($config, 'pages_path', 'pages'),
            $env,
            $config->enums(),
            self::int($config, 'per_page', 25),
            $connection,
        );

        // Built eagerly, before a single request is handled, because building
        // it is also what validates roles.php: a role naming a page that does
        // not exist stops the boot with an explanation rather than granting
        // nothing in silence.
        $gate = Gate::fromConfig($config, $pages);
        $roles = Roles::fromConfig($config->roles(), Permission::catalogue($pages));

        $authenticator = $setup->authenticator ?? self::authenticator($connection);
        $identity = new Identity($session, $authenticator);
        $csrf = new Csrf($session);

        // Nothing configured is the single-tenant case, and it is meant to
        // cost nothing: an empty set is scoped against nothing and checked
        // for nobody.
        $workspaces = Workspaces::fromConfig($config->workspaces(), $pages);
        $activeWorkspace = new SessionWorkspace($session, $workspaces);

        $fileLink = new FileLink($session, self::int($config, 'storage.link_minutes', 10) * 60);
        $storage = $setup->storage;

        if ($storage === null) {
            try {
                $storage = Storages::fromConfig($config, $urls, null, $fileLink);
            } catch (StorageException $e) {
                // An admin that uploads nothing must still start. The refusal
                // is kept word for word and moved to the first upload, where
                // somebody is actually asking for the feature that is not set
                // up -- and `doctor` says it before they get that far.
                $storage = new UnconfiguredStorage($e->getMessage());
                $warnings[] = 'Uploads are not configured: ' . $e->getMessage();
            }
        }

        $mailer = Mailers::fromConfig($config, $setup->mailCallback);
        $publicUrl = self::str($config, 'public_url', '');

        $services = new Services(
            $config,
            $pages,
            $urls,
            $renderer,
            $assets,
            $flashes,
            $session,
            $csrf,
            $identity,
            $gate,
            $workspaces,
            $storage,
            $mailer,
            $brand,
            $darkMode,
            $publicUrl,
            $setup->baseUrl,
            $urlMode,
            $connection,
        );

        $menu = new Menu(
            $setup->menu ?? MenuItems::fromConfig(self::menu($config), $urls),
            $gate,
            $identity,
            $pages,
        );
        $account = new ShellAccount($identity, $urls, $csrf);

        $handlers = new HandlerRegistry();
        $handlers->register('assets', new AssetHandler());
        $handlers->register('file', new FileHandler($storage, $fileLink));
        $handlers->register('dashboard', new DashboardHandler(
            $renderer,
            $assets,
            $flashes,
            $menu,
            $account,
            $brand,
            $darkMode,
        ));

        $login = new LoginHandler(
            $authenticator,
            $identity,
            $csrf,
            $renderer,
            $assets,
            $urls,
            $brand,
            $setup->clientIp ?? self::clientIp(),
            $darkMode,
            $flashes,
        );

        $handlers->register('login.form', $login);
        $handlers->register('login.submit', $login);
        $handlers->register('logout', new LogoutHandler($identity, $csrf, $urls));

        if ($connection !== null) {
            $warnings = [...$warnings, ...self::registerDatabaseRoutes(
                $handlers,
                $setup,
                $services,
                $connection,
                $menu,
                $account,
                $activeWorkspace,
                $roles,
                $fileLink,
            )];
        }

        // The host's own handlers go on last, so that registering one against
        // a name the SDK already used replaces it. That is how a project
        // keeps its own dashboard, or serves `page.index` from somewhere this
        // SDK has never heard of, without a fork and without a template
        // override standing in for a decision it did not want to make.
        if ($setup->handlers !== null) {
            /** @var array<string, Handler> $own */
            $own = ($setup->handlers)($services);

            foreach ($own as $name => $handler) {
                $handlers->register($name, $handler);
            }
        }

        $kernel = new Kernel(
            new Router(),
            $handlers,
            new ErrorHandler(
                debug: self::bool($config, 'debug', false),
                page: new TemplateErrorPage($renderer),
            ),
            $identity,
            $gate,
            $urls,
            $workspaces,
            $activeWorkspace,
        );

        return new self($kernel, $handlers, $services, array_values($warnings));
    }

    public function handle(Request $request): Response
    {
        return $this->kernel->handle($request);
    }

    /**
     * Whether something is registered for a route.
     *
     * It exists for `doctor` and for the test that walks `Router::routeNames()`
     * — the check nobody could make while the registry was assembled inside a
     * demo, and the reason a route added to the table with no handler behind
     * it used to be found by opening it in a browser.
     */
    public function handles(string $routeName): bool
    {
        return $this->handlers->find($routeName) !== null;
    }

    /**
     * Everything that needs rows.
     *
     * @return list<string> what it could not wire, in words a person can act on
     */
    private static function registerDatabaseRoutes(
        HandlerRegistry $handlers,
        Setup $setup,
        Services $services,
        Connection $connection,
        Menu $menu,
        ShellAccount $account,
        SessionWorkspace $activeWorkspace,
        Roles $roles,
        FileLink $fileLink,
    ): array {
        $warnings = [];
        $config = $services->config;
        $pages = $services->pages;
        $urls = $services->urls;
        $renderer = $services->renderer;
        $assets = $services->assets;
        $flashes = $services->flashes;
        $csrf = $services->csrf;
        $identity = $services->identity;
        $gate = $services->gate;
        $storage = $services->storage;
        $brand = $services->brand;
        $darkMode = $services->darkMode;

        $resetLifetime = self::int($config, 'auth.reset_lifetime_minutes', 60);
        $inviteLifetime = self::int($config, 'auth.invite_lifetime_minutes', 2880);
        $clientIp = $setup->clientIp ?? self::clientIp();

        $from = self::mailbox($config);

        if ($from === null) {
            // Not a refusal: an admin whose administrators all know their
            // passwords works perfectly without mail, and failing to boot
            // over it would be this SDK deciding how somebody's installation
            // is allowed to be incomplete. `doctor` says it out loud.
            $warnings[] = 'mail.from.address is empty, so no mail can be sent: the forgotten-password '
                . 'flow and invitations to new administrators are switched off until it is set.';
        } else {
            $messages = new MessageFactory($renderer, $from, $brand);

            $passwords = new PasswordResetHandler(
                new AdminUsers($connection),
                new PasswordResets($connection, $resetLifetime),
                new Throttle(
                    $connection,
                    self::int($config, 'auth.attempts_per_email', 10),
                    self::int($config, 'auth.attempts_per_ip', 20),
                    self::int($config, 'auth.window_minutes', 15) * 60,
                ),
                $identity,
                $services->mailer,
                $messages,
                $csrf,
                $renderer,
                $assets,
                $flashes,
                $urls,
                $brand,
                $clientIp,
                $services->publicUrl,
                $resetLifetime,
                $darkMode,
            );

            foreach ([
                'password.request.form',
                'password.request.submit',
                'password.reset.form',
                'password.reset.submit',
            ] as $route) {
                $handlers->register($route, $passwords);
            }
        }

        // How a freshly migrated installation gets its first administrator
        // without writing an Argon2id hash into the database by hand. It 404s
        // the moment one exists, so it is reachable for about a minute in the
        // life of an installation.
        $setupHandler = new SetupHandler(
            new AdminUsers($connection),
            $identity,
            new SqlAuthenticator($connection),
            $roles,
            $services->workspaces,
            $csrf,
            $renderer,
            $assets,
            $urls,
            $brand,
            $darkMode,
        );

        $handlers->register('setup.form', $setupHandler);
        $handlers->register('setup.submit', $setupHandler);

        $rows = new SqlRowSource($connection);
        $reader = new RowReader($rows);

        // One object answers "what is this request's scope" for the grid, the
        // preview, the form and every write. Four of them working it out
        // separately is four chances to differ, and the one that differs is a
        // hole rather than a bug.
        $scope = new RequestScope($activeWorkspace, $identity, $services->workspaces);

        $listRegion = new ListRegion(
            $rows,
            new QueryFactory(scope: $scope),
            new CellFormatter($storage),
            $urls,
            $gate,
            $identity,
            $csrf,
            $scope,
        );
        $previewRegion = new PreviewRegion($rows, new CellFormatter($storage), $scope);
        $actionBuilder = new ActionBuilder($gate, $identity, $urls, $csrf);
        $rowWorkspace = new RowWorkspace($scope, $reader);

        $handlers->register('workspace.switch', new WorkspaceHandler(
            $services->workspaces,
            $activeWorkspace,
            $identity,
            $csrf,
            $urls,
        ));

        $handlers->register('page.index', new PageHandler(
            $pages,
            $listRegion,
            $renderer,
            $assets,
            $flashes,
            $brand,
            $darkMode,
            $menu,
            $account,
            $actionBuilder,
            new ShellWorkspaces($services->workspaces, $activeWorkspace, $identity, $urls),
        ));
        $handlers->register('page.detail', new DetailHandler(
            $pages,
            $previewRegion,
            $renderer,
            $assets,
            $flashes,
            $brand,
            $darkMode,
            $menu,
            $account,
        ));
        $handlers->register('region', new RegionHandler($pages, $listRegion, $previewRegion, $renderer));

        $formRegion = new FormRegion($reader, $urls, $csrf, $gate, $identity, $scope, $storage);
        $forms = new FormHandler(
            $pages,
            $formRegion,
            $renderer,
            $assets,
            $flashes,
            $urls,
            $brand,
            $darkMode,
            $menu,
            $account,
            $rowWorkspace,
        );

        $handlers->register('page.create', $forms);
        $handlers->register('page.edit', $forms);
        $handlers->register('page.copy', $forms);

        $operations = self::operations($setup, $services, $connection, $roles, $inviteLifetime);
        $operations->assertEveryHandlerExists($pages);

        $handlers->register('action', new ActionHandler(
            $pages,
            $formRegion,
            new FieldValidator(UploadLimits::fromConfig($config)),
            new SqlWriteHandler($connection),
            $reader,
            $scope,
            $connection,
            $forms,
            $csrf,
            $flashes,
            $urls,
            self::bool($config, 'audit.enabled', true) ? new SqlAuditLog($connection) : null,
            $identity->user(...),
            $operations,
            $rowWorkspace,
            null,
            new FileWrites($storage),
        ));

        return $warnings;
    }

    /**
     * The project's write logic, and the SDK's own.
     *
     * `ra_admin_user` is registered here rather than left to the host because
     * the administrators page is this SDK's page: hashing a password, the two
     * JSON columns, the refusal that keeps an installation reachable and the
     * invitation mail are all things it owns. A host that names it in its own
     * pages gets it for free; a host that replaces it passes one of its own
     * under the same name, because its own operations are merged last.
     */
    private static function operations(
        Setup $setup,
        Services $services,
        Connection $connection,
        Roles $roles,
        int $inviteLifetime,
    ): Operations {
        $from = self::mailbox($services->config);

        /** @var array<string, Operation> $operations */
        $operations = [];

        if ($from !== null) {
            $operations['ra_admin_user'] = new AdminUserOperation(
                new AdminUsers($connection),
                $roles,
                new PasswordResets($connection, $inviteLifetime),
                new MessageFactory($services->renderer, $from, $services->brand),
                $services->mailer,
                $services->urls,
                $services->publicUrl,
                $inviteLifetime,
            );
        }

        if ($setup->operations !== null) {
            /** @var array<string, Operation> $own */
            $own = ($setup->operations)($services);
            $operations = [...$operations, ...$own];
        }

        return Operations::of($operations);
    }

    private static function authenticator(?Connection $connection): Authenticator
    {
        return $connection !== null ? new SqlAuthenticator($connection) : new RefusingAuthenticator();
    }

    /**
     * The connection, borrowed from the host or opened from `database.*`.
     *
     * A closure wins, because borrowing is the better answer wherever there is
     * something to borrow. A DSN that cannot be reached is a warning and not a
     * fatal error, for the same reason the no-database case exists at all: the
     * admin says what is wrong on a page somebody can read, rather than in a
     * stack trace before the first byte.
     */
    private static function connect(Setup $setup, Config $config): ?Connection
    {
        if ($setup->pdo !== null) {
            $pdo = ($setup->pdo)();

            // A closure may answer "no database" the same way the
            // configuration does -- by saying so -- rather than by throwing
            // at a point where nothing can be rendered yet.
            return $pdo !== null ? Connection::fromPdo($pdo) : null;
        }

        $dsn = self::str($config, 'database.dsn', '');

        if ($dsn === '') {
            return null;
        }

        try {
            return Connection::fromPdo(new PDO(
                $dsn,
                self::str($config, 'database.user', ''),
                self::str($config, 'database.password', ''),
            ));
        } catch (PDOException) {
            return null;
        }
    }

    /** Who the mail is from, or nothing if `mail.from.address` was never set. */
    private static function mailbox(Config $config): ?Mailbox
    {
        try {
            return Mailers::from($config);
        } catch (MailException) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private static function menu(Config $config): array
    {
        $menu = $config->get('menu', []);

        /** @var array<string, mixed> $menu the schema guarantees an array; this narrows it */
        return \is_array($menu) ? $menu : [];
    }

    private static function environment(string $name): ?string
    {
        $value = getenv($name);

        return $value === false ? null : $value;
    }

    /**
     * The caller's address, trusting nothing.
     *
     * A forwarded header is a value whoever sent the request chose, and the
     * throttle counts per address — so a proxy's header is only usable by a
     * host that knows it has a proxy, which is why `Setup` takes one. The
     * literal is for the command line, where there is no peer and an empty
     * string would pool every caller into one bucket.
     */
    private static function clientIp(): string
    {
        $address = $_SERVER['REMOTE_ADDR'] ?? null;

        return \is_string($address) && $address !== '' ? $address : '0.0.0.0';
    }

    private static function str(Config $config, string $path, string $default): string
    {
        $value = $config->get($path, $default);

        return \is_string($value) ? $value : $default;
    }

    private static function int(Config $config, string $path, int $default): int
    {
        $value = $config->get($path, $default);

        return \is_int($value) ? $value : $default;
    }

    private static function bool(Config $config, string $path, bool $default): bool
    {
        $value = $config->get($path, $default);

        return \is_bool($value) ? $value : $default;
    }
}
