<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\UrlGenerator;

/**
 * `core.js`'s `regionRequestUrl()` combines the region's own fragment
 * address (`data-ra-region-url`, on the fragment's root element) with the
 * query string of a page-route link — a sort header, a pager link or the
 * toolbar's own action — to build the address it actually fetches. This
 * project ships no JavaScript test runner (rule 1: no runtime dependency,
 * and no build step either), so the composition core.js performs is
 * mirrored here in plain PHP against the exact URLs `UrlGenerator` produces
 * for both `url_mode`s, and proved against the one case that broke it:
 * appending a second '?' onto a fragment address that, in query mode,
 * already carries one.
 *
 * A previous version of `regionRequestUrl()` simply appended
 * '?' . queryOf(hrefOrQuery) to the fragment base. In path mode the base
 * carries no query string of its own, so that happened to work. In query
 * mode the base is itself '/admin/index.php?ra=r%2Fads%2Fgrid', and
 * appending a second '?ra=p%2Fads&sort=...' produced
 * '...?ra=r%2Fads%2Fgrid?ra=p%2Fads&sort=...' — which PHP parses as one
 * 'ra' parameter holding a literal '?' in the middle of it, matching no
 * route at all. Every sort, filter and pager action in query mode failed
 * with "Could not reach the server" as a result, and nothing in this
 * repository's test suite or its demo ever ran the admin in query mode to
 * notice.
 */
#[CoversNothing]
final class RegionRequestUrlTest extends TestCase
{
    /**
     * A line-for-line port of core.js's regionRequestUrl(), so this test
     * proves the same algorithm the shipped JavaScript runs, not a
     * reimplementation that happens to agree with it. Keep the two in sync
     * by hand; there is no shared source to generate either from.
     */
    private function regionRequestUrl(string $regionUrl, string $hrefOrQuery): string
    {
        $splitAt = strpos($hrefOrQuery, '?');
        $query = $splitAt === false ? $hrefOrQuery : substr($hrefOrQuery, $splitAt + 1);

        $baseSplitAt = strpos($regionUrl, '?');
        $baseUrl = $baseSplitAt === false ? $regionUrl : substr($regionUrl, 0, $baseSplitAt);
        parse_str($baseSplitAt === false ? '' : substr($regionUrl, $baseSplitAt + 1), $baseParams);

        parse_str($query, $stateParams);
        unset($stateParams['ra']);

        $merged = [...$baseParams, ...$stateParams];

        return $merged === [] ? $baseUrl : $baseUrl . '?' . http_build_query($merged);
    }

    public function testInPathModeTheStateQueryIsAppendedToTheFragmentAddress(): void
    {
        $urls = new UrlGenerator('/admin');

        $regionUrl = $urls->route('region', ['page' => 'ads', 'region' => 'grid']);
        $pageLink = $urls->route('page.index', ['page' => 'ads'], ['grid[sort]' => '-price']);

        $fetchUrl = $this->regionRequestUrl($regionUrl, $pageLink);

        $this->assertSame('/admin/r/ads/grid?grid%5Bsort%5D=-price', $fetchUrl);
    }

    public function testInQueryModeTheFragmentsOwnRouteSurvivesAndTheStateIsAdded(): void
    {
        $urls = new UrlGenerator('/admin/index.php', UrlGenerator::MODE_QUERY);

        $regionUrl = $urls->route('region', ['page' => 'ads', 'region' => 'grid']);
        $pageLink = $urls->route('page.index', ['page' => 'ads'], ['grid[sort]' => '-price']);

        $fetchUrl = $this->regionRequestUrl($regionUrl, $pageLink);

        // The old code produced a second '?' here -- '...ra=r%2Fads%2Fgrid?ra=p%2Fads&...' --
        // which is not even well-formed as a URL, let alone a request PHP's
        // router could match. There must be exactly one '?'.
        $this->assertSame(1, substr_count($fetchUrl, '?'));

        parse_str((string) parse_url($fetchUrl, PHP_URL_QUERY), $decoded);

        // The fragment's own route survives -- it is what makes this a
        // request for the region, not the page.
        $this->assertSame('r/ads/grid', $decoded['ra'] ?? null);

        // The page link's own 'ra' (naming the *page* route) is not carried
        // into the fragment request: the fragment already named its own
        // route, and a second 'ra' would either be ignored or, worse, win
        // and send the request to the wrong route entirely.
        $grid = $decoded['grid'] ?? null;
        $this->assertIsArray($grid);
        $this->assertSame('-price', $grid['sort'] ?? null);
    }

    public function testANoQueryFragmentAddressGetsExactlyOneQuestionMarkOnceStateIsAdded(): void
    {
        $urls = new UrlGenerator('/admin/index.php', UrlGenerator::MODE_QUERY);
        $regionUrl = $urls->route('region', ['page' => 'ads', 'region' => 'grid']);

        // No state at all -- the very first load of a region, or a page with
        // no sort, filter or search applied -- must still be exactly the
        // fragment's own address, unchanged.
        $this->assertSame($regionUrl, $this->regionRequestUrl($regionUrl, ''));
    }

    /** @return array<string, array{string}> */
    public static function bothModes(): array
    {
        return [
            'path' => [UrlGenerator::MODE_PATH],
            'query' => [UrlGenerator::MODE_QUERY],
        ];
    }

    #[DataProvider('bothModes')]
    public function testAFilterAndASearchComposeWithTheFragmentAddressInBothModes(string $mode): void
    {
        $base = $mode === UrlGenerator::MODE_QUERY ? '/admin/index.php' : '/admin';
        $urls = new UrlGenerator($base, $mode);

        $regionUrl = $urls->route('region', ['page' => 'ads', 'region' => 'grid']);
        $pageLink = $urls->route(
            'page.index',
            ['page' => 'ads'],
            ['grid[q]' => 'kolo', 'grid[f][status]' => 'active', 'grid[sort]' => '-price'],
        );

        $fetchUrl = $this->regionRequestUrl($regionUrl, $pageLink);

        $this->assertSame(1, substr_count($fetchUrl, '?'), "{$mode}: exactly one '?' in the fetch URL");

        parse_str((string) parse_url($fetchUrl, PHP_URL_QUERY), $decoded);

        $grid = $decoded['grid'] ?? null;
        $this->assertIsArray($grid);
        $this->assertSame('kolo', $grid['q'] ?? null);
        $this->assertIsArray($grid['f'] ?? null);
        $this->assertSame('active', $grid['f']['status'] ?? null);
        $this->assertSame('-price', $grid['sort'] ?? null);
    }
}
