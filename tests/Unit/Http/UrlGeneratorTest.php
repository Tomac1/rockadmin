<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\ActionReference;
use RockAdmin\Config\PageReference;
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

        // Encoding happens once, per segment, in build(). Everything that is
        // not a separator survives as a character, whatever it is — see
        // testASlashInANonWildcardParameterIsRefused() for the one value that
        // cannot, because a slash becomes a separator again the moment the
        // host decodes the path.
        $this->assertSame(
            '/admin/p/ads/a%20b%3Fc%23d',
            $urls->route('page.detail', ['page' => 'ads', 'id' => 'a b?c#d']),
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

    public function testASchemeRelativeBaseIsRefused(): void
    {
        // A base of "//host/admin" makes every link this generator builds
        // scheme-relative, which Escaper::url() then refuses as an open
        // redirect — so a page built on top of one would throw on every
        // link, including the error page's own, rather than failing once
        // here where the misconfiguration is.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('//evil.com/admin');

        new UrlGenerator('//evil.com/admin');
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

    public function testASlashInANonWildcardParameterIsRefused(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Route page.detail cannot use a value containing '/' as the parameter 'id'");

        $urls->route('page.detail', ['page' => 'ads', 'id' => 'a/b']);
    }

    public function testTheWildcardParameterStillCarriesSlashes(): void
    {
        $urls = new UrlGenerator('/admin');

        // The refusal above is about a slash appearing where one segment was
        // expected. A wildcard is the one place slashes are structural.
        $this->assertSame('/admin/_assets/css/core.css', $urls->route('assets', ['path' => 'css/core.css']));
    }

    public function testToStripsDotSegmentsFromAHandWrittenPath(): void
    {
        $urls = new UrlGenerator('/admin');

        // Dot segments are discarded, not resolved: if they were resolved this
        // would be /admin/detail. Compare testDotSegmentsInParametersAreRefused(),
        // where route() refuses a ".." parameter outright.
        $this->assertSame('/admin/p/ads/detail', $urls->to('p/ads/../.././detail'));
    }

    public function testAPageReferenceBecomesThePageUrl(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->assertSame('/admin/p/users', $urls->reference(new PageReference('users')));
        $this->assertSame('/admin/p/users/create', $urls->reference(new PageReference('users', 'create')));
        $this->assertSame('/admin/p/users/42/edit', $urls->reference(new PageReference('users', '42/edit')));
    }

    public function testAnActionReferenceBecomesTheActionUrlOfTheCurrentPage(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->assertSame('/admin/a/ads/archive', $urls->reference(new ActionReference('archive'), 'ads'));
    }

    public function testAnActionReferenceWithoutACurrentPageIsRefused(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('@action:archive is addressed on the current page');

        $urls->reference(new ActionReference('archive'));
    }

    public function testAReferenceStillObeysQueryMode(): void
    {
        $urls = new UrlGenerator('/admin/index.php', 'query');

        $this->assertSame(
            '/admin/index.php?ra=p%2Fusers%2F42%2Fedit',
            $urls->reference(new PageReference('users', '42/edit')),
        );
    }

    public function testAReferenceTakesAQueryString(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->assertSame(
            '/admin/p/users/42/edit?return=p%2Fusers',
            $urls->reference(new PageReference('users', '42/edit'), null, ['return' => 'p/users']),
        );
    }

    public function testAnUnsubstitutedRowPlaceholderIsRefusedRatherThanEncoded(): void
    {
        $urls = new UrlGenerator('/admin');

        // Encoding it would produce /admin/p/users/%7Bid%7D/edit: a 404 whose
        // cause is a missing substitution three layers away.
        try {
            $urls->reference(new PageReference('users', '{id}/edit'));
            $this->fail('Expected an unsubstituted row placeholder to be refused.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('{id}', $e->getMessage());
            $this->assertStringContainsString('@page:users/{id}/edit', $e->getMessage());
            $this->assertStringContainsString('substituted per row', $e->getMessage());
        }
    }
}
