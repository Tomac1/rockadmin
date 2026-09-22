<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\Request;
use RockAdmin\Http\Router;
use RockAdmin\Http\UrlGenerator;

#[CoversClass(UrlGenerator::class)]
final class UrlGeneratorTest extends TestCase
{
    public function testPathMode(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->assertSame('/admin/', $urls->to(''));
        $this->assertSame('/admin/p/ads', $urls->to('p/ads'));
        $this->assertSame('/admin/p/ads?page=3', $urls->to('p/ads', ['page' => 3]));
    }

    public function testQueryMode(): void
    {
        $urls = new UrlGenerator('/admin/index.php', 'query');

        $this->assertSame('/admin/index.php', $urls->to(''));
        $this->assertSame('/admin/index.php?ra=p%2Fads', $urls->to('p/ads'));
        $this->assertSame('/admin/index.php?ra=p%2Fads&page=3', $urls->to('p/ads', ['page' => 3]));
    }

    public function testRouteSubstitutesParameters(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->assertSame(
            '/admin/p/ads/42/edit',
            $urls->route('page.edit', ['page' => 'ads', 'id' => 42]),
        );
        $this->assertSame(
            '/admin/r/users/detail?id=7',
            $urls->route('region', ['page' => 'users', 'region' => 'detail'], ['id' => 7]),
        );
    }

    public function testRouteEncodesParameters(): void
    {
        $urls = new UrlGenerator('/admin');

        // Encoding happens once, per segment, in build(). A slash inside a
        // non-wildcard value is a separator, not a character: the previous
        // %2F was cosmetic, because a host decodes it back to a separator
        // before the router ever sees it. Both forms reach the router as
        // "p/ads/a/b c", which matches no route — see roundTripValues(),
        // where "a/b" is absent for exactly this reason.
        $this->assertSame(
            '/admin/p/ads/a/b%20c',
            $urls->route('page.detail', ['page' => 'ads', 'id' => 'a/b c']),
        );
        $this->assertSame(
            '/admin/p/ads/u%C5%BEivatel%C3%A9',
            $urls->route('page.detail', ['page' => 'ads', 'id' => 'uživatelé']),
        );
    }

    public function testWildcardParameterKeepsItsSlashes(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->assertSame('/admin/_assets/css/core.css', $urls->route('assets', ['path' => 'css/core.css']));
    }

    public function testRouteWithAMissingParameterThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('id');

        (new UrlGenerator('/admin'))->route('page.edit', ['page' => 'ads']);
    }

    public function testUnknownModeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UrlGenerator('/admin', 'magic');
    }

    /** @return array<string, array{string}> */
    public static function roundTripValues(): array
    {
        return [
            'plain' => ['ads'],
            'accented' => ['uživatelé'],
            'spaced' => ['a b'],
            'plus' => ['a+b'],
            'percent' => ['100%'],
            'hash' => ['a#b'],
        ];
    }

    #[DataProvider('roundTripValues')]
    public function testPathModeLinksRoundTripThroughTheRouter(string $id): void
    {
        $link = (new UrlGenerator('/admin'))->route('page.detail', ['page' => 'ads', 'id' => $id]);

        // A web server hands PHP the decoded path, with the mount prefix removed.
        $path = rawurldecode(substr($link, \strlen('/admin/')));
        $route = (new Router())->match(new Request('GET', $path));

        $this->assertNotNull($route);
        $this->assertSame($id, $route->param('id'));
    }

    #[DataProvider('roundTripValues')]
    public function testQueryModeLinksRoundTripThroughTheRouter(string $id): void
    {
        $link = (new UrlGenerator('/admin/index.php', 'query'))
            ->route('page.detail', ['page' => 'ads', 'id' => $id]);

        // PHP decodes the query string into $_GET before we ever see it.
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        $this->assertIsString($query['ra'] ?? null);

        $route = (new Router())->match(new Request('GET', $query['ra']));

        $this->assertNotNull($route);
        $this->assertSame($id, $route->param('id'));
    }

    public function testDotSegmentsInParametersAreRefused(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Route page.detail cannot use '..' as the parameter 'id'");

        $urls->route('page.detail', ['page' => 'ads', 'id' => '..']);
    }

    public function testASingleDotParameterIsRefusedToo(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Route page.detail cannot use '.' as the parameter 'id'");

        $urls->route('page.detail', ['page' => 'ads', 'id' => '.']);
    }

    public function testDotSegmentsInAWildcardParameterAreRefused(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Route assets cannot use '..' as the parameter 'path'");

        $urls->route('assets', ['path' => 'css/../../etc/passwd']);
    }

    public function testToStripsDotSegmentsFromAHandWrittenPath(): void
    {
        $urls = new UrlGenerator('/admin');

        // Dot segments are discarded, not resolved: if they were resolved this
        // would be /admin/detail. Compare testDotSegmentsInParametersAreRefused(),
        // where route() refuses a ".." parameter outright.
        $this->assertSame('/admin/p/ads/detail', $urls->to('p/ads/../.././detail'));
    }
}
