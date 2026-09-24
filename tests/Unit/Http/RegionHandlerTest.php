<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Enums;
use RockAdmin\Db\Result;
use RockAdmin\Db\Sql;
use RockAdmin\Grid\CellFormatter;
use RockAdmin\Grid\ListRegion;
use RockAdmin\Grid\QueryFactory;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\NotFoundException;
use RockAdmin\Http\PageHandler;
use RockAdmin\Http\RegionHandler;
use RockAdmin\Http\Request;
use RockAdmin\Http\Route;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\FakeRowSource;
use RockAdmin\View\Assets;
use RockAdmin\View\Escaper;
use RockAdmin\View\FlashBag;
use RockAdmin\View\MenuItemView;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * `RegionHandler` renders one region and nothing else -- no shell, no
 * document -- and reads its `GridState` the same way `PageHandler` does for
 * the same region, which is the seam `testAPageRequestAndARegionRequestAgreeOnTheSameRows()`
 * pins.
 */
#[CoversClass(RegionHandler::class)]
final class RegionHandlerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-region-handler-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    private function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $this->remove($path . '/' . $entry);
        }

        rmdir($path);
    }

    private function writePage(string $name, string $body): void
    {
        file_put_contents($this->root . '/pages/' . $name . '.php', "<?php\n\nreturn {$body};\n");
    }

    private function writeAdsPage(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ra_test_ads'],
                'regions' => ['grid' => ['type' => 'list', 'per_page' => 2, 'columns' => [
                    'id' => ['type' => 'int'],
                    'title' => [],
                ]]],
            ]
            PHP);
    }

    private function repository(): PageRepository
    {
        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );
    }

    /** @param list<Result> $results */
    private function region(array $results): ListRegion
    {
        return new ListRegion(new FakeRowSource($results), new QueryFactory(), new CellFormatter(), new UrlGenerator('/'));
    }

    private function renderer(): Renderer
    {
        return new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/'));
    }

    private function fetchResult(int $page): Result
    {
        // Two totally distinct pages of data, so a test can tell which one a
        // given request actually reached.
        return $page === 1
            ? new Result([['id' => 1, 'title' => 'Horské kolo']], 3, [new Sql('SELECT 1')])
            : new Result([['id' => 2, 'title' => 'Silniční kolo']], 3, [new Sql('SELECT 1')]);
    }

    public function testARegionRequestReturnsTheRegionAndNoDocument(): void
    {
        $this->writeAdsPage();
        $handler = new RegionHandler($this->repository(), $this->region([$this->fetchResult(1)]), $this->renderer());

        $response = $handler->handle(new Route('region', ['page' => 'ads', 'region' => 'grid']), new Request('GET', 'r/ads/grid'));

        $this->assertSame(200, $response->status);
        $this->assertStringNotContainsString('<!doctype html>', $response->body);
        $this->assertStringNotContainsString('<nav class="ra-navbar', $response->body);
        $this->assertStringContainsString('Horské kolo', $response->body);
    }

    public function testAFragmentCarriesItsRegionKeyOnItsRoot(): void
    {
        $this->writeAdsPage();
        $handler = new RegionHandler($this->repository(), $this->region([$this->fetchResult(1)]), $this->renderer());

        $response = $handler->handle(new Route('region', ['page' => 'ads', 'region' => 'grid']), new Request('GET', 'r/ads/grid'));

        $this->assertMatchesRegularExpression(
            '/^<div class="[^"]*" data-ra-region="grid" data-ra-region-url="[^"]*">/',
            trim($response->body),
        );
    }

    public function testAnUnknownRegionIsNotFound(): void
    {
        $this->writeAdsPage();
        $handler = new RegionHandler($this->repository(), $this->region([$this->fetchResult(1)]), $this->renderer());

        $this->expectException(NotFoundException::class);

        $handler->handle(new Route('region', ['page' => 'ads', 'region' => 'nope']), new Request('GET', 'r/ads/nope'));
    }

    public function testAnUnknownPageIsNotFound(): void
    {
        $handler = new RegionHandler($this->repository(), $this->region([$this->fetchResult(1)]), $this->renderer());

        $this->expectException(NotFoundException::class);

        $handler->handle(new Route('region', ['page' => 'nope', 'region' => 'grid']), new Request('GET', 'r/nope/grid'));
    }

    public function testARegionRequestReadsTheStateFromItsOwnNamespace(): void
    {
        $this->writeAdsPage();

        // per_page is 2 and the fixture total is 3, so page 2 is in range and
        // the region needs exactly one RowSource::fetch() call -- one result
        // queued is what a real page 2 request would actually reach.
        $handler = new RegionHandler(
            $this->repository(),
            $this->region([$this->fetchResult(2)]),
            $this->renderer(),
        );

        $response = $handler->handle(
            new Route('region', ['page' => 'ads', 'region' => 'grid']),
            new Request('GET', 'r/ads/grid', ['grid' => ['page' => '2']]),
        );

        $this->assertStringContainsString('Silniční kolo', $response->body);
        $this->assertStringNotContainsString('Horské kolo', $response->body);
    }

    public function testAPageRequestAndARegionRequestAgreeOnTheSameRows(): void
    {
        // The seam test: the same page 2, reached through the full document
        // and through its own fragment, must show the same row -- two paths
        // into one region is exactly the shape that drifts.
        $this->writeAdsPage();

        $pageHandler = new PageHandler(
            $this->repository(),
            $this->region([$this->fetchResult(2)]),
            $this->renderer(),
            new Assets(new UrlGenerator('/')),
            new FlashBag(new ArraySessionStore()),
            'RockAdmin Demo',
            'auto',
            [new MenuItemView('Ads', '/p/ads', key: 'ads')],
        );
        $regionHandler = new RegionHandler(
            $this->repository(),
            $this->region([$this->fetchResult(2)]),
            $this->renderer(),
        );

        $query = ['grid' => ['page' => '2']];

        $pageResponse = $pageHandler->handle(
            new Route('page.index', ['page' => 'ads']),
            new Request('GET', 'p/ads', $query),
        );
        $regionResponse = $regionHandler->handle(
            new Route('region', ['page' => 'ads', 'region' => 'grid']),
            new Request('GET', 'r/ads/grid', $query),
        );

        $this->assertStringContainsString('Silniční kolo', $pageResponse->body);
        $this->assertStringContainsString('Silniční kolo', $regionResponse->body);
        $this->assertStringNotContainsString('Horské kolo', $pageResponse->body);
        $this->assertStringNotContainsString('Horské kolo', $regionResponse->body);
    }
}
