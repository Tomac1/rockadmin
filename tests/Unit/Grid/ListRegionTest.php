<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Db\Collection;
use RockAdmin\Db\Entity;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\Query;
use RockAdmin\Db\Result;
use RockAdmin\Db\RowSource;
use RockAdmin\Db\Sql;
use RockAdmin\Grid\CellFormatter;
use RockAdmin\Grid\CellView;
use RockAdmin\Grid\GridState;
use RockAdmin\Grid\ListRegion;
use RockAdmin\Grid\QueryFactory;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\ColumnDefinition;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;
use RockAdmin\Page\FilterDefinition;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\RegionDefinition;
use RockAdmin\Page\RegionType;
use RockAdmin\Tests\Support\FakeRowSource;

/**
 * Everything above `ListRegion` describes a grid or executes one; this tests
 * assembly — that a fixed `Result` from a fake `RowSource` turns into the
 * right `ListView`, never that any particular SQL ran.
 */
#[CoversClass(ListRegion::class)]
final class ListRegionTest extends TestCase
{
    /** @var list<class-string> everything a view object may never carry */
    private const FORBIDDEN_IN_A_VIEW = [
        ColumnDefinition::class,
        FilterDefinition::class,
        RegionDefinition::class,
        PageDefinition::class,
        Query::class,
        Result::class,
        RowSource::class,
    ];

    private function page(Entity $entity = new Entity('ra_test_ads', 'id')): PageDefinition
    {
        return new PageDefinition('ads', 'Ads', 'default', 'Ads', $entity, [], []);
    }

    /**
     * @param array<string, ColumnDefinition> $columns
     * @param list<string>                    $searchable
     */
    private function region(
        array $columns = [],
        array $searchable = [],
        int $perPage = 20,
    ): RegionDefinition {
        if ($columns === []) {
            $columns = [
                'id' => $this->column('id'),
                'title' => $this->column('title'),
            ];
        }

        return new RegionDefinition('grid', RegionType::List, $perPage, $columns, [], $searchable);
    }

    private function column(
        string $key,
        bool $sortable = false,
        bool $link = false,
        ?FilterDefinition $filter = null,
        ?Collection $collection = null,
    ): ColumnDefinition {
        return new ColumnDefinition(
            filter: $filter,
            collection: $collection,
            key: $key,
            label: ucfirst($key),
            source: $key,
            type: ColumnType::Text,
            display: Display::Plain,
            sortable: $sortable,
            link: $link,
            align: 'start',
            width: null,
            class: '',
        );
    }

    private function urls(): UrlGenerator
    {
        return new UrlGenerator('/admin');
    }

    /** @param list<array<string, mixed>> $rows */
    private function fetchResult(array $rows, ?int $total): Result
    {
        return new Result($rows, $total, [new Sql('SELECT 1')]);
    }

    private function listRegion(RowSource $rows): ListRegion
    {
        return new ListRegion($rows, new QueryFactory(), new CellFormatter(), $this->urls());
    }

    /**
     * Reads one leaf of a generated URL's query string, decoded the same way
     * a real request's query string would decode -- so a test reads the
     * value a browser would send back, nested arrays and all, rather than
     * the literal, percent-encoded bytes on the wire.
     */
    private function queryValue(string $url, string ...$path): ?string
    {
        $query = parse_url($url, \PHP_URL_QUERY);
        parse_str(\is_string($query) ? $query : '', $decoded);

        $value = $decoded;

        foreach ($path as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return \is_string($value) ? $value : null;
    }

    public function testEveryRowBecomesARowViewWithACellPerColumn(): void
    {
        $region = $this->region();
        $rows = new FakeRowSource([$this->fetchResult([
            ['id' => 1, 'title' => 'A'],
            ['id' => 2, 'title' => 'B'],
        ], 2)]);

        $view = $this->listRegion($rows)->render($this->page(), $region, GridState::fromQuery([], 'grid', $region));

        $this->assertCount(2, $view->rows);

        foreach ($view->rows as $row) {
            $this->assertCount(2, $row->cells);

            foreach ($row->cells as $cell) {
                $this->assertInstanceOf(CellView::class, $cell);
            }
        }
    }

    public function testARowCarriesItsKeyValueSoTheTemplateCanIdentifyIt(): void
    {
        $region = $this->region();
        $rows = new FakeRowSource([$this->fetchResult([['id' => 7, 'title' => 'A']], 1)]);

        $view = $this->listRegion($rows)->render($this->page(), $region, GridState::fromQuery([], 'grid', $region));

        $this->assertSame(7, $view->rows[0]->key);
    }

    public function testAColumnThatLinksGivesItsCellTheRowsDetailUrl(): void
    {
        $region = $this->region([
            'id' => $this->column('id'),
            'title' => $this->column('title', link: true),
        ]);
        $rows = new FakeRowSource([$this->fetchResult([['id' => 7, 'title' => 'A']], 1)]);

        $view = $this->listRegion($rows)->render($this->page(), $region, GridState::fromQuery([], 'grid', $region));

        $row = $view->rows[0];
        $titleCell = $row->cells[1];

        $this->assertSame($row->url, $titleCell->url);
        $this->assertSame('/admin/p/ads/7', $row->url);

        // A column that does not link carries no url on its cell.
        $this->assertNull($row->cells[0]->url);
    }

    public function testTheSortUrlForAColumnTogglesItsDirection(): void
    {
        $region = $this->region([
            'id' => $this->column('id', sortable: true),
            'title' => $this->column('title'),
        ]);
        $rows = new FakeRowSource([$this->fetchResult([], 0)]);

        $view = $this->listRegion($rows)->render(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['sort' => 'id']], 'grid', $region),
        );

        $idColumn = $view->columns[0];
        $this->assertNotNull($idColumn->sortUrl);
        $this->assertSame('-id', $this->queryValue($idColumn->sortUrl, 'grid', 'sort'), 'ascending toggles to descending');
    }

    public function testTheSortUrlKeepsTheCurrentFiltersAndSearch(): void
    {
        $region = $this->region(
            [
                'id' => $this->column('id', sortable: true),
                'state' => $this->column('state', filter: new FilterDefinition('select', FilterOperator::Equals, 'State')),
            ],
            searchable: ['id'],
        );
        $rows = new FakeRowSource([$this->fetchResult([], 0)]);

        $view = $this->listRegion($rows)->render(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['q' => 'bike', 'f' => ['state' => 'active']]], 'grid', $region),
        );

        $idColumn = $view->columns[0];
        $this->assertNotNull($idColumn->sortUrl);
        $this->assertSame('bike', $this->queryValue($idColumn->sortUrl, 'grid', 'q'));
        $this->assertSame('active', $this->queryValue($idColumn->sortUrl, 'grid', 'f', 'state'));
    }

    public function testAnUnsortableColumnHasNoSortUrl(): void
    {
        $region = $this->region([
            'id' => $this->column('id'),
            'title' => $this->column('title', sortable: false),
        ]);
        $rows = new FakeRowSource([$this->fetchResult([], 0)]);

        $view = $this->listRegion($rows)->render($this->page(), $region, GridState::fromQuery([], 'grid', $region));

        $this->assertNull($view->columns[1]->sortUrl);
    }

    public function testOnlyTheCurrentSortColumnCarriesADirection(): void
    {
        $region = $this->region([
            'id' => $this->column('id', sortable: true),
            'title' => $this->column('title', sortable: true),
        ]);
        $rows = new FakeRowSource([$this->fetchResult([], 0)]);

        $view = $this->listRegion($rows)->render(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['sort' => 'id']], 'grid', $region),
        );

        $this->assertSame('ascending', $view->columns[0]->sortDirection);
        $this->assertNull($view->columns[1]->sortDirection);
    }

    public function testNoViewObjectCarriesAColumnDefinition(): void
    {
        $region = $this->region([
            'id' => $this->column('id', sortable: true),
            'title' => $this->column('title', link: true, filter: new FilterDefinition('text', FilterOperator::Contains, 'Title')),
        ]);
        $rows = new FakeRowSource([$this->fetchResult([['id' => 1, 'title' => 'A']], 1)]);

        $view = $this->listRegion($rows)->render($this->page(), $region, GridState::fromQuery([], 'grid', $region));

        $this->assertNoColumnDefinitionReachable($view);
    }

    /**
     * Rule 4 of this project: a template receives a prepared view object and
     * never the configuration or the database. Checking only for a
     * ColumnDefinition would pass a view that handed templates the region's
     * own definition, the query, or the row source — each of which is a route
     * to exactly what the rule forbids, and each of which a future field
     * could introduce without anyone noticing.
     *
     * @param array<int, true> $seen object ids already visited, guarding against cycles
     */
    private function assertNoColumnDefinitionReachable(object $object, array $seen = []): void
    {
        $id = spl_object_id($object);

        if (isset($seen[$id])) {
            return;
        }

        $seen[$id] = true;

        foreach (self::FORBIDDEN_IN_A_VIEW as $forbidden) {
            $this->assertNotInstanceOf($forbidden, $object);
        }

        foreach (get_object_vars($object) as $value) {
            if (\is_object($value)) {
                $this->assertNoColumnDefinitionReachable($value, $seen);
            } elseif (\is_array($value)) {
                foreach ($value as $item) {
                    if (\is_object($item)) {
                        $this->assertNoColumnDefinitionReachable($item, $seen);
                    }
                }
            }
        }
    }

    public function testAnEmptyResultIsAnEmptyViewRatherThanAnError(): void
    {
        $region = $this->region();
        $rows = new FakeRowSource([$this->fetchResult([], 0)]);

        $view = $this->listRegion($rows)->render($this->page(), $region, GridState::fromQuery([], 'grid', $region));

        $this->assertTrue($view->isEmpty());
        $this->assertSame([], $view->rows);
    }

    public function testTheRegionUrlIsTheFragmentAddressForThisRegion(): void
    {
        $region = $this->region();
        $rows = new FakeRowSource([$this->fetchResult([], 0)]);

        $view = $this->listRegion($rows)->render($this->page(), $region, GridState::fromQuery([], 'grid', $region));

        $this->assertSame('/admin/r/ads/grid', $view->regionUrl);
    }

    public function testThePageUrlIsTheWholePagesOwnAddress(): void
    {
        $region = $this->region();
        $rows = new FakeRowSource([$this->fetchResult([], 0)]);

        $view = $this->listRegion($rows)->render($this->page(), $region, GridState::fromQuery([], 'grid', $region));

        $this->assertSame('/admin/p/ads', $view->pageUrl);
    }

    /**
     * Spec 8.11: grid state lives in the URL precisely so a sort or pager
     * link is shareable. A link that addresses the region's own fragment
     * route instead lands on a shell-less fragment when followed with no
     * JavaScript, or copied out of the address bar into a fresh tab -- not
     * shareable at all. So every sort and pager URL must be built against
     * `page.index`, never `region`, however core.js goes on to use it.
     */
    public function testASortUrlAndAPagerUrlAddressThePageRouteNotTheRegionRoute(): void
    {
        $region = $this->region(
            [
                'id' => $this->column('id', sortable: true),
                'title' => $this->column('title'),
            ],
            perPage: 1,
        );
        $rows = new FakeRowSource([$this->fetchResult([['id' => 1, 'title' => 'A']], 3)]);

        $view = $this->listRegion($rows)->render($this->page(), $region, GridState::fromQuery([], 'grid', $region));

        $sortUrl = $view->columns[0]->sortUrl;
        $this->assertNotNull($sortUrl);
        $this->assertStringStartsWith('/admin/p/ads?', $sortUrl);
        $this->assertStringNotContainsString('/admin/r/ads/grid', $sortUrl);

        $pagerUrl = $view->pagination->nextUrl;
        $this->assertNotNull($pagerUrl);
        $this->assertStringStartsWith('/admin/p/ads?', $pagerUrl);
        $this->assertStringNotContainsString('/admin/r/ads/grid', $pagerUrl);
    }

    public function testFiltersCarryTheValueTheUrlAlreadyHeld(): void
    {
        $region = $this->region([
            'id' => $this->column('id'),
            'state' => $this->column('state', filter: new FilterDefinition('select', FilterOperator::Equals, 'State')),
        ]);
        $rows = new FakeRowSource([$this->fetchResult([], 0)]);

        $view = $this->listRegion($rows)->render(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['state' => 'active']]], 'grid', $region),
        );

        $this->assertCount(1, $view->filters);
        $this->assertSame('state', $view->filters[0]->key);
        $this->assertSame('active', $view->filters[0]->value);
    }

    public function testOneSupplementaryStatementFetchesAOneToManyForThePage(): void
    {
        // ListRegion never queries per row -- it asks RowSource once and reads
        // whatever it comes back with, collections included. A RowSource that
        // is only ever called once, no matter how many rows or collection
        // columns the region has, is what "no query inside a row loop" means
        // from this class's own point of view.
        $collection = new Collection('tags', 'ra_test_tags', 'ad_id', 'label');
        $region = $this->region([
            'id' => $this->column('id'),
            'title' => $this->column('title'),
            'tags' => $this->column('tags', collection: $collection),
        ]);
        $rows = new FakeRowSource([$this->fetchResult([
            ['id' => 1, 'title' => 'A', 'tags' => ['bazar', 'sleva']],
            ['id' => 2, 'title' => 'B', 'tags' => []],
        ], 2)]);

        $this->listRegion($rows)->render($this->page(), $region, GridState::fromQuery([], 'grid', $region));

        $this->assertSame(1, $rows->calls, 'one RowSource::fetch() call for the whole page');
    }

    public function testAPageBeyondTheEndReRunsTheQueryForTheLastPage(): void
    {
        $region = $this->region(perPage: 2);
        $rows = new FakeRowSource([
            $this->fetchResult([], 3),
            $this->fetchResult([['id' => 3, 'title' => 'C']], 3),
        ]);

        $view = $this->listRegion($rows)->render(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['page' => '9']], 'grid', $region),
        );

        $this->assertSame(2, $rows->calls);
        $this->assertSame(2, $view->pagination->currentPage);
        $this->assertCount(1, $view->rows);
    }

    public function testEveryViewObjectsClassesActuallyCarryTheRaPrefix(): void
    {
        // The template standard lets a template print a view's own classes
        // into its class attribute instead of writing a literal ra- token,
        // on the understanding that the view assembled them through
        // RockAdmin\View\Classes. Nothing verified that, and the
        // check that permits it cannot: it reads template source, and the
        // guarantee lives here. A `classes` property that never carried an
        // ra- token would satisfy the standard while rendering an element
        // with no class this project can style or target.
        $region = $this->region();
        $rows = new FakeRowSource([$this->fetchResult([
            ['id' => 1, 'title' => 'A'],
        ], 1)]);

        $view = $this->listRegion($rows)->render($this->page(), $region, GridState::fromQuery([], 'grid', $region));

        foreach ($view->columns as $column) {
            $this->assertMatchesRegularExpression('/(^| )ra-[a-z0-9-]+/', $column->classes, "column {$column->key}");
        }

        foreach ($view->rows as $row) {
            $this->assertMatchesRegularExpression('/(^| )ra-[a-z0-9-]+/', $row->classes, 'row');

            foreach ($row->cells as $cell) {
                $this->assertMatchesRegularExpression('/(^| )ra-[a-z0-9-]+/', $cell->classes, "cell {$cell->key}");
            }
        }

        $this->assertMatchesRegularExpression('/(^| )ra-[a-z0-9-]+/', $view->classes());
    }
}
