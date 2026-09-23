# Milestone 6 — The list region Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn a page described in configuration into a working datagrid —
columns, filters, search, sorting, paging — rendered as a page and
re-renderable as a fragment, over MySQL and PostgreSQL alike.

**Architecture:** Configuration describes a page; nothing else does. A
`PageRepository` loads `config/rockadmin/pages/*.php` through the same
pipeline the root configuration already uses — shared definitions expanded,
placeholders resolved, validated against a schema, defaults applied — and
hands back immutable `PageDefinition` objects. A `GridState` reads the URL
into filters, a search term, a sort and a page number, discarding anything
that names a column the page did not select. A `QueryFactory` turns
definition plus state into the `RockAdmin\Db\Query` milestone 3 already knows
how to execute. A `ListRegion` runs that query through a `RowSource` and
produces a `ListView` of prepared rows and cells, which the templates from
milestone 4 render. Two handlers reach it: one renders the whole page, one
renders the region alone as an HTML fragment, and `core.js` swaps that
fragment in without a reload.

**Tech Stack:** PHP 8.4, plain PHP templates, Bootstrap 5.3 (vendored),
PDO over MySQL and PostgreSQL, PHPUnit 11, PHPStan level max.

**Spec:** [`docs/design/2026-09-21-rockadmin-design.md`](../design/2026-09-21-rockadmin-design.md) — sections 6.2 and 6.3 (what a page looks like and why keys are identity), 7 (the data layer this consumes), 8.4 (regions), 8.11 (grid state and shareable URLs), 12 (what the dev console will later read).

## Global Constraints

Copied verbatim from the spec and `CLAUDE.md`. Every task's requirements
implicitly include this section.

- **No runtime dependencies.** `composer.json` requires exactly `php`,
  `ext-pdo`, `ext-json`, `ext-mbstring`. Never add a package.
- **No framework coupling.** The core never mentions Laravel, Symfony or any
  other framework.
- **MySQL and PostgreSQL both work, from the same configuration.** Database
  differences live in `Dialect` and never surface in configuration.
- **No layer reaches two levels down.** Regions do not build SQL — they use a
  `RowSource`. Templates receive a prepared view object, never the database
  or the configuration.
- **Every configuration key exists in the schema**, with type, default,
  description and example. `ReferenceIsCurrentTest` fails the build when
  `docs/reference/` and the schema disagree, so a new key means running
  `composer run docs:reference` and committing the result.
- **Template rules.** Every element carries its `ra-` structural and identity
  classes. No `<script>` with a body; behaviour is declared with `data-ra-*`
  attributes and bound by delegation, so it survives fragment insertion. No
  hardcoded URLs. Every `<?=` escapes, and every `href`, `src` and `action`
  uses `$href(`. `TemplateStandardsTest` enforces all of it.
- **No query inside a row loop.** Related columns are JOINs; one-to-many
  values are fetched once for the whole page of rows. `Result::$statements`
  is what proves it.
- **GET is always safe.** `/p/` and `/r/` render and never mutate.
- **PHP 8.4**, `declare(strict_types=1)` in every file, PHPStan level max
  clean, coding standards clean. The gate is `composer run check`, and a
  commit with a red gate is never the end of a task.
- **English** for all code, comments, documentation and commit messages.
- config keys `snake_case`; classes `PascalCase`; methods and variables
  `camelCase`; CSS classes `kebab-case` with an `ra-` prefix; data attributes
  `kebab-case`.

## Decisions this plan makes

**A page is configuration, loaded like configuration.** `PageRepository`
reuses `Definitions`, `Resolver`, `Validator` and `Defaults` rather than
growing a second loading path. A page file therefore gets `['use' => '@column:id']`,
`{{env.*}}`, `@enum:` references and unknown-key refusal for free, and
anything learned about loading applies to both.

**One page file, one page, named by its file.** `pages/ads.php` is the page
`ads`, reachable at `/p/ads`. No `name` key: a key that must agree with a
filename is a key that will eventually disagree with it.

**The grid's state lives in the URL and nowhere else.** Namespaced per region
(`grid[q]`, `grid[f][state]`, `grid[sort]`, `grid[page]`), so a page with two
list regions keeps them apart. `remember_state` from spec 8.11 is deferred —
it needs a session policy this milestone does not otherwise touch.

**Unknown state is discarded; unknown configuration is an error.** A filter
naming a column the page does not select is dropped silently, because it
arrives from a URL a stranger can write. A column naming a relation the page
did not declare fails at load, because a person wrote it. This is milestone
3's rule, restated one layer up.

**A column's key is its identity.** It names the URL parameter, the CSS
class, the template override path and the permission it will later need. The
label is decoration. A column with no explicit `source` reads the column of
the same name on the entity's own table.

**Cells are rendered by type and display, and both are closed sets.** `type`
says what the value *is* (text, int, money, datetime, bool, enum, json);
`display` says how it should *look* (plain, badge, check, yesno, progress,
percent, link). An unknown type or display is refused at load, so a typo
cannot silently render as text.

**The `actions` column type is not in this milestone.** Spec 8.5 builds
actions on top of routes that mutate, and mutation is milestone 7. A column
may carry `link`, which makes its cell an anchor to the row's detail page, so
a grid is navigable without waiting for actions.

**Fragments re-render one region.** `/r/{page}/{region}` returns the region's
HTML and nothing else, with the state parameters it was given. `core.js`
replaces the region element, re-runs `attach()` and calls `pushState`, so the
back button and a copied URL both work.

## Test environment

This milestone's integration tests use the fixture tables
`DatabaseTestCase::createFixtures()` already creates — `ra_test_companies`,
`ra_test_users`, `ra_test_ads`, `ra_test_tags` — which happen to be exactly
the model spec 6.2 uses as its example: ads belonging to users belonging to
companies, with a JSON `stats` column, an enum-ish `state`, and tags as a
one-to-many. Every integration test runs once per configured driver and skips
when neither `RA_TEST_MYSQL_*` nor `RA_TEST_PGSQL_*` is set.

Local development uses MySQL on localhost, database `rockadmin_test`, user
`root`, password `root`.

## File Structure

**Created:**

```
src/Page/PageSchema.php          the schema a page file is validated against
src/Page/ColumnSchema.php        one column's keys, shared by every column type
src/Page/PageRepository.php      loads pages/*.php, validates, caches per request
src/Page/PageDefinition.php      one page, as the rest of the code reads it
src/Page/RegionDefinition.php    one region's slice of a page
src/Page/ColumnDefinition.php    one column: key, label, type, display, source
src/Page/FilterDefinition.php    one column's filter control and its operator
src/Page/ColumnType.php          text, int, money, datetime, bool, enum, json
src/Page/Display.php             plain, badge, check, yesno, progress, percent, link
src/Page/PageException.php       every failure this namespace raises
src/Grid/GridState.php           the URL's filters, search, sort and page
src/Grid/FilterInput.php         one filter as it arrived, before it is trusted
src/Grid/QueryFactory.php        definition + state -> RockAdmin\Db\Query
src/Grid/ListRegion.php          runs the query, builds the view
src/Grid/ListView.php            what the list templates read
src/Grid/ColumnView.php          one header: label, classes, sort link
src/Grid/RowView.php             one row, with its cells and its identity
src/Grid/CellView.php            one cell: value, formatted text, classes, link
src/Grid/PaginationView.php      pages, current page, total, page links
src/Grid/FilterView.php          one filter control, with its current value
src/Http/PageHandler.php         GET /p/{page}
src/Http/RegionHandler.php       GET /r/{page}/{region}
templates/region/list/region.php    toolbar.php filters.php table.php head.php
                                    body.php row.php empty.php pagination.php
templates/region/list/cell/text.php int.php money.php datetime.php bool.php
                                    enum.php json.php link.php
```

**Modified:**

- `assets/js/core.js` — fragment loading, region refresh, `pushState`
- `src/Config/RootSchema.php` — `pages_path`, `per_page`
- `bin/generate-reference.php` — a second reference file for the page schema
- `demo/` — a real grid over the fixture tables
- `README.md` — what the demo now shows

---

### Task 1: What a page may say

The schema a page file is validated against, and the two closed sets a column
chooses from. Nothing loads yet — this task declares the vocabulary.

**Files:**
- Create: `src/Page/PageException.php`
- Create: `src/Page/ColumnType.php`
- Create: `src/Page/Display.php`
- Create: `src/Page/ColumnSchema.php`
- Create: `src/Page/PageSchema.php`
- Test: `tests/Unit/Page/ColumnTypeTest.php`
- Test: `tests/Unit/Page/PageSchemaTest.php`

**Interfaces:**
- Consumes: `RockAdmin\Config\Schema`, `SchemaKey`, `ValueType` — read
  `src/Config/RootSchema.php` first, it is the worked example of building one.
- Produces:
  ```php
  namespace RockAdmin\Page;

  final class PageException extends \RuntimeException {}

  enum ColumnType: string {
      case Text = 'text'; case Int = 'int'; case Money = 'money';
      case Datetime = 'datetime'; case Bool = 'bool'; case Enum = 'enum';
      case Json = 'json';
      public static function parse(string $value): self;     // refuses with PageException
      public function defaultDisplay(): Display;
      public function allows(Display $display): bool;
      public function defaultAlignment(): string;            // 'start' or 'end'
  }

  enum Display: string {
      case Plain = 'plain'; case Badge = 'badge'; case Check = 'check';
      case YesNo = 'yesno'; case Progress = 'progress';
      case Percent = 'percent'; case Link = 'link';
  }

  final class ColumnSchema { public static function create(): Schema; }
  final class PageSchema   { public static function create(): Schema; }
  ```

**The two closed sets, and which pairs are legal.** A type says what a value
is; a display says how it looks. Not every pair means anything, and a pair
that means nothing is a configuration error rather than a rendering surprise:

| Type | Default display | Also allows |
|---|---|---|
| `text` | `plain` | `badge`, `link` |
| `int` | `plain` | `progress`, `percent`, `badge`, `link` |
| `money` | `plain` | — |
| `datetime` | `plain` | — |
| `bool` | `check` | `yesno`, `badge` |
| `enum` | `badge` | `plain` |
| `json` | `plain` | — |

`money`, `int` and `percent` align to the end of the cell; everything else to
the start. Numbers are compared down a column by eye, and a ragged right edge
makes that impossible.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Page/ColumnTypeTest.php`:

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;
use RockAdmin\Page\PageException;

#[CoversClass(ColumnType::class)]
#[CoversClass(Display::class)]
final class ColumnTypeTest extends TestCase
{
    public function testEveryTypeHasADefaultDisplayItAllows(): void
    {
        // A default the type itself rejects would be unreachable nonsense.
        foreach (ColumnType::cases() as $type) {
            $this->assertTrue(
                $type->allows($type->defaultDisplay()),
                "{$type->value} defaults to a display it does not allow.",
            );
        }
    }

    /** @return array<string, array{string, string}> */
    public static function typeDefaults(): array
    {
        return [
            'text' => ['text', 'plain'],
            'int' => ['int', 'plain'],
            'money' => ['money', 'plain'],
            'datetime' => ['datetime', 'plain'],
            'bool' => ['bool', 'check'],
            'enum' => ['enum', 'badge'],
            'json' => ['json', 'plain'],
        ];
    }

    #[DataProvider('typeDefaults')]
    public function testATypeKnowsHowItLooksWhenNobodySays(string $type, string $display): void
    {
        $this->assertSame($display, ColumnType::from($type)->defaultDisplay()->value);
    }

    public function testABooleanMayBeACheckOrTheWordsYesAndNo(): void
    {
        $bool = ColumnType::from('bool');

        $this->assertTrue($bool->allows(Display::Check));
        $this->assertTrue($bool->allows(Display::YesNo));
        $this->assertTrue($bool->allows(Display::Badge));
    }

    public function testAMoneyColumnCannotBeAProgressBar(): void
    {
        // The pair means nothing, so it is refused rather than rendered as
        // something the writer did not intend.
        $this->assertFalse(ColumnType::from('money')->allows(Display::Progress));
    }

    public function testNumbersAlignToTheEndAndEverythingElseToTheStart(): void
    {
        // A ragged right edge makes a column of numbers unreadable, which is
        // the whole reason anyone puts numbers in a grid.
        $this->assertSame('end', ColumnType::from('money')->defaultAlignment());
        $this->assertSame('end', ColumnType::from('int')->defaultAlignment());
        $this->assertSame('start', ColumnType::from('text')->defaultAlignment());
        $this->assertSame('start', ColumnType::from('datetime')->defaultAlignment());
    }

    public function testAnUnknownTypeIsRefusedAndSuggestsTheNearest(): void
    {
        // 'texte' is a typo somebody will make, and the list of seven is short
        // enough that naming the nearest one is always useful.
        $this->expectException(PageException::class);
        $this->expectExceptionMessage('text');

        ColumnType::from('texte');
    }

    public function testTheRefusalListsEveryTypeWhenNothingIsClose(): void
    {
        try {
            ColumnType::from('quantum');
            $this->fail('An unknown type should throw.');
        } catch (PageException $e) {
            foreach (ColumnType::cases() as $type) {
                $this->assertStringContainsString($type->value, $e->getMessage());
            }
        }
    }
}
```

Create `tests/Unit/Page/PageSchemaTest.php` asserting the schema's shape
rather than its rendering: that `title`, `layout`, `entity`, `header` and
`regions` are declared; that `entity.table` is required and `entity.key`
defaults to `'id'`; that `regions` uses `each` so every region is validated
alike; that a region declares `type`, `per_page`, `sort`, `columns` and
`search`; that `columns` uses `each` with `ColumnSchema`; and that every key
in both schemas carries a description and an example, the way
`ReferenceIsCurrentTest` demands of the root schema. Write each assertion in
the style of `tests/Unit/Config/RootSchemaTest.php`.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Page/`
Expected: FAIL — none of these classes exist.

- [ ] **Step 3: Write the implementation**

`ColumnType::parse()` exists because a backed enum's own `from()` cannot be
redeclared, and its `\ValueError` names no alternatives. `Display::parse()`
is its twin. Use
`Schema::nearestOf()` — it already exists and already solves "did you mean" —
to suggest the closest case, and list all seven when nothing is close.

`ColumnSchema::create()` returns a `Schema` with these keys. Give each a
description written for someone deciding whether to set it, and an example:

| Key | Type | Default | Notes |
|---|---|---|---|
| `type` | string | `text` | One of the seven. |
| `label` | string | — | Defaults at load to the key, title-cased. |
| `source` | string | — | A source path; defaults to the key. `.` joins a relation, `->` traverses JSON. |
| `display` | string | — | Defaults to the type's own. |
| `sortable` | bool | `false` | Whether the header is a sort link. |
| `searchable` | bool | `false` | Whether the region's search box looks here. |
| `align` | string | — | `start` or `end`; defaults to the type's own. |
| `width` | string | — | A CSS width for the column, e.g. `8rem`. |
| `class` | string | — | Extra classes on every cell of this column. |
| `link` | bool | `false` | Makes the cell an anchor to the row's detail page. |
| `currency` | string | — | `money` only. |
| `format` | string | — | `datetime` only: a PHP date format. |
| `max` | int | — | `progress` only: what counts as full. |
| `options` | mixed | — | `enum` only: an `@enum:` reference or a literal map. |
| `filter` | array | — | Makes the column filterable. Its own block, below. |
| `collection` | array | — | Makes the column a one-to-many. Its own block, below. |

**The `filter` block**, because a column that can be filtered has to say how:

| Key | Type | Default | Notes |
|---|---|---|---|
| `type` | string | `text` | `text`, `select`, `multiselect`, `range`, `date`, `boolean`. |
| `op` | string | — | A `FilterOperator` value. Defaults per filter type: `text` uses `contains`, `select` and `boolean` use `equals`, `multiselect` uses `in`, `range` and `date` use `between`. |
| `label` | string | — | Defaults to the column's label. |
| `options` | mixed | — | `select` and `multiselect`: an `@enum:` reference or a literal map. Defaults to the column's own `options` when it is an enum. |
| `placeholder` | string | — | Shown in an empty text filter. |

**The `collection` block.** A one-to-many is not a join — joining would
multiply the rows and make every count wrong. Milestone 3 fetches it with one
supplementary query for the whole page, and this is how a page asks for that:

| Key | Type | Default | Notes |
|---|---|---|---|
| `table` | string | **required** | The table holding the many. |
| `foreign_key` | string | **required** | Its column pointing back at this entity's key. |
| `column` | string | **required** | The column to collect, one value per related row. |

A column carrying `collection` has no `source`: its values come from the
supplementary query rather than the select list, so a `source` on one is
refused at load as a contradiction.

`PageSchema::create()` declares `title` (string, required), `layout` (string,
default `single`), `entity` (array: `table` required, `key` default `id`,
`scope` array, `relations` array with `each`), `header` (array: `description`),
and `regions` (array with `each`: `type` required, `per_page` int,
`sort` array, `search` array, `columns` array with `each: ColumnSchema`).

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Page/`

- [ ] **Step 5: Run the full gate**

Run: `composer run check`

- [ ] **Step 6: Commit**

```bash
git add src/Page tests/Unit/Page
git commit -m "Declare what a page may say about itself"
```

---

### Task 2: Loading pages

**Files:**
- Create: `src/Page/PageDefinition.php`
- Create: `src/Page/RegionDefinition.php`
- Create: `src/Page/ColumnDefinition.php`
- Create: `src/Page/FilterDefinition.php`
- Create: `src/Page/PageRepository.php`
- Modify: `src/Config/RootSchema.php` — add `pages_path` and `per_page`
- Test: `tests/Unit/Page/PageRepositoryTest.php`
- Test: `tests/Unit/Page/PageDefinitionTest.php`

**Interfaces:**
- Consumes: Task 1's schemas; `RockAdmin\Config\{Definitions, Resolver, Validator, Defaults, Enums, ConfigException}`. **Read `src/Config/Loader.php` first** — its `load()` is the pass order this repeats, and repeating it in a different order is how the two drift.
- Produces:
  ```php
  namespace RockAdmin\Page;

  final class PageRepository
  {
      /** @param Closure(string): ?string $env */
      public function __construct(
          string $directory,
          \Closure $env,
          Enums $enums,
          int $defaultPerPage = 25,
      );

      public function has(string $name): bool;
      public function get(string $name): PageDefinition;   // PageException when absent
      /** @return list<string> */
      public function names(): array;
  }

  final class PageDefinition
  {
      public readonly string $name;
      public readonly string $title;
      public readonly string $layout;
      public readonly string $description;
      public readonly Entity $entity;                       // RockAdmin\Db\Entity
      /** @var array<string, RegionDefinition> */
      public readonly array $regions;
      public function region(string $key): RegionDefinition;
      public function hasRegion(string $key): bool;
  }

  final class RegionDefinition
  {
      public readonly string $key;
      public readonly string $type;
      public readonly int $perPage;
      /** @var array<string, ColumnDefinition> */
      public readonly array $columns;
      /** @var list<Sort> */                                // RockAdmin\Db\Sort
      public readonly array $sort;
      /** @var list<string> */
      public readonly array $searchable;
      public function column(string $key): ColumnDefinition;
      public function hasColumn(string $key): bool;
  }

  final class ColumnDefinition
  {
      public readonly ?FilterDefinition $filter;   // null when the column is not filterable
      public readonly ?Collection $collection;     // RockAdmin\Db\Collection, null unless one-to-many
      public readonly string $key;
      public readonly string $label;
      public readonly string $source;
      public readonly ColumnType $type;
      public readonly Display $display;
      public readonly bool $sortable;
      public readonly bool $link;
      public readonly string $align;
      public readonly ?string $width;
      public readonly string $class;
      /** @var array<string, mixed> */
      public readonly array $options;    // currency, format, max, enum options
  }
  ```

**The loading pass order, which must match `Loader::load()`:** read the file →
expand shared definitions → resolve placeholders → validate against
`PageSchema` → apply defaults → build the objects. Validation before defaults,
so the validator judges what the project wrote rather than what we completed
for it.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Page/PageRepositoryTest.php`. Build a temporary directory
of page files in `setUp()` the way `TemplateResolverTest` does, and cover at
least:

```php
public function testAPageIsNamedByItsFile(): void
public function testTitleAndLayoutComeStraightFromTheFile(): void
public function testAColumnWithNoSourceReadsTheColumnOfItsOwnName(): void
public function testAColumnWithNoLabelGetsOneMadeFromItsKey(): void        // created_at -> "Created at"
public function testAColumnInheritsItsTypesDefaultDisplay(): void
public function testADisplayTheTypeRefusesIsAnErrorNamingBoth(): void
public function testARelationDeclaredOnTheEntityBecomesARelationObject(): void
public function testAColumnSourceNamingAnUndeclaredRelationIsRefused(): void
public function testSharedDefinitionsAreExpanded(): void                   // ['use' => '@column:id']
public function testPlaceholdersInScopeSurviveAsPlaceholders(): void       // {{workspace.site_id}}
public function testAnUnknownKeyInAPageFileIsRefusedAndSuggestsTheNearest(): void
public function testAMissingTitleIsRefused(): void
public function testPerPageFallsBackToTheRootConfigurationsValue(): void
public function testSortIsReadAsAListOfSortObjects(): void                 // ['created_at' => 'desc']
public function testSearchableColumnsAreCollectedForTheRegion(): void
public function testAFilterBlockBecomesAFilterDefinitionWithItsDefaultOperator(): void
public function testAFilterTypeChoosesItsOperatorWhenTheColumnDoesNotSayOne(): void
public function testASelectFilterInheritsTheColumnsEnumOptions(): void
public function testAColumnWithNoFilterBlockIsNotFilterable(): void
public function testACollectionBlockBecomesADbCollectionObject(): void
public function testACollectionColumnCarryingASourceIsRefusedAsAContradiction(): void
public function testAPageThatDoesNotExistIsRefusedByName(): void
public function testNamesListsEveryPageFileOnce(): void
public function testAPageIsReadFromDiskOnlyOnce(): void                    // repository caches per request
```

Write the file fixtures inline in each test rather than sharing one big page,
so a failure names the shape that broke it. Use the spec 6.2 example as the
shape to aim for.

- [ ] **Step 2: Run the test to verify it fails**

- [ ] **Step 3: Write the implementation**

Add to `RootSchema::create()`:

```php
'pages_path' => new SchemaKey(
    ValueType::String,
    default: 'pages',
    description: 'Where page files live, relative to the configuration directory. '
        . 'One file per page, named after the page: pages/ads.php is reachable at /p/ads.',
    example: 'pages',
),
'per_page' => new SchemaKey(
    ValueType::Int,
    default: 25,
    description: 'Rows in one page of a grid, for regions that do not set their own.',
    example: 50,
    performance: 'A large value makes every grid render slower for everyone; '
        . 'set it per region where a particular page needs more.',
),
```

`ColumnDefinition`'s label default is the key with underscores turned to
spaces and the first letter upper-cased — `created_at` becomes `Created at`,
not `Created At`, because a grid header is a phrase rather than a title.

The entity is built here, not by the caller: `PageDefinition::$entity` is a
real `RockAdmin\Db\Entity` with its relations, so the query factory in Task 3
receives something the data layer already understands.

Validate that every column's `source` names either a plain column or a
relation the entity declares. `Entity::hasRelation()` answers it, and
`SourcePath` already knows how to split one — read `src/Db/SourcePath.php`
before writing your own splitting.

- [ ] **Step 4-6: Verify, gate, commit**

```bash
composer run docs:reference   # pages_path and per_page are new keys
git add src/Page src/Config tests docs/reference
git commit -m "Load a page the same way the rest of the configuration loads"
```

---

### Task 3: The URL is the state

**Files:**
- Create: `src/Grid/GridState.php`
- Create: `src/Grid/FilterInput.php`
- Test: `tests/Unit/Grid/GridStateTest.php`

**Interfaces:**
- Consumes: `RockAdmin\Page\RegionDefinition` (Task 2), `RockAdmin\Db\{Filter, FilterOperator, Sort, SortDirection}`.
- Produces:
  ```php
  namespace RockAdmin\Grid;

  final class GridState
  {
      /**
       * Reads one region's slice of the query string. Everything here arrives
       * from a URL a stranger can write, so nothing is trusted and nothing
       * throws: what cannot be understood is dropped.
       *
       * @param array<array-key, mixed> $query the whole query string
       */
      public static function fromQuery(array $query, string $regionKey, RegionDefinition $region): self;

      public readonly string $search;
      /** @var list<FilterInput> */
      public readonly array $filters;
      /** @var list<Sort> */
      public readonly array $sort;
      public readonly int $page;

      /** The query parameters this state would produce, for building links. */
      public function toQuery(string $regionKey): array;
      public function withPage(int $page): self;
      public function withSort(string $column): self;   // toggles direction, or adds it ascending
      public function isEmpty(): bool;
  }
  ```

**The URL grammar**, from spec 8.11:

```
/p/ads?grid[q]=bike&grid[f][state]=active&grid[sort]=-created_at&grid[page]=3
```

- `q` — the search term, trimmed; an empty one is no search.
- `f[<column>]` — a filter. A scalar means equality or the column's declared
  operator; `['from' => …, 'to' => …]` means a range; a list means `in`.
- `sort` — a column key, optionally prefixed with `-` for descending. Several
  are comma-separated.
- `page` — 1-based. Anything below 1 becomes 1.

**The discard rule, restated from milestone 3.** A filter or sort naming a
column the region does not declare is dropped. A filter on a column that
declares no `filter` block is dropped. A search term when no column is
`searchable` is dropped. None of this throws: it is a URL, and a stale
bookmark should show a grid rather than an error page.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Grid/GridStateTest.php`. It needs a `RegionDefinition`
fixture; build one with a small private helper rather than loading a page from
disk, so the test stays about state. Cover at least:

```php
public function testAnEmptyQueryIsAnEmptyStateOnPageOne(): void
public function testTheSearchTermIsRead(): void
public function testASearchTermOfSpacesIsNoSearch(): void
public function testASearchTermIsDroppedWhenNoColumnIsSearchable(): void
public function testAFilterIsReadForADeclaredColumn(): void
public function testAFilterNamingAnUndeclaredColumnIsDropped(): void
public function testAFilterOnAColumnThatDeclaresNoneIsDropped(): void
public function testARangeFilterIsReadFromFromAndTo(): void
public function testARangeWithOnlyOneEndStillFilters(): void
public function testAListValueBecomesAnInFilter(): void
public function testAnEmptyFilterValueIsDroppedRatherThanMatchingEmptyString(): void
public function testSortIsReadWithItsDirection(): void                    // -created_at
public function testSeveralSortsAreReadInOrder(): void                    // state,-created_at
public function testASortNamingAnUnsortableColumnIsDropped(): void
public function testTheRegionsOwnSortIsUsedWhenTheUrlCarriesNone(): void
public function testPageBelowOneBecomesOne(): void
public function testAPageThatIsNotANumberBecomesOne(): void
public function testAnotherRegionsParametersAreIgnored(): void            // grid[…] vs detail[…]
public function testToQueryRoundTripsThroughFromQuery(): void
public function testWithSortTogglesDirectionWhenTheColumnIsAlreadyTheSort(): void
public function testWithSortReplacesTheSortRatherThanAppending(): void
public function testWithPageKeepsEverythingElse(): void
public function testDeeplyNestedRubbishIsDroppedRatherThanCrashing(): void
```

That last one matters: `?grid[f][state][][]=x` is a legal query string and PHP
will hand it to you as nested arrays. Assert it produces no filter rather than
a TypeError.

- [ ] **Step 2-6: Fail, implement, verify, gate, commit**

```bash
git add src/Grid tests/Unit/Grid
git commit -m "Read a grid's state out of the URL and trust none of it"
```

---

### Task 4: From a page and a URL to a query

The seam this milestone turns on: everything above it describes, everything
below it executes, and this is where description becomes a `Query`.

**Files:**
- Create: `src/Grid/QueryFactory.php`
- Test: `tests/Unit/Grid/QueryFactoryTest.php`
- Test: `tests/Integration/Grid/QueryFactoryTest.php`

**Interfaces:**
- Consumes: `PageDefinition`, `RegionDefinition`, `ColumnDefinition` (Task 2);
  `GridState` (Task 3); and from milestone 3 — `Query`, `Entity`, `Filter`,
  `FilterOperator`, `Search`, `Sort`, `Page`, `Collection`, `CountStrategy`.
  **Read `src/Db/Query.php` and `src/Db/QueryBuilder.php` before starting.**
  The constructor argument names and order are the contract here.
- Produces:
  ```php
  namespace RockAdmin\Grid;

  final class QueryFactory
  {
      public function __construct(private readonly int $maxPerPage = 200);

      public function build(PageDefinition $page, RegionDefinition $region, GridState $state): Query;
  }
  ```

**What it does, in order:**

1. **Columns.** Every column's `source` becomes a select entry keyed by the
   column's key. Milestone 3's `Query::$columns` is `alias => source path`,
   which is exactly what a column definition already holds.
2. **Scope.** The entity's `scope` becomes filters that are not negotiable —
   they are the workspace boundary, so they are appended after the URL's
   filters and can never be dropped by the discard rule.
3. **Filters.** Each `FilterInput` becomes a `Filter` with the operator its
   column declared. A range becomes `Between` when both ends are present and
   `GreaterOrEqual`/`LessOrEqual` when one is.
4. **Search.** When the state has a term and the region has searchable
   columns, a `Search` over those columns' source paths.
5. **Sort.** The state's sort, or the region's own when the URL carries none.
   **Always append the entity key as a final tiebreaker**, or two rows with
   the same `created_at` swap places between page one and page two and a row
   is silently never shown.
6. **Paging.** `Page` with `limit` = the region's `per_page` clamped to
   `maxPerPage`, and `offset` = `(page - 1) * limit`.
7. **Count.** `CountStrategy::Exact`, because a pager needs a number. A region
   may later ask for an estimate; that key is not in this milestone.
8. **Collections.** Every column carrying one contributes its
   `RockAdmin\Db\Collection` to the query, keyed by the column's key.
   Milestone 3 fetches them with one supplementary statement for the whole
   page — never one per row — and refuses a collection whose alias collides
   with the entity's key.

- [ ] **Step 1: Write the failing unit test**

`tests/Unit/Grid/QueryFactoryTest.php` builds definitions by hand and asserts
on the `Query` object rather than on SQL — the SQL is milestone 3's business
and already has its own tests. Cover at least:

```php
public function testEveryColumnBecomesASelectKeyedByItsKey(): void
public function testAColumnSourceCrossingARelationIsPassedThroughUntouched(): void
public function testTheEntityScopeBecomesAFilter(): void
public function testAScopeFilterSurvivesEvenWhenTheUrlFiltersTheSameColumn(): void
public function testAFilterUsesTheOperatorItsColumnDeclared(): void
public function testARangeWithBothEndsBecomesBetween(): void
public function testARangeWithOnlyAFromBecomesGreaterOrEqual(): void
public function testARangeWithOnlyAToBecomesLessOrEqual(): void
public function testSearchCoversExactlyTheSearchableColumnsSourcePaths(): void
public function testThereIsNoSearchWhenTheTermIsEmpty(): void
public function testTheEntityKeyIsAlwaysTheLastSort(): void
public function testTheEntityKeyIsNotAddedTwiceWhenItIsAlreadyTheSort(): void
public function testPerPageIsClampedSoAUrlCannotAskForEverything(): void
public function testPageThreeBecomesTheRightOffset(): void
public function testTheRegionsOwnSortIsUsedWhenTheStateHasNone(): void
public function testAColumnCarryingACollectionIsNotInTheSelectList(): void
public function testEveryCollectionColumnReachesTheQuerysCollections(): void
```

The tiebreaker test is the one that matters most. Write it so a factory that
omits the key fails:

```php
public function testTheEntityKeyIsAlwaysTheLastSort(): void
{
    // Without a tiebreaker, two rows sharing a created_at can swap between
    // page one and page two, and one of them is never shown to anyone. This
    // is not a preference; it is the difference between a pager that works
    // and one that loses rows.
    $query = (new QueryFactory())->build(
        $this->page(),
        $this->region(sort: ['created_at' => 'desc']),
        GridState::fromQuery([], 'grid', $this->region(sort: ['created_at' => 'desc'])),
    );

    $columns = array_map(static fn (Sort $sort): string => $sort->column, $query->sort);

    $this->assertSame(['created_at', 'id'], $columns);
}
```

- [ ] **Step 2: Write the failing integration test**

`tests/Integration/Grid/QueryFactoryTest.php` extends `DatabaseTestCase` and
runs the built queries through `SqlRowSource` against the fixture tables on
both drivers. This is where the milestone earns its keep: a `Query` that looks
right and does not execute is worth nothing.

```php
public function testAGridOverTheFixtureAdsReturnsItsRows(): void
public function testAJoinedColumnReturnsTheRelatedValue(): void          // user.name
public function testAJsonSourcePathReturnsTheNestedValue(): void         // stats->daily->views
public function testAFilterNarrowsTheRows(): void                        // state = active
public function testSearchMatchesAcrossSearchableColumns(): void
public function testSortingReversesTheOrder(): void
public function testTheSecondPageContinuesWhereTheFirstStopped(): void
public function testAGridWithThreeJoinedColumnsIssuesTwoStatements(): void   // rows + count
```

That last one asserts on `Result::$statements`, and it is the N+1 guard: the
count is one statement, the rows are one statement, and nothing else may
appear however many columns cross relations.

- [ ] **Step 3-6: Implement, verify on both drivers, gate, commit**

Export the database variables before running the gate:

```bash
RA_TEST_MYSQL_DSN='mysql:host=127.0.0.1;port=3306;dbname=rockadmin_test' \
RA_TEST_MYSQL_USER=root RA_TEST_MYSQL_PASSWORD=root composer run check
```

```bash
git add src/Grid tests
git commit -m "Turn a described page and a URL into a query that runs"
```

---

### Task 5: Cells

What a value looks like once it has been read. This is the task with the most
surface and the least depth: seven types, seven displays, and a great many
small decisions about formatting that are each obvious and collectively the
difference between a grid that reads well and one that does not.

**Files:**
- Create: `src/Grid/CellView.php`
- Create: `src/Grid/CellFormatter.php`
- Test: `tests/Unit/Grid/CellFormatterTest.php`

**Interfaces:**
- Consumes: `ColumnDefinition`, `ColumnType`, `Display` (Task 1-2) and
  `RockAdmin\View\Classes`. **Not `Enums`** — see below.
- Produces:
  ```php
  namespace RockAdmin\Grid;

  final class CellView
  {
      public readonly string $key;          // the column key
      public readonly mixed $value;         // as it came out of the database
      public readonly string $text;         // formatted for reading
      public readonly Display $display;
      public readonly string $classes;      // ra-grid-cell ra-grid-cell-<key> …
      public readonly ?string $url;         // set when the column links
      /** @var array<string, scalar|null> */
      public readonly array $attributes;    // data-ra-* the display needs
      public readonly ?int $percent;        // progress and percent only
      public readonly ?string $variant;     // badge colour, from the enum option
  }

  final class CellFormatter
  {
      public function format(ColumnDefinition $column, mixed $value, ?string $url = null): CellView;
  }
  ```

**Where an enum column's options come from.** Task 2 already resolved them:
a column carries `$column->options['enum']` as an `array<string, EnumOption>`,
with `@enum:` references followed and literal maps parsed, refusing anything
malformed by name. This class reads that array and nothing else. Taking an
`Enums` and looking the reference up again would be a second path to the same
data, and the two would eventually disagree about a column whose options were
overridden per page — which is exactly the kind of seam this milestone has
already had to close twice.

**How each type formats**, and the reasoning where it is not obvious:

- **text** — the value as a string. `null` becomes an empty string, never the
  word "null".
- **int** — grouped by thousands with a narrow no-break space, which is the
  Czech and most European convention and reads better than a comma in a
  narrow column.
- **money** — the same grouping plus the column's `currency`, with the amount
  and the symbol kept together.
- **datetime** — the column's `format`, defaulting to `Y-m-d H:i`. A value
  that is not a parseable date is passed through as text rather than becoming
  1970, because a wrong date looks right and is worse than an odd string.
- **bool** — `check` renders a tick or nothing; `yesno` renders words; both
  read `true`, `1`, `'1'` and `'t'` as true, because three databases spell a
  boolean three ways.
- **enum** — the option's label from `$column->options['enum']`, and its
  colour for the badge variant. A value with no matching option keeps its raw value and
  gets no variant, so an unmapped state is visible rather than blank.
- **json** — a compact one-line rendering, truncated with an ellipsis past a
  sensible length, with the full value in a `title` attribute.

**`null` is a first-class value in a grid.** Every type renders it as an empty
cell carrying `ra-grid-cell-empty`, so a column of mostly-null values is
visibly empty rather than looking like a rendering failure.

- [ ] **Step 1: Write the failing test**

One test per type and per display, plus the null case for every type in a data
provider. Assert on `CellView::$text` and `$classes`, not on markup.

Include these, which are the ones that catch real mistakes:

```php
public function testANullValueIsAnEmptyCellThatSaysSoInItsClasses(): void
public function testZeroIsNotTreatedAsEmpty(): void          // 0, '0' and 0.0 all render
public function testAFalseBooleanIsNotTreatedAsEmpty(): void
public function testAnEnumValueWithNoMatchingOptionKeepsItsRawValue(): void
public function testADatetimeThatCannotBeParsedIsShownAsItIs(): void
public function testAProgressValueAboveItsMaximumIsClampedToFull(): void
public function testANegativeProgressValueIsClampedToEmpty(): void
public function testAJsonValueIsTruncatedAndCarriesTheWholeThingInItsTitle(): void
public function testTheCellCarriesItsColumnsIdentityClass(): void
```

`testZeroIsNotTreatedAsEmpty` is the one that will fail on a first
implementation, because `empty()` and a falsy check both swallow `0` — and a
price of zero, a count of zero and a false flag are all things a grid must
show.

- [ ] **Step 2-6: Fail, implement, verify, gate, commit**

```bash
git add src/Grid tests/Unit/Grid
git commit -m "Format a value for a grid cell without losing zero or false"
```

---

### Task 6: The region

**Files:**
- Create: `src/Grid/ListRegion.php`
- Create: `src/Grid/ListView.php`
- Create: `src/Grid/RowView.php`
- Create: `src/Grid/PaginationView.php`
- Create: `src/Grid/FilterView.php`
- Test: `tests/Unit/Grid/ListRegionTest.php`
- Test: `tests/Unit/Grid/PaginationViewTest.php`
- Test: `tests/Integration/Grid/ListRegionTest.php`

**Interfaces:**
- Consumes: everything from Tasks 2-5, plus `RockAdmin\Db\RowSource`,
  `RockAdmin\Http\UrlGenerator`.
- Produces:
  ```php
  namespace RockAdmin\Grid;

  final class ListRegion
  {
      public function __construct(
          private readonly RowSource $rows,
          private readonly QueryFactory $queries,
          private readonly CellFormatter $cells,
          private readonly UrlGenerator $urls,
      );

      public function render(PageDefinition $page, RegionDefinition $region, GridState $state): ListView;
  }

  final class ListView
  {
      public readonly string $key;              // the region key
      public readonly string $pageName;
      /** @var list<ColumnView> */
      public readonly array $columns;
      /** @var list<RowView> */
      public readonly array $rows;
      /** @var list<FilterView> */
      public readonly array $filters;
      public readonly PaginationView $pagination;
      public readonly string $search;
      public readonly bool $searchable;
      public readonly string $regionUrl;        // /r/{page}/{region}, for refreshes
      public function isEmpty(): bool;
      public function classes(): string;
  }

  final class ColumnView
  {
      public readonly string $key;
      public readonly string $label;
      public readonly string $classes;         // ra-grid-head ra-grid-head-<key> ...
      public readonly string $align;
      public readonly ?string $width;
      public readonly ?string $sortUrl;        // null when the column is not sortable
      public readonly ?string $sortDirection;  // 'ascending', 'descending' or null
  }
  ```

**Why the columns are a view and not the definitions.** Handing templates a
`ColumnDefinition` would hand them configuration, which rule 4 of this project
forbids — and it would force `ListView` to hold a `UrlGenerator` and a
`GridState` so it could answer `sortUrl()` while rendering. Everything a
header needs is decided once, in `ListRegion`, and arrives already decided.

  `RowView` carries the row's key value, its `CellView`s in column order, its
  detail URL and its `ra-grid-row` classes. `PaginationView` carries the
  current page, the total, the number of pages, and the list of page numbers
  worth showing with their URLs.

**Pagination that does not lie.** With `CountStrategy::Exact` the total is a
number; the view must also survive a null total, because a later region may
ask for an estimate or none at all. When the total is unknown, show "next"
and "previous" and no page count, rather than inventing one.

**A page beyond the end shows the last page**, not an empty grid. Somebody
bookmarked page nine and three rows were deleted.

- [ ] **Step 1: Write the failing tests**

`ListRegionTest` uses a fake `RowSource` returning a fixed `Result`, so it
tests assembly rather than SQL:

```php
public function testEveryRowBecomesARowViewWithACellPerColumn(): void
public function testARowCarriesItsKeyValueSoTheTemplateCanIdentifyIt(): void
public function testAColumnThatLinksGivesItsCellTheRowsDetailUrl(): void
public function testTheSortUrlForAColumnTogglesItsDirection(): void
public function testTheSortUrlKeepsTheCurrentFiltersAndSearch(): void
public function testAnUnsortableColumnHasNoSortUrl(): void
public function testOnlyTheCurrentSortColumnCarriesADirection(): void
public function testNoViewObjectCarriesAColumnDefinition(): void
public function testAnEmptyResultIsAnEmptyViewRatherThanAnError(): void
public function testTheRegionUrlIsTheFragmentAddressForThisRegion(): void
public function testFiltersCarryTheValueTheUrlAlreadyHeld(): void
public function testOneSupplementaryStatementFetchesAOneToManyForThePage(): void
```

`PaginationViewTest` covers the arithmetic on its own:

```php
public function testThePageCountIsTheTotalDividedByTheLimitRoundedUp(): void
public function testAnExactMultipleDoesNotProduceAnEmptyLastPage(): void   // 50 rows, 25 per page = 2
public function testATotalOfZeroIsOnePageNotZero(): void
public function testAPageBeyondTheEndShowsTheLastPage(): void
public function testAnUnknownTotalStillOffersNextAndPrevious(): void
public function testTheWindowOfPageNumbersIsCenteredOnTheCurrentPage(): void
```

The integration test renders a real grid over the fixture ads on both drivers
and asserts the statement count, the joined values and the collection.

- [ ] **Step 2-6: Fail, implement, verify on both drivers, gate, commit**

```bash
git add src/Grid tests
git commit -m "Assemble a grid's rows, filters and pager into one prepared view"
```

---

### Task 7: The templates

**Files:**
- Create: `templates/region/list/region.php`, `toolbar.php`, `filters.php`,
  `table.php`, `head.php`, `body.php`, `row.php`, `empty.php`, `pagination.php`
- Create: `templates/region/list/cell/text.php`, `int.php`, `money.php`,
  `datetime.php`, `bool.php`, `enum.php`, `json.php`, `link.php`
- Modify: `assets/css/rockadmin.css` — grid density and the sort affordances
- Test: `tests/Unit/Grid/ListTemplatesTest.php`

**Interfaces:**
- Consumes: `ListView`, `RowView`, `CellView`, `FilterView`, `PaginationView`
  (Task 6); the renderer and helpers from milestone 4.
- Produces: no new PHP class.

**What a template receives** — the same nine names as every other template:
`$view` plus `$e`, `$raw`, `$attr`, `$attrs`, `$href`, `$url`, `$route`,
`$partial`. **Read `templates/page/header.php` and `templates/ui/button.php`
first**; they are the house style, including the `@var` docblock with full
closure signatures that PHPStan at level max requires.

**The structure:**

```
region.php    <div class="ra-region ra-region-list ra-region-list-<page>"
                   data-ra-region="<key>" data-ra-region-url="<regionUrl>">
              toolbar, table or empty, pagination
toolbar.php   the search box and the filter controls, in a <form> that GETs
filters.php   one control per filter, by its type
table.php     <table>, calling head and body
head.php      one <th> per column; sortable ones are links carrying the
              toggled sort URL and an aria-sort attribute
body.php      one row.php per row
row.php       <tr data-id="…"> and one cell partial per cell, chosen by the
              cell's own display
empty.php     what an empty grid says, which is not "no results" but
              something that tells the reader whether a filter caused it
pagination.php the pager
```

**The cell partial is chosen by display, not by type.** A `text` column
displayed as a badge renders `cell/enum.php`'s badge markup — so the partial
map is keyed on `Display`, with `link` wrapping whatever the underlying
display produced. Put that mapping in `row.php`, in one place, with a comment
saying why it is display rather than type.

**Everything the grid does dynamically is declared, never scripted.** The
search form, the filter controls, the sort links and the pager all carry
`data-ra-*` attributes; `core.js` in Task 8 binds them by delegation. A
template with a `<script>` body fails `TemplateStandardsTest`.

**Sorting affordances.** A sortable header is a link, so it works without
JavaScript, and carries `aria-sort="ascending"`/`"descending"` when it is the
current sort. The arrow is CSS, not an image and not an icon font.

**The empty state.** Two different messages: "nothing here yet" when the grid
has no filters, and "nothing matches these filters" with a link that clears
them when it does. Telling somebody their empty grid is empty is not help.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Grid/ListTemplatesTest.php` renders the real templates through a
real `Renderer` over hand-built views, in the style of
`tests/Unit/View/DefaultTemplatesTest.php`. Cover at least:

```php
public function testTheRegionCarriesItsKeyAndItsFragmentUrl(): void
public function testEveryColumnGetsAHeaderCellCarryingItsIdentityClass(): void
public function testASortableHeaderIsALinkAndSaysSoToAScreenReader(): void
public function testAnUnsortableHeaderIsNotALink(): void
public function testTheCurrentSortColumnCarriesAriaSort(): void
public function testEachRowCarriesItsKeyValue(): void
public function testACellIsRenderedByItsDisplayNotItsType(): void
public function testALinkingCellWrapsItsContentInAnAnchor(): void
public function testAnEmptyGridWithNoFiltersSaysSomethingDifferentFromOneWithFilters(): void
public function testTheFilterFormSubmitsWithGet(): void
public function testThePagerLinksCarryTheCurrentFiltersAndSort(): void
public function testAProgressCellRendersItsPercentageAsAWidth(): void
public function testAJsonCellCarriesTheFullValueInATitle(): void
public function testNoTemplateWritesAUrlItBuiltItself(): void
```

`TemplateStandardsTest` will pick the new templates up automatically through
its directory walk — check its vacuity guard still asserts a count at or below
the real number, and raise it if it was pinned to fourteen.

- [ ] **Step 2-6: Fail, implement, verify, gate, commit**

The gate now includes the template standard over sixteen more files. Expect
the `$href` rule and the `ra-` class rule to catch something.

```bash
git add templates assets tests
git commit -m "Render a grid, its filters and its pager"
```

---

### Task 8: Serving it, and looking at it

The last task: two handlers, the half of `core.js` milestone 4 deferred, and a
demo that shows a real grid over real tables.

**Files:**
- Create: `src/Http/PageHandler.php`
- Create: `src/Http/RegionHandler.php`
- Modify: `assets/js/core.js`
- Modify: `demo/index.php`, `demo/config/rockadmin.php`, `demo/config/pages/ads.php`, `demo/README.md`
- Modify: `README.md`
- Test: `tests/Unit/Http/PageHandlerTest.php`
- Test: `tests/Unit/Http/RegionHandlerTest.php`
- Test: `tests/Integration/Grid/GridPageTest.php`

**Interfaces:**
- Consumes: everything above, plus `RockAdmin\Http\{Handler, Request, Response, Route, NotFoundException}` and the view layer.
- Produces:
  ```php
  namespace RockAdmin\Http;

  final class PageHandler implements Handler    // route 'page.index'
  final class RegionHandler implements Handler  // route 'region'
  ```

  Both take the page repository, the region machinery and the renderer. A page
  name that does not exist is a `NotFoundException`; so is a region key the
  page does not declare. Neither is an error page worth styling specially —
  404 is the honest answer to a URL that names nothing.

**`PageHandler` renders the whole document**: the shell, the page header from
its `header` block, and each region rendered into the layout's slots.
**`RegionHandler` renders one region and nothing else** — no shell, no
document — because the answer goes straight into a live page.

**A fragment is valid HTML and carries its own metadata on its root element**,
per spec 8.6: `data-ra-region`, and for overlays `data-ra-title`,
`data-ra-size`. It can be opened directly in a browser for debugging, which is
the property that makes this easy to reason about.

**`core.js` gains exactly what a fragment needs:**

1. `RockAdmin.load(url, target)` — fetch, insert, `detach()` the old subtree
   before removal and `attach()` the new one after insertion.
2. A delegated `submit` listener on any `form[data-ra-region-form]`, which
   serialises the form, loads the region URL with those parameters, and calls
   `history.pushState` so the address bar holds the shareable link.
3. A delegated `click` listener on `a[data-ra-region-link]` — sort headers and
   pager links — doing the same.
4. A `popstate` listener that re-loads the region from the URL, so the back
   button walks back through filter changes.
5. Failure handling: a fetch that fails leaves the existing region in place
   and shows a toast. A grid that silently stops responding is worse than one
   that says it could not reach the server.

Everything still works with JavaScript off, because every one of those is an
ordinary link or an ordinary GET form first.

- [ ] **Step 1: Write the failing tests**

Handler tests use a fake `RowSource` and assert on the `Response`:

```php
public function testAPageRendersADocumentWithItsRegionInIt(): void
public function testAnUnknownPageIsNotFound(): void
public function testTheHeaderDescriptionComesFromThePage(): void
public function testARegionRequestReturnsTheRegionAndNoDocument(): void
public function testAFragmentCarriesItsRegionKeyOnItsRoot(): void
public function testAnUnknownRegionIsNotFound(): void
public function testARegionRequestReadsTheStateFromItsOwnNamespace(): void
public function testAPageRequestAndARegionRequestAgreeOnTheSameRows(): void
```

That last one is the seam test: render `/p/ads?grid[page]=2` and
`/r/ads/grid?grid[page]=2` and assert the rows in both are identical. Two
paths into the same region is exactly the shape that drifts.

The integration test runs the whole stack — page file on disk, real database,
real templates — and asserts a grid of fixture ads renders with its joined
column, its filter applied, and the statement count unchanged.

- [ ] **Step 2: Write the demo page**

`demo/config/pages/ads.php` over the fixture tables, using every feature this
milestone built: a text column with a filter, a joined `user.name`, a money
column, an enum with a badge, a JSON source path, a sortable date, and a
one-to-many of tags. It doubles as the worked example the documentation will
point at, so write it the way you would want to find it.

The demo needs a database. Read `tests/Support/DatabaseTestCase.php` for the
fixture DDL and seed rows, and add a small `demo/seed.php` that creates them
against the same `RA_TEST_MYSQL_*` variables. When no database is configured,
the demo should say so on the page rather than throwing a connection error —
somebody cloning the repository to look at the theme should still see the
shell.

- [ ] **Step 3: Check it in a browser**

Not by reading the HTML. Start the demo, open it with Playwright, and confirm
with computed styles and real interaction:

- the grid renders its rows, and the numbers are right-aligned
- clicking a sortable header reverses the order and the URL changes
- typing in the search box and submitting narrows the rows without a reload
- the back button returns to the previous filter state
- the pager moves to page two and back
- it all still works with JavaScript disabled, as plain links and a GET form
- light and dark both read properly, contrast measured rather than eyeballed

Paste the measurements into your report. The last milestone shipped an
invisible toast and an illegible navbar because nobody opened the page; this
is the task where that is caught.

- [ ] **Step 4-6: Gate, update the READMEs, commit**

```bash
git add src templates assets demo tests README.md
git commit -m "Serve a page, refresh one region, and show a real grid"
```

---

### Task 9: The preview region

A grid whose rows cannot be opened is half a tool. This adds the second region
type — one row, rendered as a field list — reachable at `/p/{page}/{id}` as a
standalone page and at `/r/{page}/{region}?id=42` as a fragment, which is what
milestone 8 will later open in an offcanvas without changing anything here.

**Files:**
- Create: `src/Grid/PreviewRegion.php`
- Create: `src/Grid/PreviewView.php`
- Create: `src/Grid/FieldView.php`
- Create: `src/Http/DetailHandler.php`
- Create: `templates/region/preview/region.php`, `field.php`, `missing.php`
- Modify: `src/Page/PageSchema.php` — a region's `fields` key
- Modify: `src/Page/RegionDefinition.php` — resolved fields
- Test: `tests/Unit/Grid/PreviewRegionTest.php`
- Test: `tests/Unit/Http/DetailHandlerTest.php`
- Test: `tests/Integration/Grid/PreviewTest.php`

**Interfaces:**
- Consumes: `PageDefinition`, `RegionDefinition`, `ColumnDefinition`,
  `CellFormatter`, `RowSource`, `QueryFactory` — everything Tasks 2-6 built.
- Produces:
  ```php
  namespace RockAdmin\Grid;

  final class PreviewRegion
  {
      public function __construct(
          private readonly RowSource $rows,
          private readonly QueryFactory $queries,
          private readonly CellFormatter $cells,
      );

      /** Null when no row has that key — the caller turns that into a 404. */
      public function render(PageDefinition $page, RegionDefinition $region, string $id): ?PreviewView;
  }

  final class PreviewView
  {
      public readonly string $key;          // the region key
      public readonly string $title;        // the row's own label, not the page's
      public readonly string $id;
      /** @var list<FieldView> */
      public readonly array $fields;
      public function classes(): string;
  }

  final class FieldView
  {
      public readonly string $key;
      public readonly string $label;
      public readonly CellView $cell;
      public readonly bool $wide;   // long text and json span the full width
      public function classes(): string;
  }
  ```

**Which fields a preview shows**, from spec 8.5, exactly:

| Configuration | Meaning |
|---|---|
| key omitted | inherit the grid's columns |
| `'fields' => ['a', 'b']` | exactly these, in this order |
| `'fields' => '@all'` | every column of the entity's table |
| `'fields' => []` | none — legal, but the loader warns, since it is almost always a mistake |

`@all` needs the table's real columns, which only the database knows. Add
`Connection::columns(string $table): list<string>` to milestone 3's connection
— `Dialect` already isolates everything else per-server, so put the two
information-schema queries there rather than in `Connection`. On MySQL that is
`SHOW COLUMNS`; on PostgreSQL, `information_schema.columns`. Write it with an
integration test on both drivers before anything else in this task, because
everything else here depends on it being right.

**A preview reuses the grid's query machinery.** Build a `Query` for the
region's fields with a `Filter` on the entity key and a `Page` of one row. Do
not write a second query path: a preview that fetches differently from the
grid it came from will eventually disagree with it about what a column means.

**Long values get their own row.** A `json` cell, a `text` cell past a
sensible length and anything the column marks `wide` span the full width
rather than squeezing into a definition-list column. Everything else is a
label-and-value pair.

**A missing row is a 404, not an empty preview.** `DetailHandler` throws
`NotFoundException` when `render()` returns null, because `/p/ads/999999` is a
URL that names nothing.

- [ ] **Step 1: Write the failing tests**

```php
public function testAPreviewRendersOneFieldPerColumn(): void
public function testFieldsDefaultToTheGridsColumns(): void
public function testAnExplicitFieldListIsUsedInItsOwnOrder(): void
public function testAFieldNamingAnUndeclaredColumnIsRefusedAtLoad(): void
public function testAllExpandsToEveryColumnOfTheTable(): void
public function testAnEmptyFieldListWarnsRatherThanFailing(): void
public function testAJsonFieldIsWide(): void
public function testAMissingRowIsNull(): void
public function testThePreviewAndTheGridFormatTheSameValueIdentically(): void
public function testThePreviewIssuesOneStatementForOneRow(): void
```

That ninth test is the seam: format the same column through `ListRegion` and
through `PreviewRegion` and assert the two `CellView`s are equal. Two renderers
over one formatter is exactly the shape that drifts, and milestones 1 through 4
each lost a day to one.

- [ ] **Step 2-6: Fail, implement, verify on both drivers, gate, commit**

```bash
git add src templates tests
git commit -m "Open a row: one region, as a page and as a fragment"
```

---

## Milestone acceptance

1. `composer run check` is green, with the database variables exported so the
   integration tests actually run on both drivers.
2. `composer show --tree` lists no runtime dependency beyond PHP extensions.
3. A page described in `config/rockadmin/pages/ads.php` renders as a grid at
   `/p/ads`, with no PHP written outside configuration.
4. A grid of rows with three joined columns and one one-to-many issues three
   statements in total, asserted by a test.
5. A filter, a search, a sort and a page change each survive a round trip
   through the URL, and a link copied out of the address bar reproduces the
   same grid.
6. The same region renders identically as a page and as a fragment, and a
   preview formats a value exactly as the grid it came from does.
7. A row opens at `/p/{page}/{id}` showing every field the page declares.
8. Everything works with JavaScript disabled.
9. Every new configuration key is in `docs/reference/`, and
   `ReferenceIsCurrentTest` proves it.

## What this milestone deliberately leaves out

- **Actions** — spec 8.5's `link`, `open` and `post`, the `actions` column
  type, bulk actions and the header's action buttons. They are built on routes
  that mutate, which is milestone 7.
- **Forms and writes** — milestone 7.
- **The `form`, `nav` and `stat` region types.** This milestone builds `list`
  and `preview`, and the machinery all of them share.
- **Permissions and `{{user.*}}` binding** — milestone 5. Until then a page's
  `scope` may reference `{{workspace.*}}` and the placeholder will survive
  unbound, which the query builder already refuses loudly rather than
  silently ignoring.
- **`remember_state`** from spec 8.11 — it needs a session policy, and the URL
  already does the shareable half, which is the half that matters.
- **Keyset pagination in the UI.** Milestone 3 built it; a grid that offers
  page numbers needs offsets. A region may opt into keyset later for the deep
  pages where offsets hurt.
- **The dev console** — milestone 10. `Result::$statements` is already carried
  through for it.
