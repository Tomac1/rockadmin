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
 * demo-only machinery. One route serves assets, one route renders a page,
 * and everything else 404s, because there is nothing else to look at until
 * milestone 6 adds a grid.
 */

require __DIR__ . '/../vendor/autoload.php';

use RockAdmin\Config\Config;
use RockAdmin\Config\Loader;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\ErrorHandler;
use RockAdmin\Http\Handler;
use RockAdmin\Http\HandlerRegistry;
use RockAdmin\Http\Kernel;
use RockAdmin\Http\Request;
use RockAdmin\Http\Response;
use RockAdmin\Http\Route;
use RockAdmin\Http\Router;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\View\AssetHandler;
use RockAdmin\View\Assets;
use RockAdmin\View\ButtonView;
use RockAdmin\View\Escaper;
use RockAdmin\View\FlashBag;
use RockAdmin\View\MenuItemView;
use RockAdmin\View\PageView;
use RockAdmin\View\Renderer;
use RockAdmin\View\ShellView;
use RockAdmin\View\TemplateErrorPage;
use RockAdmin\View\TemplateResolver;

$config = (new Loader(__DIR__ . '/config', static function (string $name): ?string {
    $value = getenv($name);

    return $value === false ? null : $value;
}))->load();

// The schema guarantees these keys are strings once the config has loaded,
// but Config::get() returns mixed for every path alike; this is the one
// place that narrows it back down, honestly, rather than casting past it.
$configString = static function (Config $config, string $path, string $default): string {
    $value = $config->get($path, $default);

    return is_string($value) ? $value : $default;
};

$brand = $configString($config, 'brand', 'RockAdmin');
$darkMode = $configString($config, 'theme.dark', 'auto');
$debugValue = $config->get('debug', false);
$debug = is_bool($debugValue) ? $debugValue : false;

$session = new ArraySessionStore();
$urls = new UrlGenerator('/', $configString($config, 'url_mode', 'path'));
$renderer = new Renderer(new TemplateResolver([]), new Escaper(), $urls);
$assets = new Assets($urls);
$flashes = new FlashBag($session);

$handlers = new HandlerRegistry();
$handlers->register('assets', new AssetHandler());
$handlers->register('dashboard', new class ($renderer, $urls, $assets, $flashes, $brand, $darkMode) implements Handler {
    public function __construct(
        private readonly Renderer $renderer,
        private readonly UrlGenerator $urls,
        private readonly Assets $assets,
        private readonly FlashBag $flashes,
        private readonly string $brand,
        private readonly string $darkMode,
    ) {
    }

    public function handle(Route $route, Request $request): Response
    {
        $this->flashes->success('This is the shell milestone 4 built — regions arrive in milestone 6.');

        $shell = new ShellView(
            $this->brand,
            menu: [
                new MenuItemView('Dashboard', $this->urls->route('dashboard'), active: true),
                new MenuItemView('Ads', $this->urls->to('p/ads')),
            ],
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
            . '<p class="ra-placeholder">There is no grid yet — regions and the templates that '
            . 'render them arrive in milestone 6. This is exactly what this milestone built.</p>';

        $single = $this->renderer->render('layout/single', new PageView('dashboard', 'Dashboard', $shell, slots: ['main' => $main]));

        $html = $this->renderer->render('layout/base', new PageView('dashboard', 'Dashboard', $shell, slots: ['main' => $single]));

        return Response::html($html);
    }
});

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
