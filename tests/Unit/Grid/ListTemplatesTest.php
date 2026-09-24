<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RockAdmin\Grid\CellView;
use RockAdmin\Grid\ColumnView;
use RockAdmin\Grid\FilterView;
use RockAdmin\Grid\ListView;
use RockAdmin\Grid\PaginationView;
use RockAdmin\Grid\RowView;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\Display;
use RockAdmin\View\Escaper;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * The list region's templates, rendered as they ship over hand-built views —
 * the same style as `DefaultTemplatesTest`. This is about the promises the
 * brief makes (identity classes, no-JS sorting and filtering, the two empty
 * messages, alignment following the column's own decision) rather than about
 * markup, so a styling change should not break it.
 */
#[CoversNothing]
final class ListTemplatesTest extends TestCase
{
    private function renderer(): Renderer
    {
        return new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/admin'));
    }

    /**
     * @param list<ColumnView> $columns
     * @param list<RowView>    $rows
     * @param list<FilterView> $filters
     */
    private function listView(
        string $key = 'grid',
        string $pageName = 'ads',
        array $columns = [],
        array $rows = [],
        array $filters = [],
        ?PaginationView $pagination = null,
        string $search = '',
        bool $searchable = false,
        string $regionUrl = '/admin/r/ads/grid',
    ): ListView {
        return new ListView(
            key: $key,
            pageName: $pageName,
            columns: $columns,
            rows: $rows,
            filters: $filters,
            pagination: $pagination ?? $this->pagination(),
            search: $search,
            searchable: $searchable,
            regionUrl: $regionUrl,
        );
    }

    private function pagination(int $page = 1, int $total = 1, int $rowCount = 1): PaginationView
    {
        return PaginationView::of($page, 20, $total, $rowCount, static fn (int $n): string => "/admin/r/ads/grid?grid[page]={$n}");
    }

    private function column(
        string $key = 'title',
        string $label = 'Title',
        string $align = 'start',
        ?string $width = null,
        ?string $sortUrl = null,
        ?string $sortDirection = null,
    ): ColumnView {
        return new ColumnView(
            key: $key,
            label: $label,
            classes: "ra-grid-head ra-grid-head-{$key}",
            align: $align,
            width: $width,
            sortUrl: $sortUrl,
            sortDirection: $sortDirection,
        );
    }

    /** @param array<string, scalar|null> $attributes */
    private function cell(
        string $key = 'title',
        mixed $value = 'Hello',
        string $text = 'Hello',
        Display $display = Display::Plain,
        ?string $url = null,
        array $attributes = [],
        ?int $percent = null,
        ?string $variant = null,
    ): CellView {
        return new CellView(
            key: $key,
            value: $value,
            text: $text,
            display: $display,
            classes: "ra-grid-cell ra-grid-cell-{$key}",
            url: $url,
            attributes: $attributes,
            percent: $percent,
            variant: $variant,
        );
    }

    /** @param list<CellView> $cells */
    private function row(mixed $key = 42, array $cells = [], string $url = '/admin/p/ads/42'): RowView
    {
        return new RowView($key, $cells === [] ? [$this->cell()] : $cells, $url, 'ra-grid-row');
    }

    // --- region.php ---

    public function testTheRegionCarriesItsKeyAndItsFragmentUrl(): void
    {
        $view = $this->listView(key: 'grid', regionUrl: '/admin/r/ads/grid');

        $html = $this->renderer()->render('region/list/region', $view);

        $this->assertStringContainsString('data-ra-region="grid"', $html);
        $this->assertStringContainsString('data-ra-region-url="/admin/r/ads/grid"', $html);
        $this->assertStringContainsString('ra-region-list', $html);
    }

    public function testTheRegionCarriesThePageIdentityClass(): void
    {
        $view = $this->listView(pageName: 'ads');

        $html = $this->renderer()->render('region/list/region', $view);

        $this->assertStringContainsString('ra-region-list-ads', $html);
    }

    // --- head.php ---

    public function testEveryColumnGetsAHeaderCellCarryingItsIdentityClass(): void
    {
        $view = $this->listView(columns: [
            $this->column(key: 'id', label: 'Id'),
            $this->column(key: 'title', label: 'Title'),
        ]);

        $html = $this->renderer()->render('region/list/head', $view);

        $this->assertStringContainsString('ra-grid-head-id', $html);
        $this->assertStringContainsString('ra-grid-head-title', $html);
    }

    public function testASortableHeaderIsALinkAndSaysSoToAScreenReader(): void
    {
        $view = $this->listView(columns: [
            $this->column(key: 'title', sortUrl: '/admin/r/ads/grid?grid[sort]=title', sortDirection: 'ascending'),
        ]);

        $html = $this->renderer()->render('region/list/head', $view);

        $this->assertMatchesRegularExpression(
            '/<a\b[^>]*href="\/admin\/r\/ads\/grid\?grid\[sort\]=title"[^>]*>/',
            $html,
        );
    }

    public function testAnUnsortableHeaderIsNotALink(): void
    {
        $view = $this->listView(columns: [$this->column(key: 'title', sortUrl: null)]);

        $html = $this->renderer()->render('region/list/head', $view);

        $this->assertStringNotContainsString('<a', $html);
    }

    public function testTheCurrentSortColumnCarriesAriaSort(): void
    {
        $view = $this->listView(columns: [
            $this->column(key: 'title', sortUrl: '/admin/r/ads/grid?grid[sort]=-title', sortDirection: 'descending'),
        ]);

        $html = $this->renderer()->render('region/list/head', $view);

        $this->assertStringContainsString('aria-sort="descending"', $html);
    }

    public function testAColumnThatIsNotTheCurrentSortCarriesNoAriaSort(): void
    {
        $view = $this->listView(columns: [
            $this->column(key: 'title', sortUrl: '/admin/r/ads/grid?grid[sort]=title', sortDirection: null),
        ]);

        $html = $this->renderer()->render('region/list/head', $view);

        $this->assertStringNotContainsString('aria-sort', $html);
    }

    public function testANumericColumnAlignsByItsOwnDecisionNotTheTemplates(): void
    {
        $view = $this->listView(columns: [$this->column(key: 'price', align: 'end')]);

        $html = $this->renderer()->render('region/list/head', $view);

        $this->assertStringContainsString('text-end', $html);
    }

    // --- row.php / cell dispatch ---

    public function testEachRowCarriesItsKeyValue(): void
    {
        $row = $this->row(key: 42);

        $html = $this->renderer()->render('region/list/row', $row);

        $this->assertStringContainsString('data-id="42"', $html);
    }

    public function testACellIsRenderedByItsDisplayNotItsType(): void
    {
        // Nothing reaching this template names a ColumnType at all — a
        // Display::Badge cell renders the badge partial regardless of what
        // column it came from, which is the only thing that can be tested
        // here: CellView carries no type to vary in the first place.
        $row = $this->row(cells: [
            $this->cell(key: 'status', display: Display::Badge, text: 'Active', variant: 'success'),
        ]);

        $html = $this->renderer()->render('region/list/row', $row);

        $this->assertStringContainsString('badge', $html);
        $this->assertStringContainsString('text-bg-success', $html);
        $this->assertStringContainsString('Active', $html);
    }

    public function testAPlainTextCellIsNotRenderedAsABadge(): void
    {
        $row = $this->row(cells: [$this->cell(key: 'title', display: Display::Plain, text: 'Hello')]);

        $html = $this->renderer()->render('region/list/row', $row);

        $this->assertStringNotContainsString('badge', $html);
        $this->assertStringContainsString('Hello', $html);
    }

    public function testALinkingCellWrapsItsContentInAnAnchor(): void
    {
        $row = $this->row(cells: [
            $this->cell(key: 'title', display: Display::Link, text: 'Hello', url: '/admin/p/ads/42'),
        ]);

        $html = $this->renderer()->render('region/list/row', $row);

        $this->assertMatchesRegularExpression('/<a\b[^>]*href="\/admin\/p\/ads\/42"[^>]*>Hello<\/a>/', $html);
    }

    public function testALinkingDisplayWithNoUrlDoesNotRenderAnAnchor(): void
    {
        $row = $this->row(cells: [$this->cell(key: 'title', display: Display::Link, text: 'Hello', url: null)]);

        $html = $this->renderer()->render('region/list/row', $row);

        $this->assertStringNotContainsString('<a', $html);
    }

    public function testAProgressCellRendersItsPercentageAsAWidth(): void
    {
        $cell = $this->cell(key: 'progress', display: Display::Progress, text: '42', percent: 42);

        $html = $this->renderer()->render('region/list/cell/int', $cell);

        $this->assertStringContainsString('width: 42%', $html);
    }

    public function testAPercentCellShowsItsTextWithoutABar(): void
    {
        $cell = $this->cell(key: 'ratio', display: Display::Percent, text: '42 %', percent: 42);

        $html = $this->renderer()->render('region/list/cell/int', $cell);

        $this->assertStringContainsString('42 %', $html);
        $this->assertStringNotContainsString('progress-bar', $html);
    }

    public function testAJsonCellCarriesTheFullValueInATitle(): void
    {
        $cell = $this->cell(
            key: 'payload',
            display: Display::Plain,
            text: '{"a":1}…',
            attributes: ['title' => '{"a":1,"b":2,"c":3}'],
        );

        $html = $this->renderer()->render('region/list/cell/json', $cell);

        $this->assertStringContainsString('title="{&quot;a&quot;:1,&quot;b&quot;:2,&quot;c&quot;:3}"', $html);
    }

    public function testABooleanCellShowsCheckmarkOrPlainText(): void
    {
        $cell = $this->cell(key: 'active', display: Display::Check, text: '✓');

        $html = $this->renderer()->render('region/list/cell/bool', $cell);

        $this->assertStringContainsString('✓', $html);
    }

    public function testTheMoneyAndDatetimePartialsEscapeTheirText(): void
    {
        $money = $this->cell(key: 'price', display: Display::Plain, text: '<b>1,00</b>');
        $datetime = $this->cell(key: 'created_at', display: Display::Plain, text: '<b>2024</b>');

        $moneyHtml = $this->renderer()->render('region/list/cell/money', $money);
        $datetimeHtml = $this->renderer()->render('region/list/cell/datetime', $datetime);

        $this->assertStringNotContainsString('<b>', $moneyHtml);
        $this->assertStringNotContainsString('<b>', $datetimeHtml);
        $this->assertStringContainsString('&lt;b&gt;', $moneyHtml);
        $this->assertStringContainsString('&lt;b&gt;', $datetimeHtml);
    }

    // --- empty.php ---

    public function testAnEmptyGridWithNoFiltersSaysSomethingDifferentFromOneWithFilters(): void
    {
        $unfiltered = $this->listView(rows: [], search: '', filters: []);
        $filtered = $this->listView(rows: [], search: 'bike', filters: []);

        $unfilteredHtml = $this->renderer()->render('region/list/empty', $unfiltered);
        $filteredHtml = $this->renderer()->render('region/list/empty', $filtered);

        $this->assertStringContainsString('Nothing here yet', $unfilteredHtml);
        $this->assertStringNotContainsString('Nothing matches', $unfilteredHtml);

        $this->assertStringContainsString('Nothing matches', $filteredHtml);
        $this->assertStringNotContainsString('Nothing here yet', $filteredHtml);
    }

    public function testTheFilteredEmptyStateOffersALinkThatClearsIt(): void
    {
        $view = $this->listView(search: 'bike', regionUrl: '/admin/r/ads/grid');

        $html = $this->renderer()->render('region/list/empty', $view);

        $this->assertMatchesRegularExpression('/<a\b[^>]*href="\/admin\/r\/ads\/grid"[^>]*>/', $html);
    }

    // --- toolbar.php / filters.php ---

    public function testTheFilterFormSubmitsWithGet(): void
    {
        $view = $this->listView(searchable: true, filters: [
            new FilterView('status', 'Status', 'text', null, [], ''),
        ]);

        $html = $this->renderer()->render('region/list/toolbar', $view);

        $this->assertMatchesRegularExpression('/<form\b[^>]*method="get"/', $html);
    }

    public function testTheSearchFieldCarriesTheRegionNamespacedName(): void
    {
        $view = $this->listView(key: 'grid', searchable: true, search: 'bike');

        $html = $this->renderer()->render('region/list/toolbar', $view);

        $this->assertStringContainsString('name="grid[q]"', $html);
        $this->assertStringContainsString('value="bike"', $html);
    }

    public function testATextFilterCarriesTheRegionNamespacedFieldName(): void
    {
        $view = $this->listView(key: 'grid', filters: [
            new FilterView('status', 'Status', 'text', 'Any status', [], 'active'),
        ]);

        $html = $this->renderer()->render('region/list/filters', $view);

        $this->assertStringContainsString('name="grid[f][status]"', $html);
        $this->assertStringContainsString('value="active"', $html);
    }

    public function testARangeFilterCarriesFromAndToFieldNames(): void
    {
        $view = $this->listView(key: 'grid', filters: [
            new FilterView('price', 'Price', 'range', null, [], ['from' => '10', 'to' => '20']),
        ]);

        $html = $this->renderer()->render('region/list/filters', $view);

        $this->assertStringContainsString('name="grid[f][price][from]"', $html);
        $this->assertStringContainsString('name="grid[f][price][to]"', $html);
        $this->assertStringContainsString('value="10"', $html);
        $this->assertStringContainsString('value="20"', $html);
    }

    public function testAMultiselectFilterMarksEachMatchingOptionSelected(): void
    {
        $view = $this->listView(key: 'grid', filters: [
            new FilterView('tags', 'Tags', 'multiselect', null, [
                'a' => new \RockAdmin\Config\EnumOption('a', 'A'),
                'b' => new \RockAdmin\Config\EnumOption('b', 'B'),
            ], ['a']),
        ]);

        $html = $this->renderer()->render('region/list/filters', $view);

        $this->assertStringContainsString('name="grid[f][tags][]"', $html);
        $this->assertMatchesRegularExpression('/value="a"\s+selected/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="b"\s+selected/', $html);
    }

    // --- pagination.php ---

    public function testThePagerLinksCarryTheCurrentFiltersAndSort(): void
    {
        $marker = '/admin/r/ads/grid?grid[f][status]=active&grid[sort]=-created_at&grid[page]=2';
        $pagination = PaginationView::of(1, 20, 60, 20, static fn (int $n): string => str_replace('page]=2', "page]={$n}", $marker));

        $html = $this->renderer()->render('region/list/pagination', $pagination);

        $this->assertStringContainsString('grid[f][status]=active', $html);
        $this->assertStringContainsString('grid[sort]=-created_at', $html);
    }

    public function testAPagerWithOnlyOnePageRendersNothing(): void
    {
        $pagination = PaginationView::of(1, 20, 5, 5, static fn (int $n): string => "/admin/r/ads/grid?grid[page]={$n}");

        $html = $this->renderer()->render('region/list/pagination', $pagination);

        $this->assertSame('', trim($html));
    }

    // --- no self-built URLs ---

    public function testNoTemplateWritesAUrlItBuiltItself(): void
    {
        // Every URL that appears comes from a view object's own field, never
        // from string concatenation inside the template: thread a
        // distinctive marker through every URL-bearing view field and check
        // it survives into the output unchanged.
        $view = $this->listView(
            key: 'grid',
            pageName: 'ads',
            regionUrl: '/admin/r/ads/grid?marker=REGION',
            columns: [$this->column(key: 'title', sortUrl: '/admin/r/ads/grid?marker=SORT', sortDirection: 'ascending')],
            rows: [$this->row(cells: [
                $this->cell(key: 'title', display: Display::Link, text: 'Row', url: '/admin/p/ads/42?marker=ROW'),
            ])],
            filters: [new FilterView('status', 'Status', 'text', null, [], '')],
            pagination: PaginationView::of(1, 1, 2, 1, static fn (int $n): string => "/admin/r/ads/grid?marker=PAGE{$n}"),
            searchable: true,
        );

        $regionHtml = $this->renderer()->render('region/list/region', $view);

        $this->assertStringContainsString('marker=REGION', $regionHtml);
        $this->assertStringContainsString('marker=SORT', $regionHtml);
        $this->assertStringContainsString('marker=ROW', $regionHtml);
        $this->assertStringContainsString('marker=PAGE', $regionHtml);
    }
}
