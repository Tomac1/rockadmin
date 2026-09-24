<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Enums;
use RockAdmin\Config\Placeholder;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\JoinType;
use RockAdmin\Db\Sort;
use RockAdmin\Db\SortDirection;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;
use RockAdmin\Page\PageException;
use RockAdmin\Page\PageRepository;
use RockAdmin\Page\RegionType;

#[CoversClass(PageRepository::class)]
final class PageRepositoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-pages-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    private function writePage(string $name, string $body): void
    {
        file_put_contents($this->root . '/pages/' . $name . '.php', "<?php\n\nreturn {$body};\n");
    }

    private function writeDefs(string $body): void
    {
        file_put_contents($this->root . '/defs.php', "<?php\n\nreturn {$body};\n");
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

    private function repository(int $defaultPerPage = 25, ?Enums $enums = null): PageRepository
    {
        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            $enums ?? Enums::fromConfig([]),
            $defaultPerPage,
        );
    }

    public function testAPageIsNamedByItsFile(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        $this->assertSame('ads', $this->repository()->get('ads')->name);
    }

    public function testTitleAndLayoutComeStraightFromTheFile(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'layout' => 'two-column',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        $page = $this->repository()->get('ads');

        $this->assertSame('Ads', $page->title);
        $this->assertSame('two-column', $page->layout);
    }

    public function testAColumnWithNoSourceReadsTheColumnOfItsOwnName(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        $column = $this->repository()->get('ads')->region('grid')->column('title');

        $this->assertSame('title', $column->source);
    }

    public function testAColumnWithNoLabelGetsOneMadeFromItsKey(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['created_at' => []]]],
            ]
            PHP);

        $column = $this->repository()->get('ads')->region('grid')->column('created_at');

        $this->assertSame('Created at', $column->label);
    }

    public function testALabelForAnAlreadyCapitalisedKeyIsLeftAlone(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['ID' => []]]],
            ]
            PHP);

        $column = $this->repository()->get('ads')->region('grid')->column('ID');

        $this->assertSame('ID', $column->label);
    }

    public function testALabelForAKeyWithADigitKeepsItInPlace(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['utm_source_2' => []]]],
            ]
            PHP);

        $column = $this->repository()->get('ads')->region('grid')->column('utm_source_2');

        $this->assertSame('Utm source 2', $column->label);
    }

    public function testAColumnInheritsItsTypesDefaultDisplay(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
                    'is_active' => ['type' => 'bool'],
                ]]],
            ]
            PHP);

        $column = $this->repository()->get('ads')->region('grid')->column('is_active');

        $this->assertSame(ColumnType::Bool, $column->type);
        $this->assertSame(Display::Check, $column->display);
    }

    public function testADisplayTheTypeRefusesIsAnErrorNamingBoth(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
                    'price' => ['type' => 'money', 'display' => 'progress'],
                ]]],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('A display the type refuses should be an error.');
        } catch (PageException $e) {
            $this->assertStringContainsString('price', $e->getMessage());
            $this->assertStringContainsString('money', $e->getMessage());
            $this->assertStringContainsString('progress', $e->getMessage());
        }
    }

    public function testAnUnknownColumnTypeNamesThePageAndColumn(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
                    'price' => ['type' => 'mony'],
                ]]],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('An unknown column type should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('ads', $e->getMessage());
            $this->assertStringContainsString('price', $e->getMessage());
            $this->assertStringContainsString("Did you mean 'money'", $e->getMessage());
        }
    }

    public function testAnUnknownDisplayNamesThePageAndColumn(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
                    'price' => ['display' => 'bagde'],
                ]]],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('An unknown display should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('ads', $e->getMessage());
            $this->assertStringContainsString('price', $e->getMessage());
            $this->assertStringContainsString('bagde', $e->getMessage());
        }
    }

    public function testAnUnresolvedEnumReferenceInOptionsIsRefusedNamingTheColumn(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
                    'state' => ['type' => 'enum', 'options' => '@enum:bogus'],
                ]]],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('An unresolved enum reference should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('ads', $e->getMessage());
            $this->assertStringContainsString('state', $e->getMessage());
            $this->assertStringContainsString('bogus', $e->getMessage());
        }
    }

    public function testColumnOptionsAcceptAMapWithAColorForABadge(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
                    'state' => [
                        'type' => 'enum',
                        'options' => ['active' => ['label' => 'Active', 'color' => 'success']],
                    ],
                ]]],
            ]
            PHP);

        $column = $this->repository()->get('ads')->region('grid')->column('state');

        /** @var array<string, \RockAdmin\Config\EnumOption> $enumOptions */
        $enumOptions = $column->options['enum'];

        $this->assertSame('Active', $enumOptions['active']->label);
        $this->assertSame('success', $enumOptions['active']->color);
    }

    public function testARegionWithAnUnknownTypeIsRefused(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'lis', 'columns' => ['title' => []]]],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('An unknown region type should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('ads', $e->getMessage());
            $this->assertStringContainsString('grid', $e->getMessage());
            $this->assertStringContainsString("Did you mean 'list'", $e->getMessage());
        }
    }

    public function testARelationDeclaredOnTheEntityBecomesARelationObject(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => [
                    'table' => 'ads',
                    'relations' => [
                        'user' => ['table' => 'users', 'on' => 'users.id = ads.user_id'],
                    ],
                ],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        $entity = $this->repository()->get('ads')->entity;

        $this->assertTrue($entity->hasRelation('user'));
        $relation = $entity->relation('user');
        $this->assertSame('users', $relation->table);
        $this->assertSame('users.id = ads.user_id', $relation->on);
        $this->assertSame(JoinType::Left, $relation->type);
    }

    public function testARelationMissingOnIsRefusedByTheGenericSchema(): void
    {
        // entity.relations now has a real per-entry schema (table/on/type),
        // so a missing 'on' is caught by the generic Validator before
        // PageRepository ever builds a Relation — proof that the hand-written
        // presence checks removed in this round were genuine duplication.
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => [
                    'table' => 'ads',
                    'relations' => [
                        'user' => ['table' => 'users'],
                    ],
                ],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('A relation missing on should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('entity.relations.user.on', $e->getMessage());
            $this->assertStringContainsString('Required', $e->getMessage());
        }
    }

    public function testARelationWithAnUnknownJoinTypeIsRefused(): void
    {
        // The schema only knows 'type' is a string; that it must be 'left'
        // or 'inner' is still PageRepository's job.
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => [
                    'table' => 'ads',
                    'relations' => [
                        'user' => ['table' => 'users', 'on' => 'users.id = ads.user_id', 'type' => 'outer'],
                    ],
                ],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('An unknown join type should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('user', $e->getMessage());
            $this->assertStringContainsString('outer', $e->getMessage());
        }
    }

    public function testARegionsSearchBlockHasARealShape(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => [
                    'type' => 'list',
                    'search' => ['placeholder' => 'Search ads...'],
                    'columns' => ['title' => []],
                ]],
            ]
            PHP);

        // This only proves a *valid* search block loads without error — it
        // cannot assert the value arrives anywhere, because RegionDefinition
        // exposes no field for it (out of this task's given interface; see
        // the task report). Paired with the two tests below, which assert
        // something a valid block cannot: that search's declared shape is
        // actually enforced, not merely tolerated — a wrong-typed
        // `placeholder` and an unknown key are both refused.
        $page = $this->repository()->get('ads');

        $this->assertSame('ads', $page->name);
    }

    public function testASearchPlaceholderMustBeAString(): void
    {
        // Proof that search's shape is a real, checked schema and not just
        // "any array is accepted": a wrong-typed value inside it is refused
        // the same way a wrong-typed value anywhere else in the page is.
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => [
                    'type' => 'list',
                    'search' => ['placeholder' => 123],
                    'columns' => ['title' => []],
                ]],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('A non-string search placeholder should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('search.placeholder', $e->getMessage());
            $this->assertStringContainsString('Expected string', $e->getMessage());
        }
    }

    public function testAnUnknownKeyInsideSearchIsRefused(): void
    {
        // search is no longer an opaque block: it has a real shape now
        // (`placeholder`), so a typo inside it is caught the same way any
        // other unknown key is.
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => [
                    'type' => 'list',
                    'search' => ['placeholderr' => 'Search ads...'],
                    'columns' => ['title' => []],
                ]],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('An unknown key inside search should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString("Did you mean 'placeholder'", $e->getMessage());
        }
    }

    public function testAColumnSourceNamingAnUndeclaredRelationIsRefused(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
                    'author' => ['source' => 'user.name'],
                ]]],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('An undeclared relation should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('author', $e->getMessage());
            $this->assertStringContainsString('user', $e->getMessage());
        }
    }

    public function testSharedDefinitionsAreExpanded(): void
    {
        $this->writeDefs(<<<'PHP'
            [
                'column' => [
                    'id' => ['type' => 'int', 'width' => '60px'],
                ],
            ]
            PHP);

        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
                    'id' => ['use' => '@column:id'],
                ]]],
            ]
            PHP);

        $column = $this->repository()->get('ads')->region('grid')->column('id');

        $this->assertSame(ColumnType::Int, $column->type);
        $this->assertSame('60px', $column->width);
    }

    public function testPlaceholdersInScopeSurviveAsPlaceholders(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => [
                    'table' => 'ads',
                    'scope' => ['site_id' => '{{workspace.site_id}}'],
                ],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        // A workspace placeholder binds per request and cannot be resolved at
        // load time; the loader must not choke on one buried inside a
        // freeform block such as scope, and must not try to validate its
        // project-chosen key against a fixed list. The data layer depends on
        // it arriving as a Placeholder object, not a string, so it can bind
        // it as a parameter later instead of interpolating it.
        $page = $this->repository()->get('ads');

        $this->assertArrayHasKey('site_id', $page->scope);
        $this->assertInstanceOf(Placeholder::class, $page->scope['site_id']);
        $this->assertSame('workspace', $page->scope['site_id']->namespace);
        $this->assertSame('site_id', $page->scope['site_id']->name);
    }

    public function testAnUnknownKeyInAPageFileIsRefusedAndSuggestsTheNearest(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'tilte' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('An unknown key should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('tilte', $e->getMessage());
            $this->assertStringContainsString("Did you mean 'title'", $e->getMessage());
        }
    }

    public function testAMissingTitleIsRefused(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('A missing title should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('title', $e->getMessage());
            $this->assertStringContainsString('Required', $e->getMessage());
        }
    }

    public function testPerPageFallsBackToTheRootConfigurationsValue(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        $region = $this->repository(defaultPerPage: 77)->get('ads')->region('grid');

        $this->assertSame(77, $region->perPage);
    }

    public function testARegionsOwnPerPageWins(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'per_page' => 10, 'columns' => ['title' => []]]],
            ]
            PHP);

        $region = $this->repository(defaultPerPage: 77)->get('ads')->region('grid');

        $this->assertSame(10, $region->perPage);
    }

    public function testAPerPageBelowOneIsRefusedAtLoadRatherThanOnEveryRequest(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'per_page' => 0, 'columns' => ['title' => []]]],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('A per_page of zero should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('grid', $e->getMessage());
            $this->assertStringContainsString('per_page', $e->getMessage());
        }
    }

    public function testANegativePerPageIsRefused(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'per_page' => -5, 'columns' => ['title' => []]]],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('A negative per_page should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('per_page', $e->getMessage());
        }
    }

    public function testSortIsReadAsAListOfSortObjects(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => [
                    'type' => 'list',
                    'sort' => ['created_at' => 'desc', 'id' => 'asc'],
                    'columns' => ['title' => []],
                ]],
            ]
            PHP);

        $region = $this->repository()->get('ads')->region('grid');

        $this->assertEquals(
            [new Sort('created_at', SortDirection::Desc), new Sort('id', SortDirection::Asc)],
            $region->sort,
        );
    }

    public function testSearchableColumnsAreCollectedForTheRegion(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
                    'title' => ['searchable' => true],
                    'id' => [],
                ]]],
            ]
            PHP);

        $region = $this->repository()->get('ads')->region('grid');

        $this->assertSame(['title'], $region->searchable);
    }

    public function testAFilterBlockBecomesAFilterDefinitionWithItsDefaultOperator(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
                    'title' => ['filter' => ['type' => 'text']],
                ]]],
            ]
            PHP);

        $filter = $this->repository()->get('ads')->region('grid')->column('title')->filter;

        $this->assertNotNull($filter);
        $this->assertSame('text', $filter->type);
        $this->assertSame(FilterOperator::Contains, $filter->operator);
    }

    public function testAFilterTypeChoosesItsOperatorWhenTheColumnDoesNotSayOne(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
                    'state' => ['type' => 'enum', 'options' => ['active' => 'Active'], 'filter' => ['type' => 'select']],
                ]]],
            ]
            PHP);

        $filter = $this->repository()->get('ads')->region('grid')->column('state')->filter;

        $this->assertNotNull($filter);
        $this->assertSame(FilterOperator::Equals, $filter->operator);
    }

    public function testASelectFilterInheritsTheColumnsEnumOptions(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
                    'state' => [
                        'type' => 'enum',
                        'options' => ['active' => 'Active', 'draft' => 'Draft'],
                        'filter' => ['type' => 'select'],
                    ],
                ]]],
            ]
            PHP);

        $filter = $this->repository()->get('ads')->region('grid')->column('state')->filter;

        $this->assertNotNull($filter);
        $this->assertSame(['active', 'draft'], array_keys($filter->options));
        $this->assertSame('Active', $filter->options['active']->label);
    }

    public function testAColumnWithNoFilterBlockIsNotFilterable(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        $column = $this->repository()->get('ads')->region('grid')->column('title');

        $this->assertNull($column->filter);
    }

    public function testACollectionBlockBecomesADbCollectionObject(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
                    'tags' => ['collection' => ['table' => 'ad_tags', 'foreign_key' => 'ad_id', 'column' => 'tag']],
                ]]],
            ]
            PHP);

        $column = $this->repository()->get('ads')->region('grid')->column('tags');

        $this->assertNotNull($column->collection);
        $this->assertSame('tags', $column->collection->alias);
        $this->assertSame('ad_tags', $column->collection->table);
        $this->assertSame('ad_id', $column->collection->foreignKey);
        $this->assertSame('tag', $column->collection->column);
    }

    public function testACollectionColumnCarryingASourceIsRefusedAsAContradiction(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
                    'tags' => [
                        'source' => 'tag_summary',
                        'collection' => ['table' => 'ad_tags', 'foreign_key' => 'ad_id', 'column' => 'tag'],
                    ],
                ]]],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('A collection column carrying a source should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('tags', $e->getMessage());
            $this->assertStringContainsString('source', $e->getMessage());
            $this->assertStringContainsString('collection', $e->getMessage());
        }
    }

    public function testAPageThatDoesNotExistIsRefusedByName(): void
    {
        try {
            $this->repository()->get('missing');
            $this->fail('A missing page should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('missing', $e->getMessage());
        }
    }

    public function testNamesListsEveryPageFileOnce(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);
        $this->writePage('users', <<<'PHP'
            [
                'title' => 'Users',
                'entity' => ['table' => 'users'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['name' => []]]],
            ]
            PHP);

        $this->assertSame(['ads', 'users'], $this->repository()->names());
    }

    public function testAPageIsReadFromDiskOnlyOnce(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        $repository = $this->repository();
        $first = $repository->get('ads');

        // Replacing the file with something that would fail to load proves
        // the second get() never touches the disk again.
        unlink($this->root . '/pages/ads.php');

        $second = $repository->get('ads');

        $this->assertSame($first, $second);
        $this->assertSame('Ads', $second->title);
    }

    public function testAMalformedOptionIsRefusedRatherThanQuietlyDropped(): void
    {
        // A mistyped 'lable' used to leave an option that simply never
        // appeared in a filter, with nothing anywhere saying why. Every other
        // malformed thing in a page file is refused by name.
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
                    'state' => ['type' => 'enum', 'options' => ['active' => ['lable' => 'Active']]],
                ]]],
            ]
            PHP);

        $this->expectException(PageException::class);
        $this->expectExceptionMessage("the option 'active' is neither a label nor");

        $this->repository()->get('ads');
    }

    public function testOptionsThatAreNotAMapAtAllAreRefused(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => [
                    'state' => ['type' => 'enum', 'options' => 42],
                ]]],
            ]
            PHP);

        $this->expectException(PageException::class);
        $this->expectExceptionMessage('options must be an @enum: reference');

        $this->repository()->get('ads');
    }

    public function testAFieldsListInheritsTheGridsColumnsWhenOmitted(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['title' => [], 'price' => ['type' => 'money']]],
                    'preview' => ['type' => 'preview'],
                ],
            ]
            PHP);

        $fields = $this->repository()->get('ads')->region('preview')->fields;

        $this->assertSame(['title', 'price'], array_map(static fn ($f) => $f->key, $fields));
    }

    public function testAnExplicitFieldsListIsUsedInItsOwnOrder(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['title' => [], 'price' => ['type' => 'money']]],
                    'preview' => ['type' => 'preview', 'fields' => ['price', 'title']],
                ],
            ]
            PHP);

        $fields = $this->repository()->get('ads')->region('preview')->fields;

        $this->assertSame(['price', 'title'], array_map(static fn ($f) => $f->key, $fields));
    }

    public function testAFieldNamingAnUndeclaredColumnIsRefusedAtLoad(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['title' => []]],
                    'preview' => ['type' => 'preview', 'fields' => ['price']],
                ],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('A field naming an undeclared column should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('ads', $e->getMessage());
            $this->assertStringContainsString('preview', $e->getMessage());
            $this->assertStringContainsString('price', $e->getMessage());
            $this->assertStringContainsString('grid', $e->getMessage());
        }
    }

    public function testAPreviewWithNoGridOnThePageAndNoFieldsOfItsOwnIsRefused(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => [
                    'preview' => ['type' => 'preview'],
                ],
            ]
            PHP);

        try {
            $this->repository()->get('ads');
            $this->fail('A preview with no grid to inherit from and no fields of its own should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('preview', $e->getMessage());
        }
    }

    public function testAnEmptyFieldListWarnsRatherThanFailing(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['title' => []]],
                    'preview' => ['type' => 'preview', 'fields' => []],
                ],
            ]
            PHP);

        $repository = $this->repository();
        $fields = $repository->get('ads')->region('preview')->fields;

        $this->assertSame([], $fields, 'legal, but shows nothing');
        $this->assertNotSame([], $repository->warnings(), 'an empty fields list is almost always a mistake');
        $this->assertStringContainsString('preview', $repository->warnings()[0]);
    }

    public function testARegionCarriesItsTypeAsTheClosedEnumNotAString(): void
    {
        // The enum exists to make the set closed; handing consumers back a
        // string would mean every one of them re-parses it, and the second
        // parser is always the one that forgets a case.
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        $this->assertSame(RegionType::List, $this->repository()->get('ads')->region('grid')->type);
    }
}
