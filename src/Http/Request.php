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
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $body
     * @param array<string, string>   $cookies
     * @param array<string, string>   $headers header names in any case
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
     *
     * Two conditions bound that guarantee, and both are load-bearing.
     *
     * It assumes the host passed an already-decoded path, and it is the first
     * thing to touch that path, so nothing may decode afterwards: an
     * un-decoded "%2E%2E" is one opaque segment here and stays one in
     * Router::matchPattern(), which is why that method never decodes either.
     *
     * A backslash is not a separator, so "..\\..\\etc" survives intact as a
     * single segment. On Windows the filesystem would read it as a traversal,
     * so a handler resolving a path against a directory must still call
     * realpath() and check the prefix rather than trusting this function.
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

    /**
     * Only HTTP_* keys become headers, so Content-Type and Content-Length —
     * which PHP exposes without that prefix — are deliberately not collected.
     */
    public static function fromGlobals(string $path): self
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_') && \is_scalar($value)) {
                $headers[str_replace('_', '-', substr($key, 5))] = (string) $value;
            }
        }

        $method = \is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';

        $cookies = [];

        foreach ($_COOKIE as $name => $value) {
            if (\is_string($value)) {
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
