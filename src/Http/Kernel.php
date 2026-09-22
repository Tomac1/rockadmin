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
