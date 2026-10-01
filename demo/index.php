<?php

declare(strict_types=1);

/**
 * The demo application: the design surface the default theme is tuned
 * against, kept honest to the general case that a real project's own
 * templates and configuration will always be bigger than. Run it with:
 *
 *     php -S localhost:8080 -t demo demo/index.php
 *
 * It wires the same public classes a project wires — nothing here is
 * demo-only machinery. One route serves assets, one route renders the
 * dashboard, and `p/ads` renders a real grid over the fixture tables
 * `demo/seed.php` fills — the same tables `tests/Support/DatabaseTestCase.php`
 * builds for the test suite.
 *
 * The grid needs a database; the shell does not. Cloning this repository and
 * running the demo with no database configured still shows a themed page —
 * the `ads` page renders its header and says plainly that no database is
 * configured, rather than throwing a connection error at whoever only came to
 * look at the theme.
 */

require __DIR__ . '/../vendor/autoload.php';

use RockAdmin\Auth\Authenticator;
use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\Gate;
use RockAdmin\Auth\Identity;
use RockAdmin\Auth\LoginResult;
use RockAdmin\Auth\RequestScope;
use RockAdmin\Auth\SessionWorkspace;
use RockAdmin\Auth\SqlAuditLog;
use RockAdmin\Auth\SqlAuthenticator;
use RockAdmin\Auth\Workspaces;
use RockAdmin\Config\Config;
use RockAdmin\Config\Loader;
use RockAdmin\Db\Connection;
use RockAdmin\Db\RowReader;
use RockAdmin\Db\SqlRowSource;
use RockAdmin\Db\SqlWriteHandler;
use RockAdmin\Form\FieldValidator;
use RockAdmin\Form\FormRegion;
use RockAdmin\Grid\ActionBuilder;
use RockAdmin\Grid\CellFormatter;
use RockAdmin\Grid\ListRegion;
use RockAdmin\Grid\PreviewRegion;
use RockAdmin\Grid\QueryFactory;
use RockAdmin\Http\ActionHandler;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\DetailHandler;
use RockAdmin\Http\ErrorHandler;
use RockAdmin\Http\FormHandler;
use RockAdmin\Http\Handler;
use RockAdmin\Http\HandlerRegistry;
use RockAdmin\Http\Kernel;
use RockAdmin\Http\LoginHandler;
use RockAdmin\Http\LogoutHandler;
use RockAdmin\Http\NativeSessionStore;
use RockAdmin\Http\NotFoundException;
use RockAdmin\Http\PageHandler;
use RockAdmin\Http\RegionHandler;
use RockAdmin\Http\Request;
use RockAdmin\Http\Response;
use RockAdmin\Http\Route;
use RockAdmin\Http\Router;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\PageRepository;
use RockAdmin\View\AssetHandler;
use RockAdmin\View\Assets;
use RockAdmin\View\ButtonView;
use RockAdmin\View\FlashBag;
use RockAdmin\View\Menu;
use RockAdmin\View\MenuItemView;
use RockAdmin\View\PageView;
use RockAdmin\View\Renderer;
use RockAdmin\View\ShellAccount;
use RockAdmin\View\ShellView;
use RockAdmin\View\TemplateErrorPage;
use RockAdmin\View\ViewFactory;

$env = static function (string $name): ?string {
    $value = getenv($name);

    return $value === false ? null : $value;
};

$config = (new Loader(__DIR__ . '/config', $env))->load();

// The schema guarantees this key is a string once the config has loaded, but
// Config::get() returns mixed for every path alike; this is the one place
// that narrows it back down, honestly, rather than casting past it.
$configString = static function (Config $config, string $path, string $default): string {
    $value = $config->get($path, $default);

    return is_string($value) ? $value : $default;
};

$debugValue = $config->get('debug', false);
$debug = is_bool($debugValue) ? $debugValue : false;

// A native session, not an ArraySessionStore: a CSRF token has to survive
// from the request that draws a form to the request that posts it, and a
// flash message has to survive a redirect. An in-memory store forgets both
// between requests, which would make every save a 403 and every toast
// invisible -- the two failures this milestone's browser check exists to
// catch. ArraySessionStore is for tests and the CLI, where there is no
// session to keep.
$session = new NativeSessionStore();
$urlMode = $configString($config, 'url_mode', 'path');
$urls = new UrlGenerator('/', $urlMode);

// ViewFactory is the proof that template_paths, assets.css, assets.js and
// theme.dark — all declared in RootSchema, none of them read anywhere before
// this class existed — are actually reachable from configuration. Hand-wiring
// a TemplateResolver and an Assets here, the way this file used to, is
// exactly the trap: it works, and it proves nothing about the setting a real
// project writes into rockadmin.php.
$views = new ViewFactory($config, $urls);
$renderer = $views->renderer();
$assets = $views->assets();
$brand = $views->brand();
$darkMode = $views->darkMode();
$flashes = new FlashBag($session);

// The navigation as declared. What each reader actually sees is `Menu`'s
// answer, built below once the gate and the identity exist: a page somebody
// may not view is not drawn, because a visible link that answers 403 is a bug
// rather than a safeguard (spec 9.2).
/** @var list<MenuItemView> $menuItems */
$menuItems = [
    new MenuItemView('Dashboard', $urls->route('dashboard'), key: 'dashboard'),
    new MenuItemView('Ads', $urls->to('p/ads'), key: 'ads'),
];

$pagesPath = $configString($config, 'pages_path', 'pages');
$perPageValue = $config->get('per_page', 25);
$perPage = is_int($perPageValue) ? $perPageValue : 25;

$pages = new PageRepository(__DIR__ . '/config/' . $pagesPath, $env, $config->enums(), $perPage);

/**
 * The demo connects to whichever fixture database the environment names —
 * the same `RA_TEST_MYSQL_*` / `RA_TEST_PGSQL_*` variables the test suite
 * reads, and the same ones `demo/seed.php` fills. Neither RootSchema nor
 * PageSchema declares a database connection: how a project reaches its
 * database is host wiring, same as SessionStore or Mailer, never something
 * rockadmin.php itself configures.
 */
function demoConnection(Closure $env): ?Connection
{
    foreach (['MYSQL', 'PGSQL'] as $prefix) {
        $dsn = $env("RA_TEST_{$prefix}_DSN");

        if (!is_string($dsn) || $dsn === '') {
            continue;
        }

        $user = $env("RA_TEST_{$prefix}_USER");
        $password = $env("RA_TEST_{$prefix}_PASSWORD");

        try {
            $pdo = new PDO($dsn, is_string($user) ? $user : null, is_string($password) ? $password : null);

            return Connection::fromPdo($pdo);
        } catch (PDOException) {
            // A configured-but-unreachable database is exactly the case the
            // page-level fallback exists for: say so on the page, never a
            // fatal error before a single byte of HTML is sent.
            continue;
        }
    }

    return null;
}

$connection = demoConnection($env);

/**
 * Steps 3, 4 and 7 of the lifecycle.
 *
 * `Gate::fromConfig()` is what actually reads `demo/config/roles.php`: the
 * loader stores that file raw, because deciding which permissions exist needs
 * the pages and the loader has no page repository. Building the gate here —
 * eagerly, before a single request is handled — is therefore also what
 * validates the file, so a role naming a page that does not exist stops this
 * script with an explanation rather than granting nothing in silence.
 *
 * Without a database there is nobody to authenticate against, so the demo
 * supplies an authenticator that refuses everything. Every page then redirects
 * to the login form, which is the honest answer: an admin with no user store
 * has no users. The assets route stays open, so the theme still serves.
 */
$authenticator = $connection !== null
    ? new SqlAuthenticator($connection)
    : new class () implements Authenticator {
        public function attempt(string $email, string $password, string $ip): LoginResult
        {
            return LoginResult::refused();
        }

        public function byId(string $id): ?AuthUser
        {
            return null;
        }

        public function logout(AuthUser $user): void
        {
        }
    };

$identity = new Identity($session, $authenticator);
$gate = Gate::fromConfig($config, $pages);

// Workspaces (spec 9.3). The demo declares none, so `Workspaces::fromConfig()`
// returns the empty set and nothing is scoped -- which is the single-tenant
// case and is meant to cost nothing. The wiring is here rather than absent so
// that adding `workspaces.php` is the only change a second tenant needs.
$workspaces = Workspaces::fromConfig($config->workspaces(), $pages);
$activeWorkspace = new SessionWorkspace($session, $workspaces);

// One `Csrf` for the whole request, wired before the handlers because three of
// them need it: the login form, the logout button in the navbar, and the write
// path. It used to be built inside the `if ($connection !== null)` block below,
// where only `ActionHandler` could reach it -- which is exactly how the navbar
// came to draw a Log out button with no token in it.
$csrf = new Csrf($session);

/**
 * Whether the audit log is on.
 *
 * `audit.enabled` is a configuration key (spec 9.4: the log "can be disabled,
 * but defaults to on"), not a decision this file makes by passing null. The
 * difference matters because the reference documentation is generated from
 * RootSchema: an off switch that exists only as a line of PHP in each host's
 * composition root is an off switch nobody can find.
 */
$auditValue = $config->get('audit.enabled', true);
$auditEnabled = is_bool($auditValue) ? $auditValue : true;

/**
 * The person the audit log names, resolved once for this request.
 *
 * It is the authenticated user and never a column of the row being written —
 * a log that read `ra_test_ads.user_id` would record an administrator's edit
 * of somebody else's ad as that somebody else's own, and an audit trail that
 * exonerates the wrong person is worse than none because it is believed.
 */
$actor = $identity->user();

/**
 * The navbar's account area, for every handler that draws a shell.
 *
 * One object rather than three strings, and passed to all five of them rather
 * than to the dashboard alone. Wiring it only where this file builds a
 * `ShellView` by hand -- which is what it did first -- put the Log out button
 * on the dashboard and nowhere else: `PageHandler`, `DetailHandler` and
 * `FormHandler` build their own shells, so somebody who navigated to `/p/ads`
 * had no way to sign out without going back to the dashboard first. Found by
 * opening the page, which is the only way it was going to be found -- every
 * test in the suite passes a shell it constructed itself.
 */
$account = new ShellAccount($identity, $urls, $csrf);

// Wired before the handlers, not after them, because rendering asks the gate
// too: a region draws a create button, an edit link or a delete button only
// for somebody the gate would let press it, and the menu draws only the pages
// they may view. One `Gate::allows()`, two callers -- the kernel at step 7 and
// the templates at step 10.
$menu = new Menu($menuItems, $gate, $identity, $pages);

$handlers = new HandlerRegistry();
$handlers->register('assets', new AssetHandler());

/**
 * Signing in and out.
 *
 * `login.form`, `login.submit` and `logout` have been declared in
 * `Router::ROUTES` since milestone 1 with nothing registered against them.
 * This is where they become reachable, and until it existed every page in the
 * demo redirected to a login form that answered 404.
 *
 * The client's address is the host's to decide, which is why `LoginHandler`
 * takes it as an argument rather than reading it off the request: only the host
 * knows whether it sits behind a proxy whose forwarded header it trusts. This
 * demo trusts nothing and uses `REMOTE_ADDR`, and falls back to a literal for
 * the command line, where there is no peer — the throttle counts per address,
 * so it needs a string rather than an empty one that would pool every caller.
 */
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;
$login = new LoginHandler(
    $authenticator,
    $identity,
    $csrf,
    $renderer,
    $assets,
    $urls,
    $brand,
    is_string($remoteAddress) && $remoteAddress !== '' ? $remoteAddress : '0.0.0.0',
    $darkMode,
);

$handlers->register('login.form', $login);
$handlers->register('login.submit', $login);
$handlers->register('logout', new LogoutHandler($identity, $csrf, $urls));

$handlers->register(
    'dashboard',
    new class (
        $renderer,
        $urls,
        $assets,
        $flashes,
        $brand,
        $darkMode,
        $menu,
        $gate,
        $identity,
        $account,
    ) implements Handler {
        public function __construct(
            private readonly Renderer $renderer,
            private readonly UrlGenerator $urls,
            private readonly Assets $assets,
            private readonly FlashBag $flashes,
            private readonly string $brand,
            private readonly string $darkMode,
            private readonly Menu $menu,
            private readonly Gate $gate,
            private readonly Identity $identity,
            private readonly ShellAccount $account,
        ) {
        }

        public function handle(Route $route, Request $request): Response
        {
            $this->flashes->success('This is the shell milestone 4 built — the ads page shows the grid milestone 6 built.');

            $shell = new ShellView(
                $this->brand,
                menu: $this->menu->forPage('dashboard'),
                flashes: $this->flashes->take(),
                styles: $this->assets->styles(),
                scripts: $this->assets->scripts(),
                userName: $this->account->userName(),
                logoutUrl: $this->account->logoutUrl(),
                darkMode: $this->darkMode,
                // Never the URL without the token: the button would answer 403.
                csrfToken: $this->account->csrfToken(),
            );

            $header = new PageView(
                'dashboard',
                'Dashboard',
                $shell,
                description: 'What this milestone built: the shell, the menu, a page header with '
                    . 'buttons, and a flash message shown once as a toast.',
                // A header button is a control like any other, so the gate
                // decides it too: 'New ad' goes to `p/ads/create`, which the
                // kernel refuses without `ads.create`, and a button that answers
                // 403 is a bug rather than a safeguard (spec 9.2). 'Export' is
                // milestone 4's placeholder and names no route at all, so it is
                // shown to whoever may read the page it belongs to.
                buttons: $this->gate->allows($this->identity->user(), 'ads.create')
                    ? [
                        new ButtonView('create', 'New ad', $this->urls->to('p/ads/create'), style: 'primary'),
                        new ButtonView('export', 'Export', $this->urls->to('p/ads/export')),
                    ]
                    : [new ButtonView('export', 'Export', $this->urls->to('p/ads/export'))],
            );

            $main = $this->renderer->render('page/header', $header)
                . '<p class="ra-placeholder">See the grid this milestone built at '
                . '<a href="' . htmlspecialchars($this->urls->to('p/ads'), ENT_QUOTES) . '">Ads</a>.</p>';

            $single = $this->renderer->render('layout/single', new PageView('dashboard', 'Dashboard', $shell, slots: ['main' => $main]));

            $html = $this->renderer->render('layout/base', new PageView('dashboard', 'Dashboard', $shell, slots: ['main' => $single]));

            return Response::html($html);
        }
    },
);

if ($connection !== null) {
    $rows = new SqlRowSource($connection);
    $listRegion = new ListRegion(
        $rows,
        new QueryFactory(),
        new CellFormatter(),
        $urls,
        $gate,
        $identity,
        $csrf,
    );
    $previewRegion = new PreviewRegion($rows, new CellFormatter());

    // The page header's own configured controls (spec 8.5). The same three
    // decisions a grid's action cell makes -- the gate, the URL from the
    // reference, the token a POST carries -- so it is the same builder.
    $actionBuilder = new ActionBuilder($gate, $identity, $urls, $csrf);

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

    // The write path. `FormHandler` draws the form for all three of
    // `page.create`, `page.edit` and `page.copy`; `ActionHandler` is the only
    // thing in the admin that changes a row, and every one of the three verbs
    // it accepts arrives as a POST to `a/{page}/{action}` carrying the token
    // the form put in its body. The two share a `FormRegion` so that a
    // refused submission is redrawn by exactly the code that drew it first.
    $reader = new RowReader($rows);
    $formRegion = new FormRegion($reader, $urls, $csrf, $gate, $identity);
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
    );

    $handlers->register('page.create', $forms);
    $handlers->register('page.edit', $forms);
    $handlers->register('page.copy', $forms);
    $handlers->register('action', new ActionHandler(
        $pages,
        $formRegion,
        new FieldValidator(),
        new SqlWriteHandler($connection),
        $reader,
        // The demo declares no workspaces, so this answers [] for every page
        // -- but it is wired rather than left null, because the day the demo
        // grows a second workspace the wiring should already be right.
        new RequestScope($activeWorkspace, $identity),
        $connection,
        $forms,
        $csrf,
        $flashes,
        $urls,
        // Spec 9.4's "defaults to on", actually on. Until this argument was
        // passed, `ActionHandler` held a null `AuditLog` and every save in this
        // demo recorded nothing, while an integration test asserted that the
        // log worked -- which it did, in the test's own wiring. Turning it off
        // is `audit.enabled => false` in rockadmin.php, not a change here.
        $auditEnabled ? new SqlAuditLog($connection) : null,
        $actor,
    ));
} else {
    // No database configured: still render the shell and the page header for
    // every real page, so cloning the repository to look at the theme works
    // with nothing set up. Only the grid itself is replaced by a message.
    $handlers->register('page.index', new class (
        $pages,
        $renderer,
        $assets,
        $flashes,
        $brand,
        $darkMode,
        $menu,
        $account,
    ) implements Handler {
        public function __construct(
            private readonly PageRepository $pages,
            private readonly Renderer $renderer,
            private readonly Assets $assets,
            private readonly FlashBag $flashes,
            private readonly string $brand,
            private readonly string $darkMode,
            private readonly Menu $menu,
            private readonly ShellAccount $account,
        ) {
        }

        public function handle(Route $route, Request $request): Response
        {
            $name = $route->param('page');

            if (!$this->pages->has($name)) {
                throw new NotFoundException("Page '{$name}' not found.");
            }

            $page = $this->pages->get($name);

            $shell = new ShellView(
                $this->brand,
                menu: $this->menu->forPage($name),
                flashes: $this->flashes->take(),
                styles: $this->assets->styles(),
                scripts: $this->assets->scripts(),
                userName: $this->account->userName(),
                logoutUrl: $this->account->logoutUrl(),
                darkMode: $this->darkMode,
                csrfToken: $this->account->csrfToken(),
            );

            $header = new PageView($page->name, $page->title, $shell, description: $page->description);

            $main = $this->renderer->render('page/header', $header)
                . '<p class="ra-placeholder">No database is configured for this demo, so the grid this page '
                . 'would otherwise show cannot run. Set <code>RA_TEST_MYSQL_DSN</code> (or '
                . '<code>RA_TEST_PGSQL_DSN</code>) and run <code>php demo/seed.php</code>, then reload.</p>';

            $single = $this->renderer->render(
                'layout/' . $page->layout,
                new PageView($page->name, $page->title, $shell, slots: ['main' => $main]),
            );

            $html = $this->renderer->render(
                'layout/base',
                new PageView($page->name, $page->title, $shell, slots: ['main' => $single]),
            );

            return Response::html($html);
        }
    });
    $handlers->register('region', new class () implements Handler {
        public function handle(Route $route, Request $request): Response
        {
            throw new NotFoundException('No database is configured for this demo.');
        }
    });
    // The form routes and the action route need the same database the grid
    // does, so without one they answer the same way the detail route already
    // does: plainly, rather than with a fatal error from a null connection.
    $noDatabase = new class () implements Handler {
        public function handle(Route $route, Request $request): Response
        {
            throw new NotFoundException('No database is configured for this demo.');
        }
    };

    $handlers->register('page.detail', $noDatabase);
    $handlers->register('page.create', $noDatabase);
    $handlers->register('page.edit', $noDatabase);
    $handlers->register('page.copy', $noDatabase);
    $handlers->register('action', $noDatabase);
}

$errors = new ErrorHandler(
    debug: $debug,
    page: new TemplateErrorPage($renderer),
);

$kernel = new Kernel(
    new Router(),
    $handlers,
    $errors,
    $identity,
    $gate,
    $urls,
);

// In 'path' mode the route lives in the URL's own path, e.g. /p/ads. In
// 'query' mode UrlGenerator instead writes it into a query parameter --
// /index.php?ra=p%2Fads -- so the host has to read the route from there
// instead: Request itself takes no view on how a path was derived (see its
// own docblock), which is exactly why this is the demo's job, not the
// SDK's. Reading $_GET directly rather than $urls->route() is deliberate:
// UrlGenerator builds outgoing links, this is decoding the one incoming
// one, and 'ra' here is UrlGenerator's own default query key.
if ($urlMode === UrlGenerator::MODE_QUERY) {
    $route = $_GET['ra'] ?? '';
    $path = is_string($route) ? $route : '';
} else {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $requestUri = is_string($requestUri) ? $requestUri : '/';
    $requestPath = parse_url($requestUri, PHP_URL_PATH);
    $path = rawurldecode(is_string($requestPath) ? $requestPath : '/');
}

$kernel->handle(Request::fromGlobals($path))->send();
