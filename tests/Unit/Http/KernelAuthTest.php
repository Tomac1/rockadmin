<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\Gate;
use RockAdmin\Auth\Identity;
use RockAdmin\Auth\Permission;
use RockAdmin\Auth\Roles;
use RockAdmin\Config\Enums;
use RockAdmin\Http\ErrorHandler;
use RockAdmin\Http\Handler;
use RockAdmin\Http\HandlerRegistry;
use RockAdmin\Http\Kernel;
use RockAdmin\Http\Request;
use RockAdmin\Http\Response;
use RockAdmin\Http\Route;
use RockAdmin\Http\Router;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\FakeAuthenticator;
use RockAdmin\Tests\Support\RecordingSessionStore;

/**
 * Steps 4 and 7 of the lifecycle, where the kernel runs them.
 *
 * A document request that is not signed in is redirected to the login page,
 * because a person needs somewhere to go. A fragment or an action is answered
 * with 401, because redirecting an AJAX call to a login page renders the login
 * form into the grid — which is how session expiry looks in most admin tools,
 * and is a bug rather than a feature.
 */
#[CoversClass(Kernel::class)]
final class KernelAuthTest extends TestCase
{
    private string $root;

    /** @var list<string> route names whose handler actually ran */
    private array $reached = [];

    protected function setUp(): void
    {
        $this->reached = [];
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-kernel-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);
        $this->writePage('ads');
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    // ------------------------------------------------------- step 4: signed in at all

    /** @return iterable<string, array{string, string}> */
    public static function documentRequests(): iterable
    {
        yield 'dashboard' => ['GET', ''];
        yield 'page index' => ['GET', 'p/ads'];
        yield 'page create' => ['GET', 'p/ads/create'];
        yield 'page detail' => ['GET', 'p/ads/1'];
    }

    #[DataProvider('documentRequests')]
    public function testAnAnonymousDocumentRequestIsRedirectedToLogin(string $method, string $path): void
    {
        $response = $this->kernel()->handle(new Request($method, $path));

        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->headers['location'] ?? null);
        $this->assertSame([], $this->reached);
    }

    public function testTheLoginPageItselfIsNotRedirected(): void
    {
        $response = $this->kernel()->handle(new Request('GET', 'login'));

        $this->assertSame(200, $response->status);
        $this->assertSame(['login.form'], $this->reached);
    }

    public function testPostingTheLoginFormIsNotRedirected(): void
    {
        $response = $this->kernel()->handle(new Request('POST', 'login'));

        $this->assertSame(200, $response->status);
        $this->assertSame(['login.submit'], $this->reached);
    }

    public function testAssetsAreNotRedirected(): void
    {
        $response = $this->kernel()->handle(new Request('GET', '_assets/css/rockadmin.css'));

        $this->assertSame(200, $response->status);
        $this->assertSame(['assets'], $this->reached);
    }

    /** @return iterable<string, array{string, string}> */
    public static function nonDocumentRequests(): iterable
    {
        yield 'fragment' => ['GET', 'r/ads/grid'];
        yield 'action' => ['POST', 'a/ads/update'];
    }

    #[DataProvider('nonDocumentRequests')]
    public function testAnAnonymousFragmentOrActionIs401AndNotARedirect(string $method, string $path): void
    {
        $response = $this->kernel()->handle(new Request($method, $path));

        $this->assertSame(401, $response->status);
        $this->assertArrayNotHasKey('location', $response->headers);
        $this->assertSame([], $this->reached);
    }

    public function testAValidSessionReachesTheHandler(): void
    {
        $response = $this->kernel(['editor' => ['permissions' => ['ads.*']]], '7')
            ->handle(new Request('GET', 'p/ads'));

        $this->assertSame(200, $response->status);
        $this->assertSame(['page.index'], $this->reached);
    }

    public function testASessionNamingAUserThatNoLongerExistsIsAnonymousRatherThanACrash(): void
    {
        $response = $this->kernel(['editor' => ['permissions' => ['ads.*']]], '404')
            ->handle(new Request('GET', 'p/ads'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->headers['location'] ?? null);
    }

    public function testATamperedSessionIsAnonymousRatherThanAFault(): void
    {
        $response = $this->kernel(['editor' => ['permissions' => ['ads.*']]], '7 OR 1=1')
            ->handle(new Request('GET', 'p/ads'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->headers['location'] ?? null);
    }

    public function testTheGateRunsBeforeTheHandlerIsEvenLookedUp(): void
    {
        // No handler is registered for `page.edit` in this fixture, so a 404
        // would be the answer if the gate ran after the lookup. It must not
        // be: whether a handler exists is not something an unauthorised
        // caller gets to learn.
        $response = $this->kernel(['viewer' => ['permissions' => ['*.view']]], '7')
            ->handle(new Request('GET', 'p/ads/1/edit'));

        $this->assertSame(403, $response->status);
    }

    // ------------------------------------------------------- step 7: may this user do this

    /** @return iterable<string, array{string, string}> */
    public static function adsRequests(): iterable
    {
        yield 'page' => ['GET', 'p/ads'];
        yield 'fragment' => ['GET', 'r/ads/grid'];
        yield 'action' => ['POST', 'a/ads/update'];
    }

    #[DataProvider('adsRequests')]
    public function testAUserWithoutViewIsForbiddenEverywhere(string $method, string $path): void
    {
        $response = $this->kernel(['nobody' => ['permissions' => []]], '7')
            ->handle(new Request($method, $path));

        $this->assertSame(403, $response->status);
        $this->assertSame([], $this->reached);
    }

    #[DataProvider('adsRequests')]
    public function testAForbiddenAnswerIsA403AndNotARedirect(string $method, string $path): void
    {
        $response = $this->kernel(['nobody' => ['permissions' => []]], '7')
            ->handle(new Request($method, $path));

        $this->assertSame(403, $response->status);
        $this->assertArrayNotHasKey('location', $response->headers);
    }

    public function testAViewerMayOpenThePageAndMayNotDelete(): void
    {
        $viewer = ['viewer' => ['permissions' => ['ads.view']]];

        $this->assertSame(200, $this->kernel($viewer, '7')->handle(new Request('GET', 'p/ads'))->status);

        $this->reached = [];

        $response = $this->kernel($viewer, '7')->handle(new Request('POST', 'a/ads/delete'));

        $this->assertSame(403, $response->status);
        $this->assertSame([], $this->reached);
    }

    public function testAnEditorMayDelete(): void
    {
        $response = $this->kernel(['editor' => ['permissions' => ['ads.*']]], '7')
            ->handle(new Request('POST', 'a/ads/delete'));

        $this->assertSame(200, $response->status);
        $this->assertSame(['action'], $this->reached);
    }

    public function testAnActionVerbNobodyDeclaredIsForbiddenRatherThanDispatched(): void
    {
        $response = $this->kernel(['god' => ['permissions' => ['*']]], '7')
            ->handle(new Request('POST', 'a/ads/publish'));

        $this->assertSame(403, $response->status);
        $this->assertSame([], $this->reached);
    }

    public function testASignedInUserStillGets404ForAPathThatMatchesNoRoute(): void
    {
        $response = $this->kernel(['god' => ['permissions' => ['*']]], '7')
            ->handle(new Request('GET', 'nope/at/all'));

        $this->assertSame(404, $response->status);
    }

    // ------------------------------------------------------- fixtures

    /** @param array<string, mixed> $roles */
    private function kernel(array $roles = [], ?string $sessionUserId = null): Kernel
    {
        $session = new RecordingSessionStore(
            $sessionUserId === null ? [] : [Identity::SESSION_KEY => $sessionUserId],
        );

        $auth = new FakeAuthenticator([
            '7' => new AuthUser('7', 'jana@example.test', 'Jana', array_keys($roles)),
        ]);

        $pages = new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );

        return new Kernel(
            new Router(),
            $this->handlers(),
            new ErrorHandler(),
            new Identity($session, $auth),
            new Gate(Roles::fromConfig($roles, Permission::catalogue($pages))),
            new UrlGenerator('/'),
        );
    }

    private function handlers(): HandlerRegistry
    {
        $handlers = new HandlerRegistry();

        foreach (['login.form', 'login.submit', 'assets', 'dashboard', 'page.index', 'page.create', 'page.detail', 'region', 'action'] as $name) {
            $handlers->register($name, $this->handler($name));
        }

        return $handlers;
    }

    private function handler(string $name): Handler
    {
        $record = function (string $reached): void {
            $this->reached[] = $reached;
        };

        return new class ($name, $record) implements Handler {
            /** @param Closure(string): void $record */
            public function __construct(
                private readonly string $name,
                private readonly Closure $record,
            ) {
            }

            public function handle(Route $route, Request $request): Response
            {
                ($this->record)($this->name);

                return Response::html('handled ' . $this->name);
            }
        };
    }

    private function writePage(string $name): void
    {
        $body = "<?php\n\nreturn [\n"
            . "    'title' => '{$name}',\n"
            . "    'entity' => ['table' => '{$name}'],\n"
            . "    'regions' => [\n"
            . "        'grid' => [\n"
            . "            'type' => 'list',\n"
            . "            'columns' => ['id' => ['source' => 'id'], 'title' => ['source' => 'title']],\n"
            . "        ],\n"
            . "        'form' => ['type' => 'form', 'form' => ['fields' => ['title' => ['type' => 'text']]]],\n"
            . "    ],\n"
            . "];\n";

        file_put_contents($this->root . '/pages/' . $name . '.php', $body);
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
