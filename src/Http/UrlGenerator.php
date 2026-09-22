<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use InvalidArgumentException;
use RuntimeException;

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
