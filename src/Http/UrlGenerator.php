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
     * Builds a link from a route name and its parameters.
     *
     * Values are substituted raw: percent-encoding belongs to build(), which
     * knows the mode and therefore knows how many layers of encoding the host
     * will strip again. Encoding here would be undone once in path mode and
     * only once out of two layers in query mode, so the same link would yield
     * two different parameters depending on a configuration key.
     *
     * A value of "." or ".." is refused rather than emitted. Request::normalizePath()
     * drops those segments from every incoming path — that is the guard keeping
     * the asset route inside its directory — so such a value can never survive
     * the trip back. Building the link anyway would produce one that resolves
     * elsewhere; failing here names the route and the parameter instead.
     *
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
                $isWildcard = ($match[2] ?? '') === '...';

                if (!$isWildcard && str_contains($value, '/')) {
                    throw new InvalidArgumentException(
                        "Route {$name} cannot use a value containing '/' as the parameter "
                        . "'{$match[1]}': a slash is a separator again the moment the host "
                        . 'decodes the path, so the link would resolve to a different route. '
                        . 'Only a {…...} wildcard may span segments.',
                    );
                }

                $segments = $isWildcard ? explode('/', $value) : [$value];

                foreach ($segments as $segment) {
                    if ($segment === '.' || $segment === '..') {
                        throw new InvalidArgumentException(
                            "Route {$name} cannot use '{$segment}' as the parameter '{$match[1]}': "
                            . 'dot segments are dropped from every incoming path, '
                            . 'so the link would resolve elsewhere.',
                        );
                    }
                }

                return $value;
            },
            $pattern,
        ) ?? throw new RuntimeException("Failed to build a URL for route {$name}.");

        return $this->build($path, $query);
    }

    /**
     * Joins a raw, unencoded path to the base and encodes it exactly once.
     *
     * The host decodes a path exactly once — PHP decodes $_GET before anyone
     * sees it, a web server decodes PATH_INFO — so each mode must apply one
     * layer and no more. In path mode that layer is rawurlencode() per
     * segment; in query mode it is http_build_query(), which encodes the whole
     * path itself. Encoding before this point would leave a layer behind that
     * survives into the router.
     *
     * The path is otherwise used as given: build() never normalises. Callers
     * that accept a path from outside normalise it first, and `route()` has
     * already refused the values a normalisation would drop.
     *
     * @param array<string, string|int> $query
     */
    private function build(string $path, array $query): string
    {
        if ($this->mode === self::MODE_QUERY) {
            $parameters = $path === '' ? $query : [$this->queryKey => $path] + $query;

            return $this->base . ($parameters === [] ? '' : '?' . http_build_query($parameters));
        }

        $encoded = implode('/', array_map(rawurlencode(...), explode('/', $path)));

        $url = rtrim($this->base, '/') . '/' . $encoded;

        return $url . ($query === [] ? '' : '?' . http_build_query($query));
    }
}
