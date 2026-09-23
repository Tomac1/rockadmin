<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Placeholder;
use RockAdmin\Db\Collection;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Filter;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\Sort;
use RockAdmin\Db\SortDirection;
use RockAdmin\Grid\GridState;
use RockAdmin\Grid\QueryFactory;
use RockAdmin\Page\ColumnDefinition;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;
use RockAdmin\Page\FilterDefinition;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\RegionDefinition;
use RockAdmin\Page\RegionType;

#[CoversClass(QueryFactory::class)]
final class QueryFactoryTest extends TestCase
{
    /** @param array<string, mixed> $scope */
    private function page(array $scope = [], Entity $entity = new Entity('ra_test_ads', 'id')): PageDefinition
    {
        return new PageDefinition(
            'ads',
            'Ads',
            'default',
            'Ads',
            $entity,
            $scope,
            [],
        );
    }

    /**
     * @param array<string, ColumnDefinition> $columns
     * @param array<string, string>           $sort       column key => 'asc'|'desc'
     * @param list<string>                    $searchable
     */
    private function region(
        array $columns = [],
        array $sort = [],
        array $searchable = [],
        int $perPage = 20,
    ): RegionDefinition {
        $sortList = [];

        foreach ($sort as $column => $direction) {
            $sortList[] = new Sort($column, $direction === 'desc' ? SortDirection::Desc : SortDirection::Asc);
        }

        if ($columns === []) {
            $columns = [
                'id' => $this->column('id'),
                'title' => $this->column('title'),
                'created_at' => $this->column('created_at', sortable: true),
            ];
        }

        return new RegionDefinition('grid', RegionType::List, $perPage, $columns, $sortList, $searchable);
    }

    private function column(
        string $key,
        string $source = '',
        bool $sortable = false,
        ?FilterDefinition $filter = null,
        ?Collection $collection = null,
    ): ColumnDefinition {
        return new ColumnDefinition(
            filter: $filter,
            collection: $collection,
            key: $key,
            label: ucfirst($key),
            source: $source === '' ? $key : $source,
            type: ColumnType::Text,
            display: Display::Plain,
            sortable: $sortable,
            link: false,
            align: 'start',
            width: null,
            class: '',
        );
    }

    private function filter(FilterOperator $operator, string $type = 'text'): FilterDefinition
    {
        return new FilterDefinition($type, $operator, ucfirst($type));
    }

    public function testEveryColumnBecomesASelectKeyedByItsKey(): void
    {
        $region = $this->region([
            'id' => $this->column('id'),
            'title' => $this->column('title'),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $this->assertSame(['id' => 'id', 'title' => 'title'], $query->columns);
    }

    public function testAColumnSourceCrossingARelationIsPassedThroughUntouched(): void
    {
        $region = $this->region([
            'id' => $this->column('id'),
            'user_name' => $this->column('user_name', source: 'user.name'),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $this->assertSame('user.name', $query->columns['user_name']);
    }

    public function testTheEntityScopeBecomesAFilter(): void
    {
        $region = $this->region();

        $query = (new QueryFactory())->build(
            $this->page(['company_id' => 1]),
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $this->assertSame(['company_id' => 1], $query->scope);
    }

    public function testAScopeFilterSurvivesEvenWhenTheUrlFiltersTheSameColumn(): void
    {
        $region = $this->region([
            'id' => $this->column('id'),
            'company_id' => $this->column('company_id', filter: $this->filter(FilterOperator::Equals, 'select')),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(['company_id' => 1]),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['company_id' => '2']]], 'grid', $region),
        );

        $this->assertSame(['company_id' => 1], $query->scope, 'the scope value is untouched');
        $this->assertEquals(
            [new Filter('company_id', FilterOperator::Equals, '2')],
            $query->filters,
            'the url filter is not discarded either — both apply, ANDed',
        );
    }

    public function testAScopeValueThatIsAnUnresolvedPlaceholderIsPassedThroughUntouched(): void
    {
        $region = $this->region();
        $placeholder = new Placeholder('workspace', 'site_id');

        $query = (new QueryFactory())->build(
            $this->page(['site_id' => $placeholder]),
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $this->assertSame($placeholder, $query->scope['site_id']);
    }

    public function testAFilterUsesTheOperatorItsColumnDeclared(): void
    {
        $region = $this->region([
            'id' => $this->column('id'),
            'state' => $this->column('state', filter: $this->filter(FilterOperator::Equals, 'select')),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['state' => 'active']]], 'grid', $region),
        );

        $this->assertEquals([new Filter('state', FilterOperator::Equals, 'active')], $query->filters);
    }

    public function testARangeWithBothEndsBecomesBetween(): void
    {
        $region = $this->region([
            'id' => $this->column('id'),
            'price' => $this->column('price', filter: $this->filter(FilterOperator::Between, 'range')),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['price' => ['from' => '10', 'to' => '20']]]], 'grid', $region),
        );

        $this->assertEquals(
            [new Filter('price', FilterOperator::Between, ['10', '20'])],
            $query->filters,
        );
    }

    public function testARangeWithOnlyAFromBecomesGreaterOrEqual(): void
    {
        $region = $this->region([
            'id' => $this->column('id'),
            'price' => $this->column('price', filter: $this->filter(FilterOperator::Between, 'range')),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['price' => ['from' => '10']]]], 'grid', $region),
        );

        $this->assertEquals(
            [new Filter('price', FilterOperator::GreaterOrEqual, '10')],
            $query->filters,
        );
    }

    public function testARangeWithOnlyAToBecomesLessOrEqual(): void
    {
        $region = $this->region([
            'id' => $this->column('id'),
            'price' => $this->column('price', filter: $this->filter(FilterOperator::Between, 'range')),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['price' => ['to' => '20']]]], 'grid', $region),
        );

        $this->assertEquals(
            [new Filter('price', FilterOperator::LessOrEqual, '20')],
            $query->filters,
        );
    }

    public function testAListValueBecomesAnInFilterWithTheColumnsDeclaredOperator(): void
    {
        $region = $this->region([
            'id' => $this->column('id'),
            'state' => $this->column('state', filter: $this->filter(FilterOperator::In, 'multiselect')),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['state' => ['active', 'draft']]]], 'grid', $region),
        );

        $this->assertEquals(
            [new Filter('state', FilterOperator::In, ['active', 'draft'])],
            $query->filters,
        );
    }

    // --- Shape-vs-operator reconciliation --------------------------------
    //
    // Every FilterInput's value shape only tells us what the URL sent, not
    // what the column's FilterDefinition expects. A shape that does not
    // match the declared operator must never reach QueryBuilder unguarded:
    // one, the builder throws (a 500 from a link anyone can type); or worse,
    // an array is silently coerced to a string and the filter matches
    // nothing while looking like it ran.

    public function testAScalarWithInBecomesAOneElementList(): void
    {
        // ?grid[f][state]=active on a column declaring 'in'. Before this fix,
        // FilterOperator::In reached the builder with a bare string and blew
        // up with "An 'in' filter needs a list of values."
        $region = $this->region([
            'id' => $this->column('id'),
            'state' => $this->column('state', filter: $this->filter(FilterOperator::In, 'multiselect')),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['state' => 'active']]], 'grid', $region),
        );

        $this->assertEquals([new Filter('state', FilterOperator::In, ['active'])], $query->filters);
    }

    public function testAScalarWithBetweenIsDropped(): void
    {
        // ?grid[f][price]=15 on a column declaring 'between'. One value is
        // not a range, and the builder throws "needs exactly two values" if
        // it reaches it.
        $region = $this->region([
            'id' => $this->column('id'),
            'price' => $this->column('price', filter: $this->filter(FilterOperator::Between, 'range')),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['price' => '15']]], 'grid', $region),
        );

        $this->assertSame([], $query->filters);
    }

    public function testAListOfExactlyOneUnwrapsToItsScalarThenFollowsTheScalarRules(): void
    {
        // ?grid[f][state][]=active on a column declaring 'equals'. This is
        // the worst of the three bugs the review found: the array used to
        // reach PDO, become the literal string "Array", and match nothing
        // while raising nothing — a filter that looks like it ran but
        // silently excludes every row.
        $region = $this->region([
            'id' => $this->column('id'),
            'state' => $this->column('state', filter: $this->filter(FilterOperator::Equals, 'select')),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['state' => ['active']]]], 'grid', $region),
        );

        $this->assertEquals([new Filter('state', FilterOperator::Equals, 'active')], $query->filters);
    }

    public function testAListOfSeveralWithEqualsBecomesIn(): void
    {
        $region = $this->region([
            'id' => $this->column('id'),
            'state' => $this->column('state', filter: $this->filter(FilterOperator::Equals, 'select')),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['state' => ['active', 'draft']]]], 'grid', $region),
        );

        $this->assertEquals(
            [new Filter('state', FilterOperator::In, ['active', 'draft'])],
            $query->filters,
        );
    }

    public function testAListOfSeveralWithAnyOtherOperatorIsDropped(): void
    {
        // "Contains any of these" is not something the builder expresses,
        // and guessing would be worse than dropping the filter.
        $region = $this->region([
            'id' => $this->column('id'),
            'title' => $this->column('title', filter: $this->filter(FilterOperator::Contains, 'text')),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['title' => ['bike', 'moto']]]], 'grid', $region),
        );

        $this->assertSame([], $query->filters);
    }

    public function testIsNullIgnoresTheValueRegardlessOfShape(): void
    {
        $region = $this->region([
            'id' => $this->column('id'),
            'deleted_at' => $this->column('deleted_at', filter: $this->filter(FilterOperator::IsNull, 'boolean')),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['deleted_at' => 'whatever']]], 'grid', $region),
        );

        $this->assertEquals([new Filter('deleted_at', FilterOperator::IsNull)], $query->filters);
    }

    public function testIsNotNullIgnoresARangeShapedValue(): void
    {
        $region = $this->region([
            'id' => $this->column('id'),
            'deleted_at' => $this->column('deleted_at', filter: $this->filter(FilterOperator::IsNotNull, 'boolean')),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['deleted_at' => ['from' => '2020-01-01']]]], 'grid', $region),
        );

        $this->assertEquals([new Filter('deleted_at', FilterOperator::IsNotNull)], $query->filters);
    }

    /**
     * Every FilterOperator against every value shape a URL can produce: a
     * future operator cannot be added without deciding what each shape means
     * for it, because this test will fail until it does.
     */
    public function testEveryOperatorReconcilesEveryValueShapeOrDropsTheFilter(): void
    {
        $shapes = [
            'a scalar' => 'active',
            'a list of one' => ['active'],
            'a list of several' => ['active', 'draft'],
            'a range with both ends' => ['from' => '10', 'to' => '20'],
            'a range with only a from' => ['from' => '10'],
            'a range with only a to' => ['to' => '20'],
        ];

        foreach (FilterOperator::cases() as $operator) {
            foreach ($shapes as $label => $value) {
                $this->assertEquals(
                    $this->expectedFilterFor($operator, $value),
                    $this->filterFor($operator, $value),
                    "operator '{$operator->value}' against {$label}",
                );
            }
        }
    }

    /** @param string|list<string>|array{from?: string, to?: string} $value */
    private function filterFor(FilterOperator $operator, string|array $value): ?Filter
    {
        $region = $this->region([
            'id' => $this->column('id'),
            'col' => $this->column('col', filter: $this->filter($operator)),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['col' => $value]]], 'grid', $region),
        );

        return $query->filters[0] ?? null;
    }

    /** @param string|list<string>|array{from?: string, to?: string} $value */
    private function expectedFilterFor(FilterOperator $operator, string|array $value): ?Filter
    {
        if ($operator === FilterOperator::IsNull || $operator === FilterOperator::IsNotNull) {
            return new Filter('col', $operator);
        }

        if (\is_array($value) && !array_is_list($value)) {
            $from = $value['from'] ?? null;
            $to = $value['to'] ?? null;

            return match (true) {
                $from !== null && $to !== null => new Filter('col', FilterOperator::Between, [$from, $to]),
                $from !== null => new Filter('col', FilterOperator::GreaterOrEqual, $from),
                $to !== null => new Filter('col', FilterOperator::LessOrEqual, $to),
                default => null,
            };
        }

        $values = \is_array($value) ? $value : [$value];

        if (\count($values) === 1) {
            return match ($operator) {
                FilterOperator::Between => null,
                FilterOperator::In => new Filter('col', FilterOperator::In, [$values[0]]),
                default => new Filter('col', $operator, $values[0]),
            };
        }

        return match ($operator) {
            FilterOperator::In, FilterOperator::Equals => new Filter('col', FilterOperator::In, $values),
            default => null,
        };
    }

    public function testSearchCoversExactlyTheSearchableColumnsSourcePaths(): void
    {
        $region = $this->region(
            [
                'id' => $this->column('id'),
                'title' => $this->column('title'),
                'user_name' => $this->column('user_name', source: 'user.name'),
            ],
            searchable: ['title', 'user_name'],
        );

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['q' => 'bike']], 'grid', $region),
        );

        $this->assertNotNull($query->search);
        $this->assertSame('bike', $query->search->term);
        $this->assertSame(['title', 'user_name'], $query->search->columns);
    }

    public function testThereIsNoSearchWhenTheTermIsEmpty(): void
    {
        $region = $this->region(
            ['id' => $this->column('id'), 'title' => $this->column('title')],
            searchable: ['title'],
        );

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $this->assertNull($query->search);
    }

    public function testTheEntityKeyIsAlwaysTheLastSort(): void
    {
        // Without a tiebreaker, two rows sharing a created_at can swap between
        // page one and page two, and one of them is never shown to anyone. This
        // is not a preference; it is the difference between a pager that works
        // and one that loses rows.
        $region = $this->region(sort: ['created_at' => 'desc']);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery([], 'grid', $this->region(sort: ['created_at' => 'desc'])),
        );

        $columns = array_map(static fn (Sort $sort): string => $sort->column, $query->sort);

        $this->assertSame(['created_at', 'id'], $columns);
    }

    public function testTheEntityKeyIsNotAddedTwiceWhenItIsAlreadyTheSort(): void
    {
        $region = $this->region(sort: ['id' => 'asc']);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $columns = array_map(static fn (Sort $sort): string => $sort->column, $query->sort);

        $this->assertSame(['id'], $columns);
    }

    public function testPerPageIsClampedSoAUrlCannotAskForEverything(): void
    {
        $region = $this->region(perPage: 100_000);

        $query = (new QueryFactory(maxPerPage: 200))->build(
            $this->page(),
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $this->assertNotNull($query->page);
        $this->assertSame(200, $query->page->limit);
    }

    public function testPerPageHasALowerBoundToo(): void
    {
        // PageRepository already refuses a per_page below 1 at load time, but
        // a RegionDefinition can be built directly -- in a test, or by a
        // future caller -- bypassing that guard entirely. Page::of() throws
        // on anything less than one, and a 500 is a poor answer to a bad
        // number reaching this far.
        $region = $this->region(perPage: 0);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $this->assertNotNull($query->page);
        $this->assertSame(1, $query->page->limit);
    }

    public function testANegativePerPageIsAlsoClampedToOne(): void
    {
        $region = $this->region(perPage: -5);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $this->assertNotNull($query->page);
        $this->assertSame(1, $query->page->limit);
    }

    public function testPageThreeBecomesTheRightOffset(): void
    {
        $region = $this->region(perPage: 20);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['page' => '3']], 'grid', $region),
        );

        $this->assertNotNull($query->page);
        $this->assertSame(20, $query->page->limit);
        $this->assertSame(40, $query->page->offset);
    }

    public function testTheRegionsOwnSortIsUsedWhenTheStateHasNone(): void
    {
        $region = $this->region(sort: ['created_at' => 'desc']);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $this->assertEquals(
            [new Sort('created_at', SortDirection::Desc), new Sort('id')],
            $query->sort,
        );
    }

    public function testAColumnCarryingACollectionIsNotInTheSelectList(): void
    {
        $collection = new Collection('tags', 'ra_test_tags', 'ad_id', 'label');
        $region = $this->region([
            'id' => $this->column('id'),
            'title' => $this->column('title'),
            'tags' => $this->column('tags', collection: $collection),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $this->assertArrayNotHasKey('tags', $query->columns);
    }

    public function testEveryCollectionColumnReachesTheQuerysCollections(): void
    {
        $collection = new Collection('tags', 'ra_test_tags', 'ad_id', 'label');
        $region = $this->region([
            'id' => $this->column('id'),
            'title' => $this->column('title'),
            'tags' => $this->column('tags', collection: $collection),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $this->assertSame([$collection], $query->collections);
    }

    public function testTheCountStrategyIsAlwaysExact(): void
    {
        $region = $this->region();

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery([], 'grid', $region),
        );

        $this->assertSame(\RockAdmin\Db\CountStrategy::Exact, $query->count);
    }
}
