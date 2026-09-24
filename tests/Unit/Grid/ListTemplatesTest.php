<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Grid\CellView;
use RockAdmin\Grid\ColumnView;
use RockAdmin\Grid\FilterView;
use RockAdmin\Grid\ListView;
use RockAdmin\Grid\PaginationView;
use RockAdmin\Grid\RowView;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\ColumnType;
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
        string $pageUrl = '/admin/p/ads',
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
            pageUrl: $pageUrl,
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
        ColumnType $type = ColumnType::Text,
        ?string $url = null,
        array $attributes = [],
        ?int $percent = null,
        ?string $variant = null,
    ): CellView {
        return new CellView(
            key: $key,
            value: $value,
            text: $text,
            type: $type,
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

    public function testACellCarryingAUrlWrapsItsContentInAnAnchor(): void
    {
        // A link is a wrapper around whatever the display drew, applied
        // whenever $cell->url is set -- never a display of its own. Plain
        // text is used here deliberately: the point is that wrapping does
        // not depend on the display at all.
        $row = $this->row(cells: [
            $this->cell(key: 'title', display: Display::Plain, text: 'Hello', url: '/admin/p/ads/42'),
        ]);

        $html = $this->renderer()->render('region/list/row', $row);

        // The anchor wraps whatever cell/plain.php drew -- a <span>, not bare
        // text -- so the assertion is that the anchor opens with the right
        // href and its content, somewhere inside, still says "Hello".
        $this->assertMatchesRegularExpression('/<a\b[^>]*href="\/admin\/p\/ads\/42"[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<a\b[^>]*>.*Hello.*<\/a>/s', $html);
    }

    public function testADistinctiveDisplayStillLinksWhenItsCellCarriesAUrl(): void
    {
        // A badge, a progress bar or a checkbox all have to be able to open
        // a row: the wrapper is independent of what it wraps.
        $row = $this->row(cells: [
            $this->cell(key: 'status', display: Display::Badge, text: 'Active', variant: 'success', url: '/admin/p/ads/42'),
        ]);

        $html = $this->renderer()->render('region/list/row', $row);

        $this->assertMatchesRegularExpression('/<a\b[^>]*href="\/admin\/p\/ads\/42"[^>]*>.*badge.*Active.*<\/a>/s', $html);
    }

    public function testACellWithNoUrlDoesNotRenderAnAnchor(): void
    {
        $row = $this->row(cells: [$this->cell(key: 'title', display: Display::Plain, text: 'Hello', url: null)]);

        $html = $this->renderer()->render('region/list/row', $row);

        $this->assertStringNotContainsString('<a', $html);
    }

    public function testAProgressCellRendersItsPercentageAsAWidth(): void
    {
        $cell = $this->cell(key: 'progress', display: Display::Progress, text: '42', percent: 42);

        $html = $this->renderer()->render('region/list/cell/progress', $cell);

        $this->assertStringContainsString('width: 42%', $html);
    }

    public function testAPercentCellShowsItsTextWithoutABar(): void
    {
        $cell = $this->cell(key: 'ratio', display: Display::Percent, text: '42 %', percent: 42);

        $html = $this->renderer()->render('region/list/cell/percent', $cell);

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

        $html = $this->renderer()->render('region/list/cell/check', $cell);

        $this->assertStringContainsString('✓', $html);
    }

    /** @return array<string, array{string, Display, ColumnType}> */
    public static function everyCellPartial(): array
    {
        return [
            'plain' => ['plain', Display::Plain, ColumnType::Text],
            'money' => ['money', Display::Plain, ColumnType::Money],
            'datetime' => ['datetime', Display::Plain, ColumnType::Datetime],
            'json' => ['json', Display::Plain, ColumnType::Json],
            'badge' => ['badge', Display::Badge, ColumnType::Text],
            'check' => ['check', Display::Check, ColumnType::Bool],
            'yesno' => ['yesno', Display::YesNo, ColumnType::Bool],
            'progress' => ['progress', Display::Progress, ColumnType::Int],
            'percent' => ['percent', Display::Percent, ColumnType::Int],
            // link.php is a wrapper, not a display; it is exercised with the
            // same display/type pair 'plain' uses, and its own url is always
            // set by row.php/field.php before this template is ever reached.
            'link' => ['link', Display::Plain, ColumnType::Text],
        ];
    }

    #[DataProvider('everyCellPartial')]
    public function testEveryCellPartialEscapesWhatItPrints(
        string $partial,
        Display $display,
        ColumnType $type,
    ): void {
        // Rule 6 of this project is that everything is escaped with $e() and
        // $raw() is the explicit exception. The template standard cannot tell
        // the two apart — both are on its allowed list — so swapping one for
        // the other in a cell partial would pass every static check. Only
        // rendering hostile content through each of them catches that, and
        // only two of the eight were covered.
        $hostile = '<script>alert("x")</script> & "quoted"';

        $html = $this->renderer()->render(
            "region/list/cell/{$partial}",
            $this->cell(key: 'field', value: $hostile, text: $hostile, display: $display, type: $type, url: '/admin/p/ads/1', variant: 'success'),
        );

        $this->assertStringNotContainsString('<script>', $html, "{$partial} printed a script tag.");
        $this->assertStringNotContainsString('alert("x")', $html, "{$partial} printed an unescaped quote.");
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
        // The clear link addresses the whole page's own address, never the
        // region's fragment route: a fragment has no shell, so a plain
        // no-JavaScript follow -- or a copied link -- must not land on 13 KB
        // of HTML with no <html> around it. Compare the sort and pager links,
        // fixed the same way for the same reason.
        $view = $this->listView(search: 'bike', regionUrl: '/admin/r/ads/grid', pageUrl: '/admin/p/ads');

        $html = $this->renderer()->render('region/list/empty', $view);

        $this->assertMatchesRegularExpression('/<a\b[^>]*href="\/admin\/p\/ads"[^>]*data-ra-action="paginate"[^>]*>/', $html);
        $this->assertStringNotContainsString('href="/admin/r/ads/grid"', $html);
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

    public function testTheToolbarCarriesTheCurrentSortAsAHiddenField(): void
    {
        // Without this, submitting the toolbar (filter or search) carries
        // only 'q' and the filters -- GridState::fromQuery() then finds no
        // 'sort' in the request, falls back to the region's own default, and
        // a sort applied earlier is silently lost the moment a filter or a
        // search is submitted.
        $view = $this->listView(key: 'grid', searchable: true, columns: [
            $this->column(key: 'id'),
            $this->column(key: 'price', sortDirection: 'descending'),
        ]);

        $html = $this->renderer()->render('region/list/toolbar', $view);

        $this->assertMatchesRegularExpression(
            '/<input\b[^>]*type="hidden"[^>]*name="grid\[sort\]"[^>]*value="-price"/',
            $html,
        );
    }

    public function testTheToolbarCarriesNoSortFieldWhenNothingIsSorted(): void
    {
        $view = $this->listView(key: 'grid', searchable: true, columns: [
            $this->column(key: 'id'),
        ]);

        $html = $this->renderer()->render('region/list/toolbar', $view);

        $this->assertStringNotContainsString('[sort]', $html);
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

    public function testARangeFiltersLabelPointsAtARealInput(): void
    {
        // The range branch used to put no id on either input, so the label's
        // for="..." pointed at nothing: clicking it did nothing, and a
        // screen reader announced two unlabelled inputs.
        $view = $this->listView(key: 'grid', filters: [
            new FilterView('price', 'Price', 'range', null, [], ['from' => '10', 'to' => '20']),
        ]);

        $html = $this->renderer()->render('region/list/filters', $view);

        // The id filters.php builds is 'ra-grid-filter-<region>-<column>' --
        // asserted directly, rather than by extracting whatever the label's
        // own for="..." happens to hold, so this fails if the label points
        // at the right-shaped id that no input actually carries.
        $this->assertStringContainsString('for="ra-grid-filter-grid-price"', $html);
        $this->assertStringContainsString('id="ra-grid-filter-grid-price"', $html);
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
                $this->cell(key: 'title', display: Display::Plain, text: 'Row', url: '/admin/p/ads/42?marker=ROW'),
            ])],
            filters: [new FilterView('status', 'Status', 'text', null, [], '')],
            pagination: PaginationView::of(1, 1, 2, 1, static fn (int $n): string => "/admin/r/ads/grid?marker=PAGE{$n}"),
            searchable: true,
            pageUrl: '/admin/p/ads?marker=FORMACTION',
        );

        $regionHtml = $this->renderer()->render('region/list/region', $view);

        $this->assertStringContainsString('marker=REGION', $regionHtml);
        $this->assertStringContainsString('marker=SORT', $regionHtml);
        $this->assertStringContainsString('marker=ROW', $regionHtml);
        $this->assertStringContainsString('marker=FORMACTION', $regionHtml);
        $this->assertStringContainsString('marker=PAGE', $regionHtml);
    }

    /** @return array<string, array{ColumnType, string}> */
    public static function plainCellTypes(): array
    {
        return [
            'money' => [ColumnType::Money, 'ra-grid-cell-money'],
            'datetime' => [ColumnType::Datetime, 'ra-grid-cell-datetime'],
            'json' => [ColumnType::Json, 'ra-grid-cell-json'],
            'int' => [ColumnType::Int, 'ra-grid-cell-int'],
            'text' => [ColumnType::Text, 'ra-grid-cell-text'],
        ];
    }

    #[DataProvider('plainCellTypes')]
    public function testAPlainCellIsRenderedByItsTypeSinceItsDisplayCannotTellThemApart(
        ColumnType $type,
        string $expected,
    ): void {
        // Four of the seven types share Display::Plain, so dispatching on
        // display alone sent money, dates and JSON all to the text partial
        // and left three shipped templates unreachable by any configuration.
        $html = $this->renderer()->render(
            'region/list/row',
            $this->row(cells: [$this->cell(display: Display::Plain, type: $type)]),
        );

        $this->assertStringContainsString($expected, $html);
    }

    public function testADistinctiveDisplayStillWinsOverTheType(): void
    {
        // A text column shown as a badge is drawn as a badge. The type only
        // decides when the display has nothing to say.
        $html = $this->renderer()->render(
            'region/list/row',
            $this->row(cells: [$this->cell(display: Display::Badge, type: ColumnType::Text, variant: 'success')]),
        );

        $this->assertStringContainsString('ra-grid-cell-badge', $html);
        $this->assertStringNotContainsString('ra-grid-cell-text', $html);
    }
}
