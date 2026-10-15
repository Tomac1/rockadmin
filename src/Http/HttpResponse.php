<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/**
 * What came back from somewhere else's server.
 *
 * Not `Response`: that one is what this admin sends to a browser, and the two
 * have nothing in common but a status code. Putting them in one class would
 * mean a value that is sometimes a view and sometimes a download.
 */
final class HttpResponse
{
    /** @param array<string, string> $headers lower-cased names */
    public function __construct(
        public readonly int $status,
        public readonly string $body = '',
        public readonly array $headers = [],
    ) {
    }

    public function succeeded(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
