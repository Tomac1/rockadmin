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
