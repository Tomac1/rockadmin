<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/**
 * Route name to handler.
 *
 * This is where route handlers attach, so a later milestone adding a route
 * registers it here. Cross-cutting lifecycle steps are a different matter:
 * bootstrap, session identity, the auth gate, workspace resolution and the
 * permission check run for every route and belong in Kernel::handle(), not
 * here. See the design spec, section 5.2.
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
