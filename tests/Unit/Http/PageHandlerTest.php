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
 * `PageHandler` renders the whole document: the shell, the page header from
 * the page's own `header` block, and each of its list regions rendered into
 * the layout's main slot. What ran the region itself is `ListRegion`'s own
 * job -- these tests fix its `RowSource` and assert on the document
 * `PageHandler` assembled around it.
 */
#[CoversClass(PageHandler::class)]
final class PageHandlerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-page-handler-' . bin2hex(random_bytes(6));
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

    private function writeAdsPage(string $description = ''): void
    {
        $header = $description === '' ? '' : "'header' => ['description' => '{$description}'],";

        $this->writePage('ads', <<<PHP
            [
                'title' => 'Ads',
                {$header}
                'entity' => ['table' => 'ra_test_ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
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

    private function region(FakeRowSource $rows): ListRegion
    {
        return new ListRegion($rows, new QueryFactory(), new CellFormatter(), new UrlGenerator('/'));
    }

    private function renderer(): Renderer
    {
        return new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/'));
    }

    private function fetchResult(): Result
    {
        return new Result([
            ['id' => 1, 'title' => 'Horské kolo'],
            ['id' => 2, 'title' => 'Silniční kolo'],
        ], 2, [new Sql('SELECT 1')]);
    }

    private function handler(FakeRowSource $rows): PageHandler
    {
        return new PageHandler(
            $this->repository(),
            $this->region($rows),
            $this->renderer(),
            new Assets(new UrlGenerator('/')),
            new FlashBag(new ArraySessionStore()),
            'RockAdmin Demo',
            'auto',
            [new MenuItemView('Ads', '/p/ads', key: 'ads')],
        );
    }

    public function testAPageRendersADocumentWithItsRegionInIt(): void
    {
        $this->writeAdsPage();
        $rows = new FakeRowSource([$this->fetchResult()]);

        $response = $this->handler($rows)->handle(new Route('page.index', ['page' => 'ads']), new Request('GET', 'p/ads'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('<!doctype html>', $response->body);
        $this->assertStringContainsString('data-ra-region="grid"', $response->body);
        $this->assertStringContainsString('Horské kolo', $response->body);
    }

    public function testAnUnknownPageIsNotFound(): void
    {
        $rows = new FakeRowSource([$this->fetchResult()]);

        $this->expectException(NotFoundException::class);

        $this->handler($rows)->handle(new Route('page.index', ['page' => 'nope']), new Request('GET', 'p/nope'));
    }

    public function testTheHeaderDescriptionComesFromThePage(): void
    {
        $this->writeAdsPage('Every ad, past and present.');
        $rows = new FakeRowSource([$this->fetchResult()]);

        $response = $this->handler($rows)->handle(new Route('page.index', ['page' => 'ads']), new Request('GET', 'p/ads'));

        $this->assertStringContainsString('Every ad, past and present.', $response->body);
    }
}
