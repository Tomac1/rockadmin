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

use RockAdmin\Config\Config;
use RockAdmin\Config\Loader;
use RockAdmin\Db\Connection;
use RockAdmin\Db\SqlRowSource;
use RockAdmin\Grid\CellFormatter;
use RockAdmin\Grid\ListRegion;
use RockAdmin\Grid\PreviewRegion;
use RockAdmin\Grid\QueryFactory;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\DetailHandler;
use RockAdmin\Http\ErrorHandler;
use RockAdmin\Http\Handler;
use RockAdmin\Http\HandlerRegistry;
use RockAdmin\Http\Kernel;
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
use RockAdmin\View\MenuItemView;
use RockAdmin\View\PageView;
use RockAdmin\View\Renderer;
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

$session = new ArraySessionStore();
$urls = new UrlGenerator('/', $configString($config, 'url_mode', 'path'));

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

/** @return list<MenuItemView> */
$menu = static fn (): array => [
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

$handlers = new HandlerRegistry();
$handlers->register('assets', new AssetHandler());
$handlers->register('dashboard', new class ($renderer, $urls, $assets, $flashes, $brand, $darkMode, $menu) implements Handler {
    /** @param Closure(): list<MenuItemView> $menu */
    public function __construct(
        private readonly Renderer $renderer,
        private readonly UrlGenerator $urls,
        private readonly Assets $assets,
        private readonly FlashBag $flashes,
        private readonly string $brand,
        private readonly string $darkMode,
        private readonly Closure $menu,
    ) {
    }

    public function handle(Route $route, Request $request): Response
    {
        $this->flashes->success('This is the shell milestone 4 built — the ads page shows the grid milestone 6 built.');

        $items = ($this->menu)();
        $items[0] = new MenuItemView($items[0]->label, $items[0]->url, active: true, key: $items[0]->key);

        $shell = new ShellView(
            $this->brand,
            menu: $items,
            flashes: $this->flashes->take(),
            styles: $this->assets->styles(),
            scripts: $this->assets->scripts(),
            darkMode: $this->darkMode,
        );

        $header = new PageView(
            'dashboard',
            'Dashboard',
            $shell,
            description: 'What this milestone built: the shell, the menu, a page header with '
                . 'buttons, and a flash message shown once as a toast.',
            buttons: [
                new ButtonView('create', 'New ad', $this->urls->to('p/ads/create'), style: 'primary'),
                new ButtonView('export', 'Export', $this->urls->to('p/ads/export')),
            ],
        );

        $main = $this->renderer->render('page/header', $header)
            . '<p class="ra-placeholder">See the grid this milestone built at '
            . '<a href="' . htmlspecialchars($this->urls->to('p/ads'), ENT_QUOTES) . '">Ads</a>.</p>';

        $single = $this->renderer->render('layout/single', new PageView('dashboard', 'Dashboard', $shell, slots: ['main' => $main]));

        $html = $this->renderer->render('layout/base', new PageView('dashboard', 'Dashboard', $shell, slots: ['main' => $single]));

        return Response::html($html);
    }
});

if ($connection !== null) {
    $rows = new SqlRowSource($connection);
    $listRegion = new ListRegion($rows, new QueryFactory(), new CellFormatter(), $urls);
    $previewRegion = new PreviewRegion($rows, new CellFormatter());

    $handlers->register('page.index', new PageHandler(
        $pages,
        $listRegion,
        $renderer,
        $assets,
        $flashes,
        $brand,
        $darkMode,
        ($menu)(),
    ));
    $handlers->register('page.detail', new DetailHandler(
        $pages,
        $previewRegion,
        $renderer,
        $assets,
        $flashes,
        $brand,
        $darkMode,
        ($menu)(),
    ));
    $handlers->register('region', new RegionHandler($pages, $listRegion, $previewRegion, $renderer));
} else {
    // No database configured: still render the shell and the page header for
    // every real page, so cloning the repository to look at the theme works
    // with nothing set up. Only the grid itself is replaced by a message.
    $handlers->register('page.index', new class ($pages, $renderer, $assets, $flashes, $brand, $darkMode, $menu) implements Handler {
        /** @param Closure(): list<MenuItemView> $menu */
        public function __construct(
            private readonly PageRepository $pages,
            private readonly Renderer $renderer,
            private readonly Assets $assets,
            private readonly FlashBag $flashes,
            private readonly string $brand,
            private readonly string $darkMode,
            private readonly Closure $menu,
        ) {
        }

        public function handle(Route $route, Request $request): Response
        {
            $name = $route->param('page');

            if (!$this->pages->has($name)) {
                throw new NotFoundException("Page '{$name}' not found.");
            }

            $page = $this->pages->get($name);

            $items = array_map(
                fn (MenuItemView $item): MenuItemView => new MenuItemView(
                    $item->label,
                    $item->url,
                    active: $item->key === $name,
                    key: $item->key,
                ),
                ($this->menu)(),
            );

            $shell = new ShellView(
                $this->brand,
                menu: $items,
                flashes: $this->flashes->take(),
                styles: $this->assets->styles(),
                scripts: $this->assets->scripts(),
                darkMode: $this->darkMode,
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
    $handlers->register('page.detail', new class () implements Handler {
        public function handle(Route $route, Request $request): Response
        {
            throw new NotFoundException('No database is configured for this demo.');
        }
    });
}

$errors = new ErrorHandler(
    debug: $debug,
    page: new TemplateErrorPage($renderer),
);

$kernel = new Kernel(new Router(), $handlers, $errors);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestUri = is_string($requestUri) ? $requestUri : '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH);
$path = rawurldecode(is_string($requestPath) ? $requestPath : '/');

$kernel->handle(Request::fromGlobals($path))->send();
