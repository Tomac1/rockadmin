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
