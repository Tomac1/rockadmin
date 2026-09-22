# RockAdmin Milestone 1 — HTTP Foundation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the HTTP core RockAdmin sits on — request, response, routing,
URL generation, sessions, CSRF and error handling — so that a host project can
hand the library a path and receive a response.

**Architecture:** Plain value objects and small single-purpose classes, no
framework and no container. The kernel matches a request against a fixed route
table and passes it to a registered handler. Everything in this milestone is
pure PHP with no database and no configuration, so every test runs in
milliseconds with no fixtures.

**Tech Stack:** PHP 8.4, PHPUnit 11, PHPStan level max, PHP-CS-Fixer (PSR-12).

**Spec:** `docs/design/2026-09-21-rockadmin-design.md` — sections 4.2, 4.3,
4.4, 5.1, 5.2, 5.3, 5.5, 5.6.

## Global Constraints

These apply to every task in this plan and every plan that follows.

- **PHP 8.4 minimum.** Use typed properties, constructor promotion, readonly,
  enums and `never` where they fit.
- **Composer runtime dependencies are `php`, `ext-pdo`, `ext-json`,
  `ext-mbstring` and nothing else.** `ComposerConstraintsTest` fails the build
  otherwise. Do not add a package to solve a problem in this milestone.
- **No framework references.** Nothing in `src/` may mention Laravel, Symfony
  or Illuminate, including in comments.
- **`declare(strict_types=1);` in every PHP file.**
- **PHPStan runs at level max.** Annotate array shapes; do not add
  `@phpstan-ignore` comments or baseline entries.
- **PSR-12 via PHP-CS-Fixer**, with short arrays, single quotes, ordered
  imports and trailing commas in multi-line calls. Run `composer run cs:fix`
  before committing.
- **Naming:** `PascalCase` classes, `camelCase` methods and variables,
  `snake_case` config keys and database columns.
- **English everywhere** — code, comments, commit messages, test names.
- **Namespaces:** source is `RockAdmin\` mapped to `src/`; tests are
  `RockAdmin\Tests\` mapped to `tests/`.
- **Verification before any completion claim:** run `composer run check` and
  read the output. Never state that something passes without having run it.

## File Structure

```
src/Http/
├── Request.php          immutable request value object + path normalisation
├── Response.php         immutable response value object
├── Route.php            a matched route: name + parameters
├── Router.php           the fixed route table and the matcher
├── UrlGenerator.php     builds links in path mode and query mode
├── SessionStore.php     interface the host implements
├── NativeSessionStore.php  default, native PHP sessions
├── ArraySessionStore.php   in-memory, for tests and CLI
├── Csrf.php             token issue and constant-time validation
├── HttpException.php    base for status-carrying exceptions
├── NotFoundException.php
├── ForbiddenException.php
├── ErrorHandler.php     turns a throwable into a Response
├── Handler.php          interface a route handler implements
├── HandlerRegistry.php  route name -> handler
└── Kernel.php           match, dispatch, catch

tests/Unit/Http/         one test file per class above that has behaviour
```

Each class has one responsibility and no knowledge of the layers above it.
`Router` knows paths but not what a page is. `Kernel` knows handlers but not
what they do. Nothing here reads configuration or touches a database — those
arrive in milestones 2 and 3 and plug in through `HandlerRegistry`.

---

### Task 1: Request value object and path normalisation

**Files:**
- Create: `src/Http/Request.php`
- Test: `tests/Unit/Http/RequestTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `RockAdmin\Http\Request` with readonly `string $method`, `string $path`,
    `array<string,mixed> $query`, `array<string,mixed> $body`,
    `array<string,string> $cookies`, `array<string,string> $headers`
  - `Request::normalizePath(string $path): string`
  - `Request::fromGlobals(string $path): self`
  - `$request->isPost(): bool`
  - `$request->query(string $key, mixed $default = null): mixed`
  - `$request->input(string $key, mixed $default = null): mixed`
  - `$request->header(string $name): ?string`

Path normalisation is the security boundary for everything that follows:
`_assets/{path...}` serves files from disk, so `..` segments must not survive
here. Header names are stored lowercased so lookups do not depend on how the
server spelled them.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\Request;

#[CoversClass(Request::class)]
final class RequestTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function pathCases(): array
    {
        return [
            'empty stays empty'        => ['', ''],
            'leading slash removed'    => ['/p/users', 'p/users'],
            'trailing slash removed'   => ['p/users/', 'p/users'],
            'both slashes removed'     => ['/p/users/', 'p/users'],
            'duplicate slashes'        => ['p//users', 'p/users'],
            'only slashes'             => ['///', ''],
            'dot segment dropped'      => ['p/./users', 'p/users'],
            'parent segment dropped'   => ['_assets/../../etc/passwd', '_assets/etc/passwd'],
            'unicode kept'             => ['p/uživatelé', 'p/uživatelé'],
        ];
    }

    #[DataProvider('pathCases')]
    public function testNormalizePath(string $input, string $expected): void
    {
        $this->assertSame($expected, Request::normalizePath($input));
    }

    public function testConstructorNormalisesThePath(): void
    {
        $request = new Request('GET', '/p/users/');

        $this->assertSame('p/users', $request->path);
    }

    public function testMethodIsUppercased(): void
    {
        $this->assertSame('POST', (new Request('post', ''))->method);
    }

    public function testIsPost(): void
    {
        $this->assertTrue((new Request('POST', ''))->isPost());
        $this->assertFalse((new Request('GET', ''))->isPost());
    }

    public function testQueryAndInputReturnDefaultsWhenMissing(): void
    {
        $request = new Request('POST', 'a/ads/create', ['page' => '3'], ['title' => 'Bike']);

        $this->assertSame('3', $request->query('page'));
        $this->assertNull($request->query('missing'));
        $this->assertSame('fallback', $request->query('missing', 'fallback'));
        $this->assertSame('Bike', $request->input('title'));
        $this->assertSame('fallback', $request->input('missing', 'fallback'));
    }

    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $request = new Request('GET', '', [], [], [], ['X-Csrf-Token' => 'abc']);

        $this->assertSame('abc', $request->header('x-csrf-token'));
        $this->assertSame('abc', $request->header('X-CSRF-TOKEN'));
        $this->assertNull($request->header('x-other'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Http/RequestTest.php`
Expected: FAIL — `Class "RockAdmin\Http\Request" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/**
 * An incoming admin request, already stripped of the host's mount prefix.
 *
 * RockAdmin never sees a URL, only a path. How the host derived that path —
 * a rewrite rule, its own router, PATH_INFO or a query parameter — is not our
 * concern. See the design spec, section 5.3.
 */
final class Request
{
    public readonly string $method;

    public readonly string $path;

    /** @var array<string, string> header names lowercased on construction */
    public readonly array $headers;

    /**
     * @param array<string, mixed>  $query
     * @param array<string, mixed>  $body
     * @param array<string, string> $cookies
     * @param array<string, string> $headers header names in any case
     */
    public function __construct(
        string $method,
        string $path,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $cookies = [],
        array $headers = [],
    ) {
        $this->method = strtoupper($method);
        $this->path = self::normalizePath($path);
        $this->headers = array_change_key_case($headers);
    }

    /**
     * Reduces a path to bare segments.
     *
     * Empty, "." and ".." segments are dropped rather than rejected: a bad
     * path should fail to match a route, not raise a 500. Dropping ".." here
     * is what keeps the asset route from escaping its directory.
     */
    public static function normalizePath(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    public static function fromGlobals(string $path): self
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_') && is_scalar($value)) {
                $headers[str_replace('_', '-', substr($key, 5))] = (string) $value;
            }
        }

        $method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';

        $cookies = [];

        foreach ($_COOKIE as $name => $value) {
            if (is_string($value)) {
                $cookies[(string) $name] = $value;
            }
        }

        return new self($method, $path, $_GET, $_POST, $cookies, $headers);
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Http/RequestTest.php`
Expected: PASS, 14 assertions or more.

- [ ] **Step 5: Run the full check**

Run: `composer run check`
Expected: coding standards clean, PHPStan reports no errors, all tests pass.
If PHPStan complains about `$_GET`/`$_POST` being `mixed`, add the array shape
annotations shown above rather than a cast.

- [ ] **Step 6: Commit**

```bash
git add src/Http/Request.php tests/Unit/Http/RequestTest.php
git commit -m "Add Request value object with path normalisation"
```

---

### Task 2: Response value object

**Files:**
- Create: `src/Http/Response.php`
- Test: `tests/Unit/Http/ResponseTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `RockAdmin\Http\Response` with readonly `int $status`, `string $body`,
    `array<string,string> $headers`
  - `Response::html(string $body, int $status = 200): self`
  - `Response::redirect(string $location, int $status = 302): self`
  - `Response::notFound(string $body = ''): self`
  - `$response->withHeader(string $name, string $value): self`
  - `$response->send(): void`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\Response;

#[CoversClass(Response::class)]
final class ResponseTest extends TestCase
{
    public function testHtmlSetsContentTypeAndStatus(): void
    {
        $response = Response::html('<p>Hello</p>');

        $this->assertSame(200, $response->status);
        $this->assertSame('<p>Hello</p>', $response->body);
        $this->assertSame('text/html; charset=utf-8', $response->headers['content-type']);
    }

    public function testRedirectCarriesLocationAndDefaultStatus(): void
    {
        $response = Response::redirect('/admin/p/ads');

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/p/ads', $response->headers['location']);
        $this->assertSame('', $response->body);
    }

    public function testNotFoundUsesStatus404(): void
    {
        $this->assertSame(404, Response::notFound()->status);
    }

    public function testWithHeaderReturnsANewInstanceAndLowercasesTheName(): void
    {
        $original = Response::html('x');
        $modified = $original->withHeader('X-Robots-Tag', 'noindex');

        $this->assertNotSame($original, $modified);
        $this->assertArrayNotHasKey('x-robots-tag', $original->headers);
        $this->assertSame('noindex', $modified->headers['x-robots-tag']);
        $this->assertSame('text/html; charset=utf-8', $modified->headers['content-type']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Http/ResponseTest.php`
Expected: FAIL — `Class "RockAdmin\Http\Response" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/**
 * An outgoing response. Immutable, so a handler can hand one back and a
 * caller can add headers without the original changing under it.
 */
final class Response
{
    /** @var array<string, string> */
    public readonly array $headers;

    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status = 200,
        public readonly string $body = '',
        array $headers = [],
    ) {
        $this->headers = array_change_key_case($headers);
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body, ['content-type' => 'text/html; charset=utf-8']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, '', ['location' => $location]);
    }

    public static function notFound(string $body = ''): self
    {
        return self::html($body, 404);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, $this->body, [strtolower($name) => $value] + $this->headers);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        echo $this->body;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Http/ResponseTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Http/Response.php tests/Unit/Http/ResponseTest.php
git commit -m "Add Response value object"
```

---

### Task 3: Route table and matcher

**Files:**
- Create: `src/Http/Route.php`, `src/Http/Router.php`
- Test: `tests/Unit/Http/RouterTest.php`

**Interfaces:**
- Consumes: `Request` from Task 1.
- Produces:
  - `RockAdmin\Http\Route` with readonly `string $name`,
    `array<string,string> $params`
  - `$route->param(string $key): string` — throws `InvalidArgumentException`
    when absent
  - `Router::match(Request $request): ?Route`
  - `Router::patternFor(string $name): string` — throws
    `InvalidArgumentException` for an unknown name; used by `UrlGenerator`

Route names produced here are the contract every later milestone binds
handlers to. They are: `dashboard`, `login.form`, `login.submit`, `logout`,
`password.request.form`, `password.request.submit`, `password.reset.form`,
`password.reset.submit`, `page.index`, `page.create`, `page.detail`,
`page.edit`, `page.copy`, `region`, `action`, `workspace.switch`, `assets`,
`diagnostics`, `setup.form`, `setup.submit`.

Two ordering rules matter and are covered by tests: `p/{page}/create` must be
tried before `p/{page}/{id}`, or creating a row would be read as viewing a row
whose id is "create"; and every data-changing route is POST only, per spec
section 5.1.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\Request;
use RockAdmin\Http\Route;
use RockAdmin\Http\Router;

#[CoversClass(Router::class)]
#[CoversClass(Route::class)]
final class RouterTest extends TestCase
{
    /** @return array<string, array{string, string, string, array<string, string>}> */
    public static function matches(): array
    {
        return [
            'dashboard'   => ['GET', '', 'dashboard', []],
            'login form'  => ['GET', 'login', 'login.form', []],
            'login post'  => ['POST', 'login', 'login.submit', []],
            'page index'  => ['GET', 'p/ads', 'page.index', ['page' => 'ads']],
            'page create' => ['GET', 'p/ads/create', 'page.create', ['page' => 'ads']],
            'page detail' => ['GET', 'p/ads/42', 'page.detail', ['page' => 'ads', 'id' => '42']],
            'page edit'   => ['GET', 'p/ads/42/edit', 'page.edit', ['page' => 'ads', 'id' => '42']],
            'page copy'   => ['GET', 'p/ads/42/copy', 'page.copy', ['page' => 'ads', 'id' => '42']],
            'region'      => ['GET', 'r/users/detail', 'region', ['page' => 'users', 'region' => 'detail']],
            'action'      => ['POST', 'a/ads/archive', 'action', ['page' => 'ads', 'action' => 'archive']],
            'workspace'   => ['POST', 'w/cyklobazar', 'workspace.switch', ['workspace' => 'cyklobazar']],
            'assets'      => ['GET', '_assets/css/core.css', 'assets', ['path' => 'css/core.css']],
            'diagnostics' => ['GET', '_diagnostics', 'diagnostics', []],
            'reset token' => ['GET', 'password/reset/abc123', 'password.reset.form', ['token' => 'abc123']],
        ];
    }

    /** @param array<string, string> $params */
    #[DataProvider('matches')]
    public function testMatches(string $method, string $path, string $name, array $params): void
    {
        $route = (new Router())->match(new Request($method, $path));

        $this->assertNotNull($route, "Expected {$method} /{$path} to match a route.");
        $this->assertSame($name, $route->name);
        $this->assertSame($params, $route->params);
    }

    public function testCreateWinsOverDetailBecauseOrderMatters(): void
    {
        $route = (new Router())->match(new Request('GET', 'p/ads/create'));

        $this->assertNotNull($route);
        $this->assertSame('page.create', $route->name);
    }

    /** @return array<string, array{string, string}> */
    public static function nonMatches(): array
    {
        return [
            'unknown prefix'      => ['GET', 'x/ads'],
            'action must be post' => ['GET', 'a/ads/archive'],
            'workspace must post' => ['GET', 'w/cyklobazar'],
            'logout must be post' => ['GET', 'logout'],
            'too many segments'   => ['GET', 'p/ads/42/edit/extra'],
            'assets need a path'  => ['GET', '_assets'],
        ];
    }

    #[DataProvider('nonMatches')]
    public function testDoesNotMatch(string $method, string $path): void
    {
        $this->assertNull((new Router())->match(new Request($method, $path)));
    }

    public function testPatternForKnownName(): void
    {
        $this->assertSame('p/{page}/{id}/edit', Router::patternFor('page.edit'));
    }

    public function testPatternForUnknownNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('nope');

        Router::patternFor('nope');
    }

    public function testRouteParamThrowsWhenMissing(): void
    {
        $route = new Route('page.index', ['page' => 'ads']);

        $this->assertSame('ads', $route->param('page'));

        $this->expectException(\InvalidArgumentException::class);
        $route->param('id');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Http/RouterTest.php`
Expected: FAIL — `Class "RockAdmin\Http\Router" not found`.

- [ ] **Step 3: Write `Route`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use InvalidArgumentException;

/** A matched route: which one, and the values pulled out of the path. */
final class Route
{
    /** @param array<string, string> $params */
    public function __construct(
        public readonly string $name,
        public readonly array $params = [],
    ) {
    }

    public function param(string $key): string
    {
        return $this->params[$key]
            ?? throw new InvalidArgumentException("Route {$this->name} has no parameter {$key}.");
    }
}
```

- [ ] **Step 4: Write `Router`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use InvalidArgumentException;

/**
 * The route table. Fixed, ordered, and small enough to read in one screen.
 *
 * Prefixes carry the meaning: /p/ renders a page, /r/ renders one region as a
 * fragment, /a/ changes data. Anything that changes data is POST only, so no
 * link scanner, mail proxy or browser prefetch can trigger it. See the design
 * spec, section 5.1.
 *
 * @phpstan-type RouteDefinition array{0: string, 1: string, 2: string}
 */
final class Router
{
    /**
     * Order matters: the first match wins, so literal segments come before
     * placeholders that would also accept them.
     *
     * @var list<RouteDefinition>
     */
    private const ROUTES = [
        ['GET',  '',                       'dashboard'],
        ['GET',  'login',                  'login.form'],
        ['POST', 'login',                  'login.submit'],
        ['POST', 'logout',                 'logout'],
        ['GET',  'password/reset',         'password.request.form'],
        ['POST', 'password/reset',         'password.request.submit'],
        ['GET',  'password/reset/{token}', 'password.reset.form'],
        ['POST', 'password/reset/{token}', 'password.reset.submit'],
        ['GET',  'p/{page}',               'page.index'],
        ['GET',  'p/{page}/create',        'page.create'],
        ['GET',  'p/{page}/{id}',          'page.detail'],
        ['GET',  'p/{page}/{id}/edit',     'page.edit'],
        ['GET',  'p/{page}/{id}/copy',     'page.copy'],
        ['GET',  'r/{page}/{region}',      'region'],
        ['POST', 'a/{page}/{action}',      'action'],
        ['POST', 'w/{workspace}',          'workspace.switch'],
        ['GET',  '_assets/{path...}',      'assets'],
        ['GET',  '_diagnostics',           'diagnostics'],
        ['GET',  '_setup',                 'setup.form'],
        ['POST', '_setup',                 'setup.submit'],
    ];

    public function match(Request $request): ?Route
    {
        $segments = $request->path === '' ? [] : explode('/', $request->path);

        foreach (self::ROUTES as [$method, $pattern, $name]) {
            if ($method !== $request->method) {
                continue;
            }

            $params = self::matchPattern($pattern, $segments);

            if ($params !== null) {
                return new Route($name, $params);
            }
        }

        return null;
    }

    /** Used by UrlGenerator to build a link from a route name. */
    public static function patternFor(string $name): string
    {
        foreach (self::ROUTES as [, $pattern, $routeName]) {
            if ($routeName === $name) {
                return $pattern;
            }
        }

        throw new InvalidArgumentException("Unknown route name: {$name}.");
    }

    /**
     * @param  list<string>               $segments
     * @return array<string, string>|null null when the pattern does not apply
     */
    private static function matchPattern(string $pattern, array $segments): ?array
    {
        $parts = $pattern === '' ? [] : explode('/', $pattern);
        $params = [];

        foreach ($parts as $index => $part) {
            if (str_starts_with($part, '{') && str_ends_with($part, '...}')) {
                $rest = array_slice($segments, $index);

                if ($rest === []) {
                    return null;
                }

                $params[substr($part, 1, -4)] = implode('/', $rest);

                return $params;
            }

            if (!isset($segments[$index])) {
                return null;
            }

            if (str_starts_with($part, '{')) {
                $params[trim($part, '{}')] = $segments[$index];

                continue;
            }

            if ($segments[$index] !== $part) {
                return null;
            }
        }

        return count($segments) === count($parts) ? $params : null;
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Http/RouterTest.php`
Expected: PASS.

- [ ] **Step 6: Run the full check**

Run: `composer run check`
Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add src/Http/Route.php src/Http/Router.php tests/Unit/Http/RouterTest.php
git commit -m "Add the route table and matcher"
```

---

### Task 4: URL generation in both modes

**Files:**
- Create: `src/Http/UrlGenerator.php`
- Test: `tests/Unit/Http/UrlGeneratorTest.php`

**Interfaces:**
- Consumes: `Request::normalizePath()` from Task 1, `Router::patternFor()`
  from Task 3.
- Produces:
  - `new UrlGenerator(string $base, string $mode = 'path', string $queryKey = 'ra')`
  - `$urls->to(string $path, array<string, string|int> $query = []): string`
  - `$urls->route(string $name, array<string, string|int> $params = [], array<string, string|int> $query = []): string`

Every link in every template goes through this class, because the admin has to
work without rewrite rules. Templates are forbidden from writing URLs by hand
(spec 4.3, rule 4), and this is what they use instead.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\UrlGenerator;

#[CoversClass(UrlGenerator::class)]
final class UrlGeneratorTest extends TestCase
{
    public function testPathMode(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->assertSame('/admin/', $urls->to(''));
        $this->assertSame('/admin/p/ads', $urls->to('p/ads'));
        $this->assertSame('/admin/p/ads?page=3', $urls->to('p/ads', ['page' => 3]));
    }

    public function testQueryMode(): void
    {
        $urls = new UrlGenerator('/admin/index.php', 'query');

        $this->assertSame('/admin/index.php', $urls->to(''));
        $this->assertSame('/admin/index.php?ra=p%2Fads', $urls->to('p/ads'));
        $this->assertSame('/admin/index.php?ra=p%2Fads&page=3', $urls->to('p/ads', ['page' => 3]));
    }

    public function testRouteSubstitutesParameters(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->assertSame(
            '/admin/p/ads/42/edit',
            $urls->route('page.edit', ['page' => 'ads', 'id' => 42]),
        );
        $this->assertSame(
            '/admin/r/users/detail?id=7',
            $urls->route('region', ['page' => 'users', 'region' => 'detail'], ['id' => 7]),
        );
    }

    public function testRouteEncodesParameters(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->assertSame(
            '/admin/p/ads/a%2Fb%20c',
            $urls->route('page.detail', ['page' => 'ads', 'id' => 'a/b c']),
        );
    }

    public function testWildcardParameterKeepsItsSlashes(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->assertSame('/admin/_assets/css/core.css', $urls->route('assets', ['path' => 'css/core.css']));
    }

    public function testRouteWithAMissingParameterThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('id');

        (new UrlGenerator('/admin'))->route('page.edit', ['page' => 'ads']);
    }

    public function testUnknownModeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UrlGenerator('/admin', 'magic');
    }

    public function testDotSegmentsInParametersSurvive(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->assertSame('/admin/p/ads/..', $urls->route('page.detail', ['page' => 'ads', 'id' => '..']));
        $this->assertSame('/admin/p/ads/.', $urls->route('page.detail', ['page' => 'ads', 'id' => '.']));
    }

    public function testToStripsDotSegmentsFromAHandWrittenPath(): void
    {
        $urls = new UrlGenerator('/admin');

        // Dot segments are discarded, not resolved: if they were resolved this
        // would be /admin/detail. Compare testDotSegmentsInParametersSurvive(),
        // where route() leaves an encoded ".." parameter intact.
        $this->assertSame('/admin/p/ads/detail', $urls->to('p/ads/../.././detail'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Http/UrlGeneratorTest.php`
Expected: FAIL — `Class "RockAdmin\Http\UrlGenerator" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use InvalidArgumentException;

/**
 * Builds admin links.
 *
 * In "path" mode links look like /admin/p/ads; in "query" mode the same route
 * becomes /admin/index.php?ra=p/ads, which needs no rewrite rule. Templates
 * must never write a URL by hand, or the admin breaks in query mode.
 */
final class UrlGenerator
{
    public const MODE_PATH = 'path';

    public const MODE_QUERY = 'query';

    public function __construct(
        private readonly string $base,
        private readonly string $mode = self::MODE_PATH,
        private readonly string $queryKey = 'ra',
    ) {
        if ($mode !== self::MODE_PATH && $mode !== self::MODE_QUERY) {
            throw new InvalidArgumentException(
                "Unknown url_mode '{$mode}'. Use 'path' or 'query'.",
            );
        }
    }

    /** @param array<string, string|int> $query */
    public function to(string $path, array $query = []): string
    {
        return $this->build(Request::normalizePath($path), $query);
    }

    /**
     * @param array<string, string|int> $params route parameters, e.g. ['page' => 'ads', 'id' => 42]
     * @param array<string, string|int> $query  appended as a query string
     */
    public function route(string $name, array $params = [], array $query = []): string
    {
        $pattern = Router::patternFor($name);

        $path = preg_replace_callback(
            '/\{(\w+)(\.\.\.)?}/',
            static function (array $match) use ($name, $params): string {
                /** @var array{0: non-empty-string, 1: non-empty-string, 2?: '...'} $match */
                if (!\array_key_exists($match[1], $params)) {
                    throw new InvalidArgumentException(
                        "Route {$name} needs the parameter '{$match[1]}'.",
                    );
                }

                $value = (string) $params[$match[1]];

                if (($match[2] ?? '') === '...') {
                    return implode('/', array_map(rawurlencode(...), explode('/', $value)));
                }

                return rawurlencode($value);
            },
            $pattern,
        ) ?? throw new RuntimeException("Failed to build a URL for route {$name}.");

        return $this->build($path, $query);
    }

    /**
     * Joins an already-prepared path to the base, in whichever mode is active.
     *
     * The path is used as given. Callers that accept a path from outside
     * normalise it first; `route()` must not, because its segments are already
     * encoded and a value such as ".." would otherwise be read as a
     * parent-directory segment and dropped.
     *
     * @param array<string, string|int> $query
     */
    private function build(string $path, array $query): string
    {
        if ($this->mode === self::MODE_QUERY) {
            $parameters = $path === '' ? $query : [$this->queryKey => $path] + $query;

            return $this->base . ($parameters === [] ? '' : '?' . http_build_query($parameters));
        }

        $url = rtrim($this->base, '/') . '/' . $path;

        return $url . ($query === [] ? '' : '?' . http_build_query($query));
    }
}
```

Two notes on this shape, both of which cost a review round to discover:

`to()` normalises its argument and `route()` does not, which looks like an
inconsistency and is not. `Request::normalizePath()` discards `.` and `..`
segments because its job is sanitising a path that arrived from the network.
`rawurlencode()` leaves a dot untouched, so a parameter whose value is `..`
survives encoding — and if `route()` then handed its finished path to `to()`,
that parameter would be read as a parent-directory segment and silently
dropped, turning `route('page.detail', ['id' => '..'])` into `/admin/p/ads`.
A path this class assembled from validated parameters is not untrusted input
and must not be sanitised a second time.

`to('')` in path mode gives `rtrim('/admin', '/') . '/' . ''`, which is
`/admin/` — what the dashboard test expects.

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Http/UrlGeneratorTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Http/UrlGenerator.php tests/Unit/Http/UrlGeneratorTest.php
git commit -m "Add URL generation for path and query modes"
```

---

### Task 5: Session abstraction and CSRF

**Files:**
- Create: `src/Http/SessionStore.php`, `src/Http/ArraySessionStore.php`,
  `src/Http/NativeSessionStore.php`, `src/Http/Csrf.php`
- Test: `tests/Unit/Http/ArraySessionStoreTest.php`,
  `tests/Unit/Http/CsrfTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `interface RockAdmin\Http\SessionStore` with
    `get(string $key, mixed $default = null): mixed`,
    `set(string $key, mixed $value): void`, `forget(string $key): void`,
    `regenerate(): void`
  - `RockAdmin\Http\ArraySessionStore` — in-memory, used by every test and by
    the CLI
  - `RockAdmin\Http\NativeSessionStore` — native PHP sessions, the default
  - `new Csrf(SessionStore $session)`, `$csrf->token(): string`,
    `$csrf->isValid(?string $token): bool`

The interface exists because a host that manages its own sessions would be
broken by a `session_start()` call from a library (spec 5.5). Hosts substitute
their own implementation; RockAdmin never assumes it owns the session.

`NativeSessionStore` is not unit-tested — it is four lines wrapping a PHP
global, and testing it would test PHP. It is exercised end to end in the
milestone covering installation.

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\ArraySessionStore;

#[CoversClass(ArraySessionStore::class)]
final class ArraySessionStoreTest extends TestCase
{
    public function testStoresAndReturnsValues(): void
    {
        $session = new ArraySessionStore();

        $this->assertNull($session->get('missing'));
        $this->assertSame('fallback', $session->get('missing', 'fallback'));

        $session->set('user_id', 7);
        $this->assertSame(7, $session->get('user_id'));

        $session->forget('user_id');
        $this->assertNull($session->get('user_id'));
    }

    public function testRegenerateKeepsTheData(): void
    {
        $session = new ArraySessionStore();
        $session->set('user_id', 7);

        $session->regenerate();

        $this->assertSame(7, $session->get('user_id'));
    }
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\Csrf;

#[CoversClass(Csrf::class)]
final class CsrfTest extends TestCase
{
    public function testTokenIsStableWithinASession(): void
    {
        $csrf = new Csrf(new ArraySessionStore());

        $this->assertSame($csrf->token(), $csrf->token());
    }

    public function testTokenIsLongAndHexadecimal(): void
    {
        $token = (new Csrf(new ArraySessionStore()))->token();

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    public function testDifferentSessionsGetDifferentTokens(): void
    {
        $first = (new Csrf(new ArraySessionStore()))->token();
        $second = (new Csrf(new ArraySessionStore()))->token();

        $this->assertNotSame($first, $second);
    }

    public function testValidation(): void
    {
        $csrf = new Csrf(new ArraySessionStore());
        $token = $csrf->token();

        $this->assertTrue($csrf->isValid($token));
        $this->assertFalse($csrf->isValid('wrong'));
        $this->assertFalse($csrf->isValid(null));
        $this->assertFalse($csrf->isValid(''));
    }

    public function testValidationFailsWhenNoTokenWasIssued(): void
    {
        $session = new ArraySessionStore();

        $this->assertFalse((new Csrf($session))->isValid('anything'));
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Http/ArraySessionStoreTest.php tests/Unit/Http/CsrfTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write the session classes**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/**
 * Session access, kept behind an interface so RockAdmin never calls
 * session_start() in a host that manages its own sessions.
 */
interface SessionStore
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function forget(string $key): void;

    /** Issues a new session id, keeping the data. Called on privilege change. */
    public function regenerate(): void;
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/** In-memory session, for tests and for the CLI, where there is no session. */
final class ArraySessionStore implements SessionStore
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(): void
    {
        // Nothing to regenerate without a real session id.
    }
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/** The default store: native PHP sessions, started on first use. */
final class NativeSessionStore implements SessionStore
{
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();

        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->start();

        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();

        session_regenerate_id(true);
    }

    private function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
```

- [ ] **Step 4: Write `Csrf`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/**
 * One token per session, compared in constant time.
 *
 * Every POST carries it; core.js sends it in a header automatically.
 */
final class Csrf
{
    private const SESSION_KEY = '_rockadmin_csrf';

    public const HEADER = 'x-csrf-token';

    public const FIELD = '_csrf';

    public function __construct(private readonly SessionStore $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function isValid(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $expected = $this->session->get(self::SESSION_KEY);

        if (!is_string($expected) || $expected === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }
}
```

`isValid()` deliberately reads the session directly instead of calling
`token()`: calling `token()` would mint a token when none exists and then
compare against it, so a request could validate itself.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Http/ArraySessionStoreTest.php tests/Unit/Http/CsrfTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Http/SessionStore.php src/Http/ArraySessionStore.php \
        src/Http/NativeSessionStore.php src/Http/Csrf.php \
        tests/Unit/Http/ArraySessionStoreTest.php tests/Unit/Http/CsrfTest.php
git commit -m "Add the session abstraction and CSRF tokens"
```

---

### Task 6: HTTP exceptions and the error handler

**Files:**
- Create: `src/Http/HttpException.php`, `src/Http/NotFoundException.php`,
  `src/Http/ForbiddenException.php`, `src/Http/ErrorHandler.php`
- Test: `tests/Unit/Http/ErrorHandlerTest.php`

**Interfaces:**
- Consumes: `Response` from Task 2.
- Produces:
  - `RockAdmin\Http\HttpException` extending `RuntimeException`, with
    `public readonly int $status`
  - `NotFoundException` (404), `ForbiddenException` (403)
  - `new ErrorHandler(bool $debug = false)`,
    `$errors->toResponse(Throwable $e): Response`

In development the response names the exception, the message and the file and
line. In production it says nothing beyond the status, because an admin error
page is a fine place to leak a database schema (spec 5.6).

Templates arrive in milestone 4; until then the handler emits a minimal HTML
document. The milestone 4 task that introduces `error/*.php` templates
replaces the body-building method and keeps this class's public interface.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\ErrorHandler;
use RockAdmin\Http\ForbiddenException;
use RockAdmin\Http\HttpException;
use RockAdmin\Http\NotFoundException;

#[CoversClass(ErrorHandler::class)]
#[CoversClass(HttpException::class)]
#[CoversClass(NotFoundException::class)]
#[CoversClass(ForbiddenException::class)]
final class ErrorHandlerTest extends TestCase
{
    public function testHttpExceptionKeepsItsStatus(): void
    {
        $response = (new ErrorHandler())->toResponse(new NotFoundException('no such page'));

        $this->assertSame(404, $response->status);
        $this->assertSame(403, (new ErrorHandler())->toResponse(new ForbiddenException('nope'))->status);
    }

    public function testAnyOtherThrowableBecomes500(): void
    {
        $response = (new ErrorHandler())->toResponse(new \LogicException('boom'));

        $this->assertSame(500, $response->status);
    }

    public function testProductionHidesTheDetails(): void
    {
        $response = (new ErrorHandler(debug: false))
            ->toResponse(new \LogicException('SQLSTATE secret table ra_users'));

        $this->assertStringNotContainsString('secret', $response->body);
        $this->assertStringNotContainsString('LogicException', $response->body);
        $this->assertStringContainsString('500', $response->body);
    }

    public function testDebugShowsTheDetails(): void
    {
        $response = (new ErrorHandler(debug: true))->toResponse(new \LogicException('boom'));

        $this->assertStringContainsString('LogicException', $response->body);
        $this->assertStringContainsString('boom', $response->body);
        $this->assertStringContainsString(__FILE__, $response->body);
    }

    public function testDebugOutputIsEscaped(): void
    {
        $response = (new ErrorHandler(debug: true))
            ->toResponse(new \LogicException('<script>alert(1)</script>'));

        $this->assertStringNotContainsString('<script>', $response->body);
        $this->assertStringContainsString('&lt;script&gt;', $response->body);
    }

    public function testResponseIsHtml(): void
    {
        $response = (new ErrorHandler())->toResponse(new NotFoundException('x'));

        $this->assertSame('text/html; charset=utf-8', $response->headers['content-type']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Http/ErrorHandlerTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write the exceptions**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RuntimeException;
use Throwable;

/** An exception that already knows which HTTP status it should produce. */
class HttpException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use Throwable;

final class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Not found', ?Throwable $previous = null)
    {
        parent::__construct(404, $message, $previous);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use Throwable;

final class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Forbidden', ?Throwable $previous = null)
    {
        parent::__construct(403, $message, $previous);
    }
}
```

- [ ] **Step 4: Write `ErrorHandler`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use Throwable;

/**
 * Turns a throwable into a response.
 *
 * In development it says exactly what went wrong and where. In production it
 * says only the status: an error page is an easy place to leak a schema, a
 * path or a query to whoever reaches it.
 */
final class ErrorHandler
{
    public function __construct(private readonly bool $debug = false)
    {
    }

    public function toResponse(Throwable $e): Response
    {
        $status = $e instanceof HttpException ? $e->status : 500;

        return Response::html($this->body($e, $status), $status);
    }

    private function body(Throwable $e, int $status): string
    {
        $title = match ($status) {
            403 => 'Forbidden',
            404 => 'Not found',
            default => 'Something went wrong',
        };

        $detail = '';

        if ($this->debug) {
            $detail = sprintf(
                '<pre>%s: %s%sin %s:%d</pre>',
                htmlspecialchars($e::class, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'),
                PHP_EOL,
                htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8'),
                $e->getLine(),
            );
        }

        return sprintf(
            '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<title>%1$d %2$s</title></head><body class="ra-error ra-error-%1$d">'
            . '<h1>%1$d %2$s</h1>%3$s</body></html>',
            $status,
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            $detail,
        );
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Http/ErrorHandlerTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Http/HttpException.php src/Http/NotFoundException.php \
        src/Http/ForbiddenException.php src/Http/ErrorHandler.php \
        tests/Unit/Http/ErrorHandlerTest.php
git commit -m "Add HTTP exceptions and the error handler"
```

---

### Task 7: Kernel and handler registry

**Files:**
- Create: `src/Http/Handler.php`, `src/Http/HandlerRegistry.php`,
  `src/Http/Kernel.php`
- Test: `tests/Unit/Http/KernelTest.php`

**Interfaces:**
- Consumes: `Request`, `Response`, `Route`, `Router`, `ErrorHandler`,
  `NotFoundException` from Tasks 1, 2, 3 and 6.
- Produces:
  - `interface RockAdmin\Http\Handler` with
    `handle(Route $route, Request $request): Response`
  - `HandlerRegistry::register(string $routeName, Handler $handler): void`,
    `HandlerRegistry::find(string $routeName): ?Handler`
  - `new Kernel(Router $router, HandlerRegistry $handlers, ErrorHandler $errors)`,
    `$kernel->handle(Request $request): Response`

This closes the milestone: a request goes in, a response comes out, and every
later milestone registers handlers rather than touching the kernel. Steps 4
to 10 of the lifecycle in spec 5.2 — configuration, auth, workspaces,
permissions — are middleware and handlers added in later milestones; the
kernel's job is only match, dispatch and catch.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\ErrorHandler;
use RockAdmin\Http\ForbiddenException;
use RockAdmin\Http\Handler;
use RockAdmin\Http\HandlerRegistry;
use RockAdmin\Http\Kernel;
use RockAdmin\Http\Request;
use RockAdmin\Http\Response;
use RockAdmin\Http\Route;
use RockAdmin\Http\Router;

#[CoversClass(Kernel::class)]
#[CoversClass(HandlerRegistry::class)]
final class KernelTest extends TestCase
{
    private function kernel(HandlerRegistry $handlers, bool $debug = false): Kernel
    {
        return new Kernel(new Router(), $handlers, new ErrorHandler($debug));
    }

    /**
     * `callable` is not a legal property type in PHP, and an untyped property
     * fails PHPStan at level max, so the double holds a Closure.
     *
     * @param \Closure(Route, Request): Response $callback
     */
    private function handler(\Closure $callback): Handler
    {
        return new class ($callback) implements Handler {
            /** @param \Closure(Route, Request): Response $callback */
            public function __construct(private readonly \Closure $callback)
            {
            }

            public function handle(Route $route, Request $request): Response
            {
                return ($this->callback)($route, $request);
            }
        };
    }

    public function testDispatchesToTheRegisteredHandlerWithRouteParameters(): void
    {
        $handlers = new HandlerRegistry();
        $handlers->register('page.index', $this->handler(
            static fn (Route $route): Response => Response::html('page: ' . $route->param('page')),
        ));

        $response = $this->kernel($handlers)->handle(new Request('GET', 'p/ads'));

        $this->assertSame(200, $response->status);
        $this->assertSame('page: ads', $response->body);
    }

    public function testUnmatchedPathIs404(): void
    {
        $response = $this->kernel(new HandlerRegistry())->handle(new Request('GET', 'nope/at/all'));

        $this->assertSame(404, $response->status);
    }

    public function testMatchedRouteWithNoHandlerIs404(): void
    {
        $response = $this->kernel(new HandlerRegistry())->handle(new Request('GET', 'p/ads'));

        $this->assertSame(404, $response->status);
    }

    public function testHandlerExceptionsBecomeResponses(): void
    {
        $handlers = new HandlerRegistry();
        $handlers->register('page.index', $this->handler(
            static fn (): Response => throw new ForbiddenException('not yours'),
        ));

        $response = $this->kernel($handlers)->handle(new Request('GET', 'p/ads'));

        $this->assertSame(403, $response->status);
    }

    public function testUnexpectedExceptionsBecome500(): void
    {
        $handlers = new HandlerRegistry();
        $handlers->register('page.index', $this->handler(
            static fn (): Response => throw new \LogicException('boom'),
        ));

        $response = $this->kernel($handlers)->handle(new Request('GET', 'p/ads'));

        $this->assertSame(500, $response->status);
    }

    public function testRegisteringTheSameRouteTwiceReplacesTheHandler(): void
    {
        $handlers = new HandlerRegistry();
        $handlers->register('page.index', $this->handler(static fn (): Response => Response::html('first')));
        $handlers->register('page.index', $this->handler(static fn (): Response => Response::html('second')));

        $this->assertSame('second', $this->kernel($handlers)->handle(new Request('GET', 'p/ads'))->body);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Http/KernelTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write `Handler` and `HandlerRegistry`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/** Something that can answer one kind of route. */
interface Handler
{
    public function handle(Route $route, Request $request): Response;
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/**
 * Route name to handler. Later milestones register their handlers here
 * instead of modifying the kernel.
 */
final class HandlerRegistry
{
    /** @var array<string, Handler> */
    private array $handlers = [];

    public function register(string $routeName, Handler $handler): void
    {
        $this->handlers[$routeName] = $handler;
    }

    public function find(string $routeName): ?Handler
    {
        return $this->handlers[$routeName] ?? null;
    }
}
```

- [ ] **Step 4: Write `Kernel`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use Throwable;

/**
 * Match, dispatch, catch.
 *
 * The kernel knows nothing about pages, configuration or the database. That
 * keeps the one place every request passes through small enough to be
 * obviously correct.
 */
final class Kernel
{
    public function __construct(
        private readonly Router $router,
        private readonly HandlerRegistry $handlers,
        private readonly ErrorHandler $errors,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            $route = $this->router->match($request)
                ?? throw new NotFoundException("No route matches {$request->method} /{$request->path}");

            $handler = $this->handlers->find($route->name)
                ?? throw new NotFoundException("No handler registered for route {$route->name}");

            return $handler->handle($route, $request);
        } catch (Throwable $e) {
            return $this->errors->toResponse($e);
        }
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Http/KernelTest.php`
Expected: PASS.

- [ ] **Step 6: Run the full check**

Run: `composer run check`
Expected: PHP-CS-Fixer clean, PHPStan level max with no errors, every test
passing on PHP 8.4.

- [ ] **Step 7: Update the changelog**

Add under `## [Unreleased]` / `### Added`:

```markdown
- HTTP foundation: request and response value objects, the route table and
  matcher, URL generation in path and query modes, the session abstraction,
  CSRF tokens, HTTP exceptions and the kernel.
```

- [ ] **Step 8: Commit**

```bash
git add src/Http/Handler.php src/Http/HandlerRegistry.php src/Http/Kernel.php \
        tests/Unit/Http/KernelTest.php CHANGELOG.md
git commit -m "Add the kernel and handler registry"
```

---

## Milestone acceptance

The milestone is done when all of the following are true, each verified by
running the command and reading its output:

1. `composer run check` passes — coding standards, PHPStan at level max, and
   every test.
2. `composer validate --strict` passes and `composer show --tree` lists no
   runtime dependency beyond PHP extensions.
3. `vendor/bin/phpunit --testdox` reads as a description of the HTTP layer:
   route matching, URL generation in both modes, CSRF validation, error
   handling.
4. This snippet returns a `200` with the body `hello` and, with the path
   changed to `nope`, a `404`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use RockAdmin\Http\ErrorHandler;
use RockAdmin\Http\Handler;
use RockAdmin\Http\HandlerRegistry;
use RockAdmin\Http\Kernel;
use RockAdmin\Http\Request;
use RockAdmin\Http\Response;
use RockAdmin\Http\Route;
use RockAdmin\Http\Router;

$handlers = new HandlerRegistry();
$handlers->register('page.index', new class () implements Handler {
    public function handle(Route $route, Request $request): Response
    {
        return Response::html('hello');
    }
});

$kernel = new Kernel(new Router(), $handlers, new ErrorHandler(debug: true));

$ok = $kernel->handle(new Request('GET', 'p/ads'));
$missing = $kernel->handle(new Request('GET', 'nope'));

echo $ok->status, ' ', $ok->body, PHP_EOL;   // 200 hello
echo $missing->status, PHP_EOL;              // 404
```

## What this milestone deliberately leaves out

- Configuration loading, validation and the schema — milestone 2
- Anything touching a database — milestone 3
- Templates, assets and `core.js` — milestone 4; until then `ErrorHandler`
  emits a minimal HTML document
- Authentication, permissions and workspaces — milestone 5; the kernel has no
  auth gate yet, so every registered handler is reachable
- The `RockAdmin::handle()` facade shown in the README, which needs
  configuration to exist — milestone 10

## The remaining milestones

Each produces working, testable software and gets its own plan written from
the same specification, in this order:

| # | Milestone | Deliverable |
|---|---|---|
| 1 | HTTP foundation | this plan |
| 2 | Configuration | loader, schema, validator, placeholders, shared definitions, enums, cache, `validate` and `schema` commands |
| 3 | Data layer | connection, MySQL and PostgreSQL dialects, query builder, relations and path sources, pagination, counting strategies |
| 4 | View layer | renderer, template cascade, layouts, assets, `core.js`, flash messages |
| 5 | Authentication | login, users, gate, roles, workspaces, audit log |
| 6 | List region | grid, column types and displays, filters, sorting, state in the URL |
| 7 | Form region and writes | create, update, copy, delete, validation, defaults, return-to-state |
| 8 | Actions | link, open and post actions; action columns; bulk actions; region dependencies |
| 9 | Mail | drivers, templates, password reset |
| 10 | CLI and installation | `init`, `doctor`, `migrate`, `user:create`, `make:page`, cache commands, `/_setup`, `/_diagnostics`, dev console |
| 11 | Built-in pages | dashboard, profile, user management, help |
| 12 | Documentation | generated reference, written guides, first release |
