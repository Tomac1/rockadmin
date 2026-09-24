<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testARangeOnAColumnThatDoesNotCompareByMagnitudeIsDropped(): void
    {
        // ?grid[f][title][from]=A&grid[f][title][to]=Z on a column declaring
        // 'contains'. A range is only reconcilable with an operator that
        // compares by magnitude -- between, gt, gte, lt, lte -- because a
        // URL may narrow what the page offered, never change its meaning
        // into something the page never declared. 'contains' has no notion
        // of "between", so the range must be dropped, not silently promoted
        // to one.
        $region = $this->region([
            'id' => $this->column('id'),
            'title' => $this->column('title', filter: $this->filter(FilterOperator::Contains, 'text')),
        ]);

        $query = (new QueryFactory())->build(
            $this->page(),
            $region,
            GridState::fromQuery(['grid' => ['f' => ['title' => ['from' => 'A', 'to' => 'Z']]]], 'grid', $region),
        );

        $this->assertSame([], $query->filters);
    }

    public function testARangeIsAcceptedForEveryMagnitudeComparingOperatorRegardlessOfWhichOneDeclaredIt(): void
    {
        // The ends are the more specific statement: whichever of the five
        // magnitude operators the column declared, a range with both ends
        // is 'between', one end is 'gte' or 'lte'.
        foreach ([
            FilterOperator::Between,
            FilterOperator::GreaterThan,
            FilterOperator::GreaterOrEqual,
            FilterOperator::LessThan,
            FilterOperator::LessOrEqual,
        ] as $operator) {
            $region = $this->region([
                'id' => $this->column('id'),
                'price' => $this->column('price', filter: $this->filter($operator, 'range')),
            ]);

            $both = (new QueryFactory())->build(
                $this->page(),
                $region,
                GridState::fromQuery(['grid' => ['f' => ['price' => ['from' => '10', 'to' => '20']]]], 'grid', $region),
            );
            $fromOnly = (new QueryFactory())->build(
                $this->page(),
                $region,
                GridState::fromQuery(['grid' => ['f' => ['price' => ['from' => '10']]]], 'grid', $region),
            );
            $toOnly = (new QueryFactory())->build(
                $this->page(),
                $region,
                GridState::fromQuery(['grid' => ['f' => ['price' => ['to' => '20']]]], 'grid', $region),
            );

            $this->assertEquals(
                [new Filter('price', FilterOperator::Between, ['10', '20'])],
                $both->filters,
                "declared as {$operator->value}",
            );
            $this->assertEquals(
                [new Filter('price', FilterOperator::GreaterOrEqual, '10')],
                $fromOnly->filters,
                "declared as {$operator->value}",
            );
            $this->assertEquals(
                [new Filter('price', FilterOperator::LessOrEqual, '20')],
                $toOnly->filters,
                "declared as {$operator->value}",
            );
        }
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
     * Every operator against every value shape a URL can produce, with the
     * exact Filter (or null, meaning "drop it") this class must produce.
     * Written out literally, one row per combination, rather than computed
     * from the same branching `toFilter()` uses — a computed expectation
     * shares whatever the implementation got wrong, which is exactly how an
     * earlier version of this test passed 78 green cases while missing that
     * a range reached `Between` regardless of the column's declared
     * operator. Repetitive on purpose: a table that is boring to read is one
     * nobody can reason wrongly about.
     *
     * @return iterable<string, array{0: FilterOperator, 1: string|list<string>|array{from?: string, to?: string}, 2: ?Filter}>
     */
    public static function filterMatrix(): iterable
    {
        $scalar = 'active';
        $listOfOne = ['active'];
        $listOfSeveral = ['active', 'draft'];
        $rangeBoth = ['from' => '10', 'to' => '20'];
        $rangeFromOnly = ['from' => '10'];
        $rangeToOnly = ['to' => '20'];

        // equals — a scalar or one-element list keeps 'equals'; several
        // values become 'in'; a range has no meaning for it.
        yield 'equals / scalar' => [FilterOperator::Equals, $scalar, new Filter('col', FilterOperator::Equals, 'active')];
        yield 'equals / list of one' => [FilterOperator::Equals, $listOfOne, new Filter('col', FilterOperator::Equals, 'active')];
        yield 'equals / list of several' => [FilterOperator::Equals, $listOfSeveral, new Filter('col', FilterOperator::In, ['active', 'draft'])];
        yield 'equals / range with both ends' => [FilterOperator::Equals, $rangeBoth, null];
        yield 'equals / range with only a from' => [FilterOperator::Equals, $rangeFromOnly, null];
        yield 'equals / range with only a to' => [FilterOperator::Equals, $rangeToOnly, null];

        // not_equals — like equals for one value, but a negation has no
        // "any of these" reading for several.
        yield 'not_equals / scalar' => [FilterOperator::NotEquals, $scalar, new Filter('col', FilterOperator::NotEquals, 'active')];
        yield 'not_equals / list of one' => [FilterOperator::NotEquals, $listOfOne, new Filter('col', FilterOperator::NotEquals, 'active')];
        yield 'not_equals / list of several' => [FilterOperator::NotEquals, $listOfSeveral, null];
        yield 'not_equals / range with both ends' => [FilterOperator::NotEquals, $rangeBoth, null];
        yield 'not_equals / range with only a from' => [FilterOperator::NotEquals, $rangeFromOnly, null];
        yield 'not_equals / range with only a to' => [FilterOperator::NotEquals, $rangeToOnly, null];

        // contains — a text match, same shape rules as not_equals.
        yield 'contains / scalar' => [FilterOperator::Contains, $scalar, new Filter('col', FilterOperator::Contains, 'active')];
        yield 'contains / list of one' => [FilterOperator::Contains, $listOfOne, new Filter('col', FilterOperator::Contains, 'active')];
        yield 'contains / list of several' => [FilterOperator::Contains, $listOfSeveral, null];
        yield 'contains / range with both ends' => [FilterOperator::Contains, $rangeBoth, null];
        yield 'contains / range with only a from' => [FilterOperator::Contains, $rangeFromOnly, null];
        yield 'contains / range with only a to' => [FilterOperator::Contains, $rangeToOnly, null];

        // starts_with — same shape rules as contains.
        yield 'starts_with / scalar' => [FilterOperator::StartsWith, $scalar, new Filter('col', FilterOperator::StartsWith, 'active')];
        yield 'starts_with / list of one' => [FilterOperator::StartsWith, $listOfOne, new Filter('col', FilterOperator::StartsWith, 'active')];
        yield 'starts_with / list of several' => [FilterOperator::StartsWith, $listOfSeveral, null];
        yield 'starts_with / range with both ends' => [FilterOperator::StartsWith, $rangeBoth, null];
        yield 'starts_with / range with only a from' => [FilterOperator::StartsWith, $rangeFromOnly, null];
        yield 'starts_with / range with only a to' => [FilterOperator::StartsWith, $rangeToOnly, null];

        // ends_with — same shape rules as contains.
        yield 'ends_with / scalar' => [FilterOperator::EndsWith, $scalar, new Filter('col', FilterOperator::EndsWith, 'active')];
        yield 'ends_with / list of one' => [FilterOperator::EndsWith, $listOfOne, new Filter('col', FilterOperator::EndsWith, 'active')];
        yield 'ends_with / list of several' => [FilterOperator::EndsWith, $listOfSeveral, null];
        yield 'ends_with / range with both ends' => [FilterOperator::EndsWith, $rangeBoth, null];
        yield 'ends_with / range with only a from' => [FilterOperator::EndsWith, $rangeFromOnly, null];
        yield 'ends_with / range with only a to' => [FilterOperator::EndsWith, $rangeToOnly, null];

        // gt — a magnitude comparison. A scalar or one-element list keeps
        // 'gt'; several values have no meaning and are dropped. A range is
        // always accepted, and the ends decide regardless of which of the
        // five magnitude operators declared it.
        yield 'gt / scalar' => [FilterOperator::GreaterThan, $scalar, new Filter('col', FilterOperator::GreaterThan, 'active')];
        yield 'gt / list of one' => [FilterOperator::GreaterThan, $listOfOne, new Filter('col', FilterOperator::GreaterThan, 'active')];
        yield 'gt / list of several' => [FilterOperator::GreaterThan, $listOfSeveral, null];
        yield 'gt / range with both ends' => [FilterOperator::GreaterThan, $rangeBoth, new Filter('col', FilterOperator::Between, ['10', '20'])];
        yield 'gt / range with only a from' => [FilterOperator::GreaterThan, $rangeFromOnly, new Filter('col', FilterOperator::GreaterOrEqual, '10')];
        yield 'gt / range with only a to' => [FilterOperator::GreaterThan, $rangeToOnly, new Filter('col', FilterOperator::LessOrEqual, '20')];

        // gte — same shape rules as gt.
        yield 'gte / scalar' => [FilterOperator::GreaterOrEqual, $scalar, new Filter('col', FilterOperator::GreaterOrEqual, 'active')];
        yield 'gte / list of one' => [FilterOperator::GreaterOrEqual, $listOfOne, new Filter('col', FilterOperator::GreaterOrEqual, 'active')];
        yield 'gte / list of several' => [FilterOperator::GreaterOrEqual, $listOfSeveral, null];
        yield 'gte / range with both ends' => [FilterOperator::GreaterOrEqual, $rangeBoth, new Filter('col', FilterOperator::Between, ['10', '20'])];
        yield 'gte / range with only a from' => [FilterOperator::GreaterOrEqual, $rangeFromOnly, new Filter('col', FilterOperator::GreaterOrEqual, '10')];
        yield 'gte / range with only a to' => [FilterOperator::GreaterOrEqual, $rangeToOnly, new Filter('col', FilterOperator::LessOrEqual, '20')];

        // lt — same shape rules as gt.
        yield 'lt / scalar' => [FilterOperator::LessThan, $scalar, new Filter('col', FilterOperator::LessThan, 'active')];
        yield 'lt / list of one' => [FilterOperator::LessThan, $listOfOne, new Filter('col', FilterOperator::LessThan, 'active')];
        yield 'lt / list of several' => [FilterOperator::LessThan, $listOfSeveral, null];
        yield 'lt / range with both ends' => [FilterOperator::LessThan, $rangeBoth, new Filter('col', FilterOperator::Between, ['10', '20'])];
        yield 'lt / range with only a from' => [FilterOperator::LessThan, $rangeFromOnly, new Filter('col', FilterOperator::GreaterOrEqual, '10')];
        yield 'lt / range with only a to' => [FilterOperator::LessThan, $rangeToOnly, new Filter('col', FilterOperator::LessOrEqual, '20')];

        // lte — same shape rules as gt.
        yield 'lte / scalar' => [FilterOperator::LessOrEqual, $scalar, new Filter('col', FilterOperator::LessOrEqual, 'active')];
        yield 'lte / list of one' => [FilterOperator::LessOrEqual, $listOfOne, new Filter('col', FilterOperator::LessOrEqual, 'active')];
        yield 'lte / list of several' => [FilterOperator::LessOrEqual, $listOfSeveral, null];
        yield 'lte / range with both ends' => [FilterOperator::LessOrEqual, $rangeBoth, new Filter('col', FilterOperator::Between, ['10', '20'])];
        yield 'lte / range with only a from' => [FilterOperator::LessOrEqual, $rangeFromOnly, new Filter('col', FilterOperator::GreaterOrEqual, '10')];
        yield 'lte / range with only a to' => [FilterOperator::LessOrEqual, $rangeToOnly, new Filter('col', FilterOperator::LessOrEqual, '20')];

        // between — a scalar or one-element list is dropped: one value is
        // not a range. Several values are dropped too. A range is accepted,
        // exactly as for the other four magnitude operators above.
        yield 'between / scalar' => [FilterOperator::Between, $scalar, null];
        yield 'between / list of one' => [FilterOperator::Between, $listOfOne, null];
        yield 'between / list of several' => [FilterOperator::Between, $listOfSeveral, null];
        yield 'between / range with both ends' => [FilterOperator::Between, $rangeBoth, new Filter('col', FilterOperator::Between, ['10', '20'])];
        yield 'between / range with only a from' => [FilterOperator::Between, $rangeFromOnly, new Filter('col', FilterOperator::GreaterOrEqual, '10')];
        yield 'between / range with only a to' => [FilterOperator::Between, $rangeToOnly, new Filter('col', FilterOperator::LessOrEqual, '20')];

        // in — any single value or list becomes 'in'; a range has no
        // meaning for it.
        yield 'in / scalar' => [FilterOperator::In, $scalar, new Filter('col', FilterOperator::In, ['active'])];
        yield 'in / list of one' => [FilterOperator::In, $listOfOne, new Filter('col', FilterOperator::In, ['active'])];
        yield 'in / list of several' => [FilterOperator::In, $listOfSeveral, new Filter('col', FilterOperator::In, ['active', 'draft'])];
        yield 'in / range with both ends' => [FilterOperator::In, $rangeBoth, null];
        yield 'in / range with only a from' => [FilterOperator::In, $rangeFromOnly, null];
        yield 'in / range with only a to' => [FilterOperator::In, $rangeToOnly, null];

        // is_null — the value is ignored entirely, whatever shape it takes.
        yield 'is_null / scalar' => [FilterOperator::IsNull, $scalar, new Filter('col', FilterOperator::IsNull)];
        yield 'is_null / list of one' => [FilterOperator::IsNull, $listOfOne, new Filter('col', FilterOperator::IsNull)];
        yield 'is_null / list of several' => [FilterOperator::IsNull, $listOfSeveral, new Filter('col', FilterOperator::IsNull)];
        yield 'is_null / range with both ends' => [FilterOperator::IsNull, $rangeBoth, new Filter('col', FilterOperator::IsNull)];
        yield 'is_null / range with only a from' => [FilterOperator::IsNull, $rangeFromOnly, new Filter('col', FilterOperator::IsNull)];
        yield 'is_null / range with only a to' => [FilterOperator::IsNull, $rangeToOnly, new Filter('col', FilterOperator::IsNull)];

        // is_not_null — same as is_null.
        yield 'is_not_null / scalar' => [FilterOperator::IsNotNull, $scalar, new Filter('col', FilterOperator::IsNotNull)];
        yield 'is_not_null / list of one' => [FilterOperator::IsNotNull, $listOfOne, new Filter('col', FilterOperator::IsNotNull)];
        yield 'is_not_null / list of several' => [FilterOperator::IsNotNull, $listOfSeveral, new Filter('col', FilterOperator::IsNotNull)];
        yield 'is_not_null / range with both ends' => [FilterOperator::IsNotNull, $rangeBoth, new Filter('col', FilterOperator::IsNotNull)];
        yield 'is_not_null / range with only a from' => [FilterOperator::IsNotNull, $rangeFromOnly, new Filter('col', FilterOperator::IsNotNull)];
        yield 'is_not_null / range with only a to' => [FilterOperator::IsNotNull, $rangeToOnly, new Filter('col', FilterOperator::IsNotNull)];
    }

    /** @param string|list<string>|array{from?: string, to?: string} $value */
    #[DataProvider('filterMatrix')]
    public function testEveryOperatorAgainstEveryValueShape(FilterOperator $operator, string|array $value, ?Filter $expected): void
    {
        $this->assertEquals($expected, $this->filterFor($operator, $value));
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
