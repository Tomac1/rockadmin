<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use InvalidArgumentException;

/**
 * The route table. Fixed, ordered, and small enough to read in one screen.
 *
 * Prefixes carry the meaning: /p/ renders a page, /r/ renders one region as a
 * fragment, /a/ changes data. Anything that changes data is POST only, so no
 * link scanner, mail proxy or browser prefetch can trigger it. See the design
 * spec, section 5.1.
 *
 * match() is an instance method because matching may gain per-instance state
 * later; patternFor() is a pure lookup over the same constant table, static
 * because UrlGenerator needs it without holding a router.
 *
 * @phpstan-type RouteDefinition array{0: string, 1: string, 2: string}
 */
final class Router
{
    /**
     * Order matters: the first match wins, so literal segments come before
     * placeholders that would also accept them.
     *
     * @var list<RouteDefinition>
     */
    private const ROUTES = [
        ['GET',  '',                       'dashboard'],
        ['GET',  'login',                  'login.form'],
        ['POST', 'login',                  'login.submit'],
        ['POST', 'logout',                 'logout'],
        ['GET',  'password/reset',         'password.request.form'],
        ['POST', 'password/reset',         'password.request.submit'],
        ['GET',  'password/reset/{token}', 'password.reset.form'],
        ['POST', 'password/reset/{token}', 'password.reset.submit'],
        ['GET',  'p/{page}',               'page.index'],
        ['GET',  'p/{page}/create',        'page.create'],
        ['GET',  'p/{page}/{id}',          'page.detail'],
        ['GET',  'p/{page}/{id}/edit',     'page.edit'],
        ['GET',  'p/{page}/{id}/copy',     'page.copy'],
        ['GET',  'r/{page}/{region}',      'region'],
        ['POST', 'a/{page}/{action}',      'action'],
        ['POST', 'w/{workspace}',          'workspace.switch'],
        ['GET',  '_assets/{path...}',      'assets'],
        ['GET',  '_diagnostics',           'diagnostics'],
        ['GET',  '_setup',                 'setup.form'],
        ['POST', '_setup',                 'setup.submit'],
    ];

    public function match(Request $request): ?Route
    {
        $segments = $request->path === '' ? [] : explode('/', $request->path);

        foreach (self::ROUTES as [$method, $pattern, $name]) {
            if ($method !== $request->method) {
                continue;
            }

            $params = self::matchPattern($pattern, $segments);

            if ($params !== null) {
                return new Route($name, $params);
            }
        }

        return null;
    }

    /** Used by UrlGenerator to build a link from a route name. */
    public static function patternFor(string $name): string
    {
        foreach (self::ROUTES as [, $pattern, $routeName]) {
            if ($routeName === $name) {
                return $pattern;
            }
        }

        throw new InvalidArgumentException("Unknown route name: {$name}.");
    }

    /**
     * Matches one pattern against already-decoded segments.
     *
     * Invariant: segments arrive decoded by the host and are consumed exactly
     * as given. Never decode here. Request::normalizePath() runs before
     * anything could decode a path, so it is the only place ".." is dropped;
     * decoding a segment afterwards would turn a surviving "%2E%2E" into ".."
     * behind that guard and reopen traversal on the _assets/{path...} route.
     *
     * @param  list<string>               $segments
     * @return array<string, string>|null null when the pattern does not apply
     */
    private static function matchPattern(string $pattern, array $segments): ?array
    {
        $parts = $pattern === '' ? [] : explode('/', $pattern);
        $params = [];

        foreach ($parts as $index => $part) {
            if (str_starts_with($part, '{') && str_ends_with($part, '...}')) {
                $rest = \array_slice($segments, $index);

                if ($rest === []) {
                    return null;
                }

                $params[substr($part, 1, -4)] = implode('/', $rest);

                return $params;
            }

            if (!isset($segments[$index])) {
                return null;
            }

            if (str_starts_with($part, '{')) {
                $params[trim($part, '{}')] = $segments[$index];

                continue;
            }

            if ($segments[$index] !== $part) {
                return null;
            }
        }

        return \count($segments) === \count($parts) ? $params : null;
    }
}
