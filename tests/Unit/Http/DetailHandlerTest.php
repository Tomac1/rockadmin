<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Enums;
use RockAdmin\Db\Result;
use RockAdmin\Db\Sql;
use RockAdmin\Grid\CellFormatter;
use RockAdmin\Grid\PreviewRegion;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\DetailHandler;
use RockAdmin\Http\NotFoundException;
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
 * `DetailHandler` renders the whole document for `GET /p/{page}/{id}`: the
 * shell, a header, and the page's preview region rendered for the one row
 * the URL names. What actually assembled the row into a `PreviewView` is
 * `PreviewRegion`'s own job -- these tests fix its `RowSource` and assert
 * on the document `DetailHandler` builds around it, and on the one thing
 * only this class decides: a row that names nothing is a 404.
 */
#[CoversClass(DetailHandler::class)]
final class DetailHandlerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-detail-handler-' . bin2hex(random_bytes(6));
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

    private function writeAdsPageWithPreview(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ra_test_ads'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => [
                        'id' => ['type' => 'int'],
                        'title' => [],
                    ]],
                    'preview' => ['type' => 'preview', 'fields' => ['title']],
                ],
            ]
            PHP);
    }

    private function writeAdsPageWithNoPreview(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ra_test_ads'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['title' => []]],
                ],
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
    private function region(array $results): PreviewRegion
    {
        return new PreviewRegion(new FakeRowSource($results), new CellFormatter());
    }

    private function renderer(): Renderer
    {
        return new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/'));
    }

    private function fetchResult(): Result
    {
        return new Result([['id' => 1, 'title' => 'Horské kolo']], null, [new Sql('SELECT 1')]);
    }

    private function handler(PreviewRegion $region): DetailHandler
    {
        return new DetailHandler(
            $this->repository(),
            $region,
            $this->renderer(),
            new Assets(new UrlGenerator('/')),
            new FlashBag(new ArraySessionStore()),
            'RockAdmin Demo',
            'auto',
            [new MenuItemView('Ads', '/p/ads', key: 'ads')],
        );
    }

    public function testARowRendersAsAWholeDocument(): void
    {
        $this->writeAdsPageWithPreview();
        $handler = $this->handler($this->region([$this->fetchResult()]));

        $response = $handler->handle(new Route('page.detail', ['page' => 'ads', 'id' => '1']), new Request('GET', 'p/ads/1'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('<!doctype html>', $response->body);
        $this->assertStringContainsString('data-ra-region="preview"', $response->body);
        $this->assertStringContainsString('Horské kolo', $response->body);
    }

    public function testTheDocumentsTitleIsTheRowsOwnLabel(): void
    {
        $this->writeAdsPageWithPreview();
        $handler = $this->handler($this->region([$this->fetchResult()]));

        $response = $handler->handle(new Route('page.detail', ['page' => 'ads', 'id' => '1']), new Request('GET', 'p/ads/1'));

        $this->assertStringContainsString('<h2 class="ra-preview-title">Horské kolo</h2>', $response->body);
    }

    public function testAMissingRowIsNotFound(): void
    {
        $this->writeAdsPageWithPreview();
        $handler = $this->handler($this->region([new Result([], null, [new Sql('SELECT 1')])]));

        $this->expectException(NotFoundException::class);

        $handler->handle(new Route('page.detail', ['page' => 'ads', 'id' => '999999']), new Request('GET', 'p/ads/999999'));
    }

    public function testAnUnknownPageIsNotFound(): void
    {
        $handler = $this->handler($this->region([$this->fetchResult()]));

        $this->expectException(NotFoundException::class);

        $handler->handle(new Route('page.detail', ['page' => 'nope', 'id' => '1']), new Request('GET', 'p/nope/1'));
    }

    public function testAPageWithNoPreviewRegionIsNotFound(): void
    {
        $this->writeAdsPageWithNoPreview();
        $handler = $this->handler($this->region([$this->fetchResult()]));

        $this->expectException(NotFoundException::class);

        $handler->handle(new Route('page.detail', ['page' => 'ads', 'id' => '1']), new Request('GET', 'p/ads/1'));
    }
}
