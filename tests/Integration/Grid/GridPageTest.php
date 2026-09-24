<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Config\Enums;
use RockAdmin\Db\Connection;
use RockAdmin\Db\SqlRowSource;
use RockAdmin\Grid\CellFormatter;
use RockAdmin\Grid\ListRegion;
use RockAdmin\Grid\QueryFactory;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\PageHandler;
use RockAdmin\Http\RegionHandler;
use RockAdmin\Http\Request;
use RockAdmin\Http\Route;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\CountingRowSource;
use RockAdmin\Tests\Support\DatabaseTestCase;
use RockAdmin\View\Assets;
use RockAdmin\View\Escaper;
use RockAdmin\View\FlashBag;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * The whole stack: a page file on disk, a real database, real templates.
 *
 * Everything below `PageHandler` and `RegionHandler` has its own unit and
 * integration tests already; this is the one place that runs them together
 * the way an actual request does, on both configured drivers, and checks the
 * thing a unit test cannot: that the HTML a browser would receive actually
 * carries the joined column, the filtered rows, and no more queries than
 * `ListRegionTest` already proved a page needs.
 */
#[CoversClass(PageHandler::class)]
#[CoversClass(RegionHandler::class)]
final class GridPageTest extends DatabaseTestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-grid-page-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);

        file_put_contents($this->root . '/pages/ads.php', <<<'PHP'
            <?php

            return [
                'title' => 'Ads',
                'header' => ['description' => 'Every ad, past and present.'],
                'entity' => [
                    'table' => 'ra_test_ads',
                    'relations' => [
                        'user' => ['table' => 'ra_test_users', 'on' => 'ra_test_users.id = ra_test_ads.user_id'],
                    ],
                ],
                'regions' => [
                    'grid' => [
                        'type' => 'list',
                        'per_page' => 20,
                        'sort' => ['id' => 'asc'],
                        'search' => ['placeholder' => 'Search ads...'],
                        'columns' => [
                            'id' => ['type' => 'int', 'link' => true],
                            'title' => ['searchable' => true, 'filter' => ['type' => 'text']],
                            'user_name' => ['label' => 'Seller', 'source' => 'user.name'],
                            'price' => ['type' => 'money', 'currency' => 'CZK', 'sortable' => true],
                            'tags' => [
                                'type' => 'json',
                                'collection' => ['table' => 'ra_test_tags', 'foreign_key' => 'ad_id', 'column' => 'label'],
                            ],
                        ],
                    ],
                ],
            ];

            PHP);
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

    private function repository(): PageRepository
    {
        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );
    }

    private function renderer(): Renderer
    {
        return new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/admin'));
    }

    #[DataProvider('connections')]
    public function testAGridRendersItsJoinedColumnWithAFilterAppliedAndNoExtraStatements(
        ?Connection $connection,
    ): void {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $rows = new CountingRowSource(new SqlRowSource($connection));
        $listRegion = new ListRegion($rows, new QueryFactory(), new CellFormatter(), new UrlGenerator('/admin'));
        $pageHandler = new PageHandler(
            $this->repository(),
            $listRegion,
            $this->renderer(),
            new Assets(new UrlGenerator('/admin')),
            new FlashBag(new ArraySessionStore()),
            'RockAdmin',
            'auto',
        );

        // 'kolo' matches the two bicycles, not the scooter.
        $response = $pageHandler->handle(
            new Route('page.index', ['page' => 'ads']),
            new Request('GET', 'p/ads', ['grid' => ['q' => 'kolo']]),
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Horské kolo', $response->body, 'a filtered-in row');
        $this->assertStringContainsString('Silniční kolo', $response->body, 'a filtered-in row');
        $this->assertStringNotContainsString('Skútr', $response->body, 'filtered out by the search');
        $this->assertStringContainsString('Jana', $response->body, 'the joined column');
        $this->assertStringContainsString('bazar', $response->body, 'the one-to-many collection');

        $this->assertSame(1, $rows->calls, 'one RowSource::fetch() call for the whole page');
        $this->assertNotNull($rows->lastResult);
        $this->assertCount(
            3,
            $rows->lastResult->statements,
            'one for the rows, one for the count, one for the tags collection -- the same as ListRegionTest',
        );

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAPageRequestAndARegionRequestAgreeOnTheSameRowsOverARealDatabase(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $listRegionForPage = new ListRegion(
            new SqlRowSource($connection),
            new QueryFactory(),
            new CellFormatter(),
            new UrlGenerator('/admin'),
        );
        $listRegionForFragment = new ListRegion(
            new SqlRowSource($connection),
            new QueryFactory(),
            new CellFormatter(),
            new UrlGenerator('/admin'),
        );

        $pageHandler = new PageHandler(
            $this->repository(),
            $listRegionForPage,
            $this->renderer(),
            new Assets(new UrlGenerator('/admin')),
            new FlashBag(new ArraySessionStore()),
            'RockAdmin',
            'auto',
        );
        $regionHandler = new RegionHandler($this->repository(), $listRegionForFragment, $this->renderer());

        $query = ['grid' => ['sort' => '-price']];

        $pageResponse = $pageHandler->handle(
            new Route('page.index', ['page' => 'ads']),
            new Request('GET', 'p/ads', $query),
        );
        $regionResponse = $regionHandler->handle(
            new Route('region', ['page' => 'ads', 'region' => 'grid']),
            new Request('GET', 'r/ads/grid', $query),
        );

        // Sorted by price descending, the most expensive ad ('Skútr', 30000)
        // comes before the cheapest ('Horské kolo', 12000) in both -- the
        // same order, from the same state, reached two different ways.
        $skutrInPage = strpos($pageResponse->body, 'Skútr');
        $bikeInPage = strpos($pageResponse->body, 'Horské kolo');
        $skutrInRegion = strpos($regionResponse->body, 'Skútr');
        $bikeInRegion = strpos($regionResponse->body, 'Horské kolo');

        $this->assertNotFalse($skutrInPage);
        $this->assertNotFalse($bikeInPage);
        $this->assertNotFalse($skutrInRegion);
        $this->assertNotFalse($bikeInRegion);
        $this->assertLessThan($bikeInPage, $skutrInPage);
        $this->assertLessThan($bikeInRegion, $skutrInRegion);

        $this->dropFixtures($connection);
    }
}
