<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\Sort;
use RockAdmin\Db\SortDirection;
use RockAdmin\Grid\FilterInput;
use RockAdmin\Grid\GridState;
use RockAdmin\Page\ColumnDefinition;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;
use RockAdmin\Page\FilterDefinition;
use RockAdmin\Page\RegionDefinition;
use RockAdmin\Page\RegionType;

#[CoversClass(GridState::class)]
final class GridStateTest extends TestCase
{
    /**
     * @param array<string, ColumnDefinition> $columns
     * @param list<Sort>                      $sort
     * @param list<string>                    $searchable
     */
    private function region(array $columns = [], array $sort = [], array $searchable = []): RegionDefinition
    {
        return new RegionDefinition('grid', RegionType::List, 25, $columns, $sort, $searchable);
    }

    private function column(
        string $key,
        bool $sortable = false,
        ?FilterDefinition $filter = null,
    ): ColumnDefinition {
        return new ColumnDefinition(
            filter: $filter,
            collection: null,
            key: $key,
            label: ucfirst($key),
            source: $key,
            type: ColumnType::Text,
            display: Display::Plain,
            sortable: $sortable,
            link: false,
            align: 'start',
            width: null,
            class: '',
        );
    }

    private function filter(FilterOperator $operator = FilterOperator::Contains, string $type = 'text'): FilterDefinition
    {
        return new FilterDefinition($type, $operator, ucfirst($type));
    }

    public function testAnEmptyQueryIsAnEmptyStateOnPageOne(): void
    {
        $region = $this->region(['title' => $this->column('title')]);

        $state = GridState::fromQuery([], 'grid', $region);

        $this->assertSame('', $state->search);
        $this->assertSame([], $state->filters);
        $this->assertSame([], $state->sort);
        $this->assertSame(1, $state->page);
    }

    public function testTheSearchTermIsRead(): void
    {
        $region = $this->region(['title' => $this->column('title')], searchable: ['title']);

        $state = GridState::fromQuery(['grid' => ['q' => 'bike']], 'grid', $region);

        $this->assertSame('bike', $state->search);
    }

    public function testASearchTermOfSpacesIsNoSearch(): void
    {
        $region = $this->region(['title' => $this->column('title')], searchable: ['title']);

        $state = GridState::fromQuery(['grid' => ['q' => '   ']], 'grid', $region);

        $this->assertSame('', $state->search);
    }

    public function testASearchTermIsDroppedWhenNoColumnIsSearchable(): void
    {
        $region = $this->region(['title' => $this->column('title')], searchable: []);

        $state = GridState::fromQuery(['grid' => ['q' => 'bike']], 'grid', $region);

        $this->assertSame('', $state->search);
    }

    public function testAFilterIsReadForADeclaredColumn(): void
    {
        $region = $this->region(['state' => $this->column('state', filter: $this->filter(FilterOperator::Equals, 'select'))]);

        $state = GridState::fromQuery(['grid' => ['f' => ['state' => 'active']]], 'grid', $region);

        $this->assertEquals([new FilterInput('state', 'active')], $state->filters);
    }

    public function testAFilterNamingAnUndeclaredColumnIsDropped(): void
    {
        $region = $this->region(['title' => $this->column('title')]);

        $state = GridState::fromQuery(['grid' => ['f' => ['state' => 'active']]], 'grid', $region);

        $this->assertSame([], $state->filters);
    }

    public function testAFilterOnAColumnThatDeclaresNoneIsDropped(): void
    {
        $region = $this->region(['title' => $this->column('title')]);

        $state = GridState::fromQuery(['grid' => ['f' => ['title' => 'bike']]], 'grid', $region);

        $this->assertSame([], $state->filters);
    }

    public function testARangeFilterIsReadFromFromAndTo(): void
    {
        $region = $this->region(['price' => $this->column('price', filter: $this->filter(FilterOperator::Between, 'range'))]);

        $state = GridState::fromQuery(
            ['grid' => ['f' => ['price' => ['from' => '10', 'to' => '20']]]],
            'grid',
            $region,
        );

        $this->assertEquals(
            [new FilterInput('price', ['from' => '10', 'to' => '20'])],
            $state->filters,
        );
    }

    public function testARangeWithOnlyOneEndStillFilters(): void
    {
        $region = $this->region(['price' => $this->column('price', filter: $this->filter(FilterOperator::Between, 'range'))]);

        $state = GridState::fromQuery(
            ['grid' => ['f' => ['price' => ['from' => '10']]]],
            'grid',
            $region,
        );

        $this->assertEquals(
            [new FilterInput('price', ['from' => '10'])],
            $state->filters,
        );
    }

    public function testAOneEndedAndATwoEndedRangeProduceTheSameShapeDifferingOnlyInValue(): void
    {
        // Pinned from this side because QueryFactory (task 4) keys its choice
        // of Between vs. GreaterOrEqual/LessOrEqual on exactly this shape: a
        // one-ended range must not be a different kind of FilterInput than a
        // two-ended one, only a different value.
        $region = $this->region(['price' => $this->column('price', filter: $this->filter(FilterOperator::Between, 'range'))]);

        $oneEnded = GridState::fromQuery(
            ['grid' => ['f' => ['price' => ['from' => '10']]]],
            'grid',
            $region,
        )->filters[0];

        $twoEnded = GridState::fromQuery(
            ['grid' => ['f' => ['price' => ['from' => '10', 'to' => '20']]]],
            'grid',
            $region,
        )->filters[0];

        $this->assertInstanceOf(FilterInput::class, $oneEnded);
        $this->assertInstanceOf(FilterInput::class, $twoEnded);
        $this->assertSame('price', $oneEnded->column);
        $this->assertSame('price', $twoEnded->column);
        $this->assertSame(['from' => '10'], $oneEnded->value);
        $this->assertSame(['from' => '10', 'to' => '20'], $twoEnded->value);
    }

    public function testAListValueBecomesAnInFilter(): void
    {
        $region = $this->region(['state' => $this->column('state', filter: $this->filter(FilterOperator::In, 'multiselect'))]);

        $state = GridState::fromQuery(
            ['grid' => ['f' => ['state' => ['active', 'draft']]]],
            'grid',
            $region,
        );

        $this->assertEquals(
            [new FilterInput('state', ['active', 'draft'])],
            $state->filters,
        );
    }

    public function testAnEmptyFilterValueIsDroppedRatherThanMatchingEmptyString(): void
    {
        $region = $this->region(['state' => $this->column('state', filter: $this->filter(FilterOperator::Equals, 'select'))]);

        $state = GridState::fromQuery(['grid' => ['f' => ['state' => '']]], 'grid', $region);

        $this->assertSame([], $state->filters);
    }

    public function testSortIsReadWithItsDirection(): void
    {
        $region = $this->region(['created_at' => $this->column('created_at', sortable: true)]);

        $state = GridState::fromQuery(['grid' => ['sort' => '-created_at']], 'grid', $region);

        $this->assertEquals([new Sort('created_at', SortDirection::Desc)], $state->sort);
    }

    public function testSeveralSortsAreReadInOrder(): void
    {
        $region = $this->region([
            'state' => $this->column('state', sortable: true),
            'created_at' => $this->column('created_at', sortable: true),
        ]);

        $state = GridState::fromQuery(['grid' => ['sort' => 'state,-created_at']], 'grid', $region);

        $this->assertEquals(
            [new Sort('state', SortDirection::Asc), new Sort('created_at', SortDirection::Desc)],
            $state->sort,
        );
    }

    public function testASortNamingAnUnsortableColumnIsDropped(): void
    {
        $region = $this->region([
            'state' => $this->column('state', sortable: false),
            'created_at' => $this->column('created_at', sortable: true),
        ]);

        $state = GridState::fromQuery(['grid' => ['sort' => 'state,-created_at']], 'grid', $region);

        $this->assertEquals([new Sort('created_at', SortDirection::Desc)], $state->sort);
    }

    public function testTheRegionsOwnSortIsUsedWhenTheUrlCarriesNone(): void
    {
        $defaultSort = [new Sort('created_at', SortDirection::Desc)];
        $region = $this->region(
            ['created_at' => $this->column('created_at', sortable: true)],
            sort: $defaultSort,
        );

        $state = GridState::fromQuery([], 'grid', $region);

        $this->assertEquals($defaultSort, $state->sort);
    }

    public function testTheRegionsOwnSortIsUsedWhenEveryUrlSortIsInvalid(): void
    {
        // A stale bookmark whose sort names a since-renamed or since-removed
        // column must behave exactly like a URL that never named a sort at
        // all: drop what cannot be understood, then act as though it was
        // never there.
        $defaultSort = [new Sort('created_at', SortDirection::Desc)];
        $region = $this->region(
            ['created_at' => $this->column('created_at', sortable: true)],
            sort: $defaultSort,
        );

        $state = GridState::fromQuery(['grid' => ['sort' => 'bogus_column']], 'grid', $region);

        $this->assertEquals($defaultSort, $state->sort);
    }

    public function testPageBelowOneBecomesOne(): void
    {
        $region = $this->region(['title' => $this->column('title')]);

        $state = GridState::fromQuery(['grid' => ['page' => '0']], 'grid', $region);

        $this->assertSame(1, $state->page);

        $negative = GridState::fromQuery(['grid' => ['page' => '-5']], 'grid', $region);

        $this->assertSame(1, $negative->page);
    }

    public function testAPageThatIsNotANumberBecomesOne(): void
    {
        $region = $this->region(['title' => $this->column('title')]);

        $state = GridState::fromQuery(['grid' => ['page' => 'banana']], 'grid', $region);

        $this->assertSame(1, $state->page);
    }

    public function testAnotherRegionsParametersAreIgnored(): void
    {
        $region = $this->region(['title' => $this->column('title')], searchable: ['title']);

        $state = GridState::fromQuery(
            ['grid' => ['q' => 'bike'], 'detail' => ['q' => 'something else', 'page' => '9']],
            'grid',
            $region,
        );

        $this->assertSame('bike', $state->search);
        $this->assertSame(1, $state->page);
    }

    /** @return iterable<string, array{0: array<array-key, mixed>}> */
    public static function roundTripQueries(): iterable
    {
        yield 'a search term with spaces and diacritics' => [
            ['grid' => ['q' => 'café con leche']],
        ];
        yield 'a range with one end' => [
            ['grid' => ['f' => ['price' => ['from' => '10']]]],
        ];
        yield 'a list value' => [
            ['grid' => ['f' => ['state' => ['active', 'draft']]]],
        ];
        yield 'several sorts' => [
            ['grid' => ['sort' => 'state,-created_at']],
        ];
        yield 'a page beyond the first' => [
            ['grid' => ['page' => '3']],
        ];
        yield 'everything together' => [
            [
                'grid' => [
                    'q' => 'bike',
                    'f' => ['state' => ['active', 'draft'], 'price' => ['from' => '10', 'to' => '20']],
                    'sort' => 'state,-created_at',
                    'page' => '2',
                ],
            ],
        ];
        yield 'an empty query' => [[]];
    }

    /** @param array<array-key, mixed> $query */
    #[DataProvider('roundTripQueries')]
    public function testToQueryRoundTripsThroughFromQuery(array $query): void
    {
        $region = $this->region(
            [
                'title' => $this->column('title'),
                'state' => $this->column('state', sortable: true, filter: $this->filter(FilterOperator::In, 'multiselect')),
                'created_at' => $this->column('created_at', sortable: true),
                'price' => $this->column('price', filter: $this->filter(FilterOperator::Between, 'range')),
            ],
            searchable: ['title'],
        );

        $state = GridState::fromQuery($query, 'grid', $region);
        $roundTripped = GridState::fromQuery($state->toQuery('grid'), 'grid', $region);

        $this->assertEquals($state, $roundTripped);
    }

    public function testWithSortTogglesDirectionWhenTheColumnIsAlreadyTheSort(): void
    {
        $region = $this->region(['created_at' => $this->column('created_at', sortable: true)]);
        $state = GridState::fromQuery(['grid' => ['sort' => 'created_at']], 'grid', $region);

        $toggled = $state->withSort('created_at');

        $this->assertEquals([new Sort('created_at', SortDirection::Desc)], $toggled->sort);

        $toggledAgain = $toggled->withSort('created_at');

        $this->assertEquals([new Sort('created_at', SortDirection::Asc)], $toggledAgain->sort);
    }

    public function testWithSortReplacesTheSortRatherThanAppending(): void
    {
        $region = $this->region([
            'state' => $this->column('state', sortable: true),
            'created_at' => $this->column('created_at', sortable: true),
        ]);
        $state = GridState::fromQuery(['grid' => ['sort' => 'state']], 'grid', $region);

        $withNewSort = $state->withSort('created_at');

        $this->assertEquals([new Sort('created_at', SortDirection::Asc)], $withNewSort->sort);
    }

    public function testWithPageKeepsEverythingElse(): void
    {
        $region = $this->region(['title' => $this->column('title')], searchable: ['title']);
        $state = GridState::fromQuery(['grid' => ['q' => 'bike', 'page' => '2']], 'grid', $region);

        $withNewPage = $state->withPage(5);

        $this->assertSame('bike', $withNewPage->search);
        $this->assertSame(5, $withNewPage->page);
    }

    /** @return iterable<string, array{0: array<array-key, mixed>}> */
    public static function rubbishQueries(): iterable
    {
        yield 'the whole namespace is a scalar' => [['grid' => '5']];
        yield 'the page is an array' => [['grid' => ['page' => ['2']]]];
        yield 'the search term is an array' => [['grid' => ['q' => ['bike']]]];
        yield 'a filter value is doubly nested' => [['grid' => ['f' => ['state' => [['x']]]]]];
        yield 'the filter block itself is a scalar' => [['grid' => ['f' => 'state']]];
        yield 'the sort is an array' => [['grid' => ['sort' => ['created_at']]]];
        yield 'the filter namespace has a numeric key' => [['grid' => ['f' => ['nested']]]];
        yield 'the query itself is empty nested arrays' => [['grid' => ['f' => [], 'sort' => [], 'q' => [], 'page' => []]]];
    }

    /** @param array<array-key, mixed> $query */
    #[DataProvider('rubbishQueries')]
    public function testDeeplyNestedRubbishIsDroppedRatherThanCrashing(array $query): void
    {
        $region = $this->region(
            ['state' => $this->column('state', sortable: true, filter: $this->filter(FilterOperator::Equals, 'select'))],
            searchable: ['state'],
        );

        $state = GridState::fromQuery($query, 'grid', $region);

        $this->assertInstanceOf(GridState::class, $state);
        $this->assertSame(1, $state->page);
    }

    public function testIsEmptyIsTrueForAFreshState(): void
    {
        $region = $this->region(['title' => $this->column('title')]);

        $state = GridState::fromQuery([], 'grid', $region);

        $this->assertTrue($state->isEmpty());
    }

    public function testIsEmptyIsFalseOnceThereIsASearchTerm(): void
    {
        $region = $this->region(['title' => $this->column('title')], searchable: ['title']);

        $state = GridState::fromQuery(['grid' => ['q' => 'bike']], 'grid', $region);

        $this->assertFalse($state->isEmpty());
    }
}
