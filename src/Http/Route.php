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
