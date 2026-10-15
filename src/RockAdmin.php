<?php

declare(strict_types=1);

namespace RockAdmin;

use Closure;
use RockAdmin\Http\Request;
use RockAdmin\Http\Response;
use RockAdmin\Http\UrlGenerator;

/**
 * The one line a project writes.
 *
 * ```php
 * // Laravel
 * Route::any('/admin/{path?}', fn ($path = '') => RockAdmin::handle(
 *     $path,
 *     basePath: base_path(),
 *     pdo: fn () => DB::connection()->getPdo(),
 * )->body)->where('path', '.*');
 *
 * // a plain index.php
 * RockAdmin::serve(new Setup(basePath: __DIR__ . '/..'));
 * ```
 *
 * It is a facade over `Admin` and holds nothing else: everything that can be
 * tested, extended or run twice lives there, and what is left here is the two
 * things a static method is actually good for — reading the superglobals, and
 * being short enough to put in a README.
 *
 * **Nothing is cached between calls.** Booting twice in one process boots
 * twice, which is what a test or a queue worker wants and what a web request
 * never notices. A host that wants one instance holds an `Admin`.
 */
final class RockAdmin
{
    /**
     * Answer one request, for a host that has its own router.
     *
     * The path is the part after the mount point, which the host's router
     * already separated: Laravel's `{path?}` wildcard, or whatever the
     * framework calls it. Nothing is read from the environment except what
     * `Request::fromGlobals()` reads.
     *
     * @param (Closure(): \PDO)|null $pdo the host's connection, borrowed
     */
    public static function handle(
        string $path,
        string $basePath,
        ?Closure $pdo = null,
        ?Setup $setup = null,
    ): Response {
        $setup ??= new Setup(basePath: $basePath, pdo: $pdo);

        return Admin::boot($setup)->handle(Request::fromGlobals(trim($path, '/')));
    }

    /**
     * Answer the current request and send it, for a project with no router.
     *
     * Where the path comes from is `url_mode`'s business and therefore the
     * admin's: in `path` mode it is the URL's own path with the mount point
     * taken off, and in `query` mode it is a query parameter, because an
     * installation that cannot have a rewrite rule still has to work.
     */
    public static function serve(Setup $setup): void
    {
        $admin = Admin::boot($setup);

        $admin->handle(Request::fromGlobals(self::path($admin)))->send();
    }

    /**
     * The route this request is asking for, out of the superglobals.
     *
     * Reading `$_GET` directly rather than asking `UrlGenerator` is
     * deliberate: that class builds outgoing links, and this is decoding the
     * one incoming address. `Request` itself takes no view on how a path was
     * derived, which is exactly why this is the entry point's job.
     */
    public static function path(Admin $admin): string
    {
        if ($admin->services->urlMode === UrlGenerator::MODE_QUERY) {
            $route = $_GET['ra'] ?? '';

            return \is_string($route) ? trim($route, '/') : '';
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url(\is_string($uri) ? $uri : '/', PHP_URL_PATH);
        $path = rawurldecode(\is_string($path) ? $path : '/');

        // The mount point is not part of the route. A host that mounts the
        // admin at /admin sends us /admin/p/ads, and `p/ads` is the route;
        // stripping it here rather than in the router keeps the router's
        // table readable as the thing it is -- a list of the admin's own
        // addresses, with nothing about where it was installed.
        $base = trim($admin->services->baseUrl, '/');

        if ($base !== '' && (str_starts_with(ltrim($path, '/'), $base . '/') || trim($path, '/') === $base)) {
            $path = substr(ltrim($path, '/'), \strlen($base));
        }

        return trim($path, '/');
    }
}
