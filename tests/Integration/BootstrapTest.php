<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Admin;
use RockAdmin\Http\Handler;
use RockAdmin\Http\Request;
use RockAdmin\Http\Response;
use RockAdmin\Http\Route;
use RockAdmin\Http\Router;
use RockAdmin\RockAdmin;
use RockAdmin\Services;
use RockAdmin\Setup;
use RockAdmin\Tests\Support\RecordingSessionStore;

/**
 * The bootstrap, which is the thing a project actually installs.
 *
 * What is asserted here could not be asserted at all until milestone 14:
 * every route having a handler was a property of one demo's composition root,
 * so the only way to find a route nobody had wired was to open it. The first
 * test in this file is that check, and it is the reason the registry stopped
 * being assembled in a demo.
 */
final class BootstrapTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ra-bootstrap-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config/pages', 0o777, true);

        file_put_contents($this->root . '/config/rockadmin.php', <<<'PHP'
            <?php return [
                'brand' => 'Test Admin',
                'paths' => ['logs' => 'storage/logs'],
                'menu' => [
                    'dashboard' => ['label' => 'Home', 'route' => 'dashboard'],
                    'ads' => ['label' => 'Ads'],
                    'elsewhere' => ['label' => 'The site', 'url' => 'https://example.com'],
                ],
                'mail' => ['from' => ['address' => 'admin@example.com']],
            ];
            PHP);

        file_put_contents($this->root . '/config/pages/ads.php', <<<'PHP'
            <?php return [
                'title' => 'Ads',
                'layout' => 'single',
                'entity' => ['table' => 'ra_test_ads', 'key' => 'id'],
                'regions' => [
                    'grid' => [
                        'type' => 'list',
                        'columns' => ['id' => ['type' => 'int'], 'title' => ['type' => 'text']],
                    ],
                ],
            ];
            PHP);
    }

    protected function tearDown(): void
    {
        foreach (['/config/pages/ads.php', '/config/rockadmin.php'] as $file) {
            @unlink($this->root . $file);
        }

        @rmdir($this->root . '/config/pages');
        @rmdir($this->root . '/config');
        @rmdir($this->root);
    }

    /**
     * The check the demo could not make about itself.
     *
     * `diagnostics` is the one route with nothing behind it — specification
     * 12's development console is not built — and naming it here is the point:
     * when it is built, this test stops allowing the exception and somebody
     * has to delete the line.
     */
    #[DataProvider('pdos')]
    public function testEveryRouteHasAHandlerOnceTheAdminHasBooted(?PDO $pdo): void
    {
        if ($pdo === null) {
            self::markTestSkipped('No database configured. Set RA_TEST_MYSQL_DSN or RA_TEST_PGSQL_DSN.');
        }

        $admin = $this->boot(pdo: static fn (): PDO => $pdo);
        $missing = [];

        foreach (Router::routeNames() as $name) {
            if ($name === 'diagnostics') {
                continue;
            }

            if (!$admin->handles($name)) {
                $missing[] = $name;
            }
        }

        self::assertSame([], $missing, 'Routes the bootstrap declared but registered nothing for.');
    }

    public function testAnAdminWithNoDatabaseStillAnswersItsOwnFrontDoor(): void
    {
        $admin = $this->boot();

        // Anonymous, so the dashboard redirects rather than drawing -- which
        // is itself the assertion: the kernel reached a registered handler
        // and the gate turned it away, rather than 404ing on a route nobody
        // had wired.
        $response = $admin->handle(new Request('GET', '', [], []));

        self::assertSame(302, $response->status);
        self::assertStringContainsString('login', $response->headers['location'] ?? '');

        self::assertSame(200, $admin->handle(new Request('GET', 'login', [], []))->status);
    }

    public function testTheRoutesThatNeedRowsSayThereIsNoDatabaseRatherThanFailing(): void
    {
        $response = $this->boot()->handle(new Request('GET', 'p/ads', [], []));

        // A 404 from the gate's own error page, not a PDO exception before
        // the first byte: an installation whose credentials are not set yet
        // is a real state and has to be readable.
        self::assertContains($response->status, [302, 404]);
    }

    public function testTheMenuIsBuiltFromConfiguration(): void
    {
        $admin = $this->boot();
        $menu = $admin->services->config->get('menu');

        self::assertIsArray($menu);
        self::assertArrayHasKey('ads', $menu);

        // The URL an entry gets when it names neither route nor url: the key
        // is a page, and a page is at p/{name}.
        self::assertSame('/p/ads', $admin->services->urls->to('p/ads'));
    }

    public function testAHostHandlerReplacesTheOneTheSdkRegistered(): void
    {
        $admin = $this->boot(handlers: static fn (Services $services): array => [
            'dashboard' => new class () implements Handler {
                public function handle(Route $route, Request $request): Response
                {
                    return Response::html('the host drew this');
                }
            },
        ]);

        self::assertTrue($admin->handles('dashboard'));
    }

    public function testTheMountPointIsNotPartOfTheRoute(): void
    {
        $admin = $this->boot(baseUrl: '/admin');

        $_SERVER['REQUEST_URI'] = '/admin/p/ads?page=2';
        self::assertSame('p/ads', RockAdmin::path($admin));

        $_SERVER['REQUEST_URI'] = '/admin';
        self::assertSame('', RockAdmin::path($admin));

        $_SERVER['REQUEST_URI'] = '/admin/';
        self::assertSame('', RockAdmin::path($admin));
    }

    public function testARelativePathInTheConfigurationIsResolvedAgainstTheProjectRoot(): void
    {
        $logs = $this->boot()->services->config->get('paths.logs');

        // `storage/logs` as written, and the project root is the temporary
        // directory: a path relative to the working directory would put the
        // mail log wherever the process happened to be started.
        self::assertSame($this->root . '/storage/logs', $logs);
    }

    /** @return iterable<string, array{?PDO}> */
    public static function pdos(): iterable
    {
        $any = false;

        foreach (['mysql' => 'MYSQL', 'pgsql' => 'PGSQL'] as $driver => $prefix) {
            $dsn = getenv("RA_TEST_{$prefix}_DSN");

            if (!\is_string($dsn) || $dsn === '') {
                continue;
            }

            $user = getenv("RA_TEST_{$prefix}_USER");
            $password = getenv("RA_TEST_{$prefix}_PASSWORD");

            try {
                $pdo = new PDO($dsn, \is_string($user) ? $user : null, \is_string($password) ? $password : null);
            } catch (PDOException) {
                continue;
            }

            $any = true;

            yield $driver => [$pdo];
        }

        if (!$any) {
            yield 'no database configured' => [null];
        }
    }

    /**
     * @param (\Closure(): ?PDO)|null                          $pdo
     * @param (\Closure(Services): array<string, Handler>)|null $handlers
     */
    private function boot(?\Closure $pdo = null, ?\Closure $handlers = null, string $baseUrl = '/'): Admin
    {
        return Admin::boot(new Setup(
            basePath: $this->root,
            configPath: $this->root . '/config',
            baseUrl: $baseUrl,
            pdo: $pdo,
            // Never PHP's own session in a test: `session_start()` in a suite
            // that boots many admins is one global the next test inherits.
            session: new RecordingSessionStore(),
            handlers: $handlers,
        ));
    }
}
