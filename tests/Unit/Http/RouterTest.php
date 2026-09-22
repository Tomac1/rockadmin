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
    public static function matchingCases(): array
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
    #[DataProvider('matchingCases')]
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
