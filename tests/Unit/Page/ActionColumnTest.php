<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\ActionReference;
use RockAdmin\Config\Enums;
use RockAdmin\Config\PageReference;
use RockAdmin\Config\ValueType;
use RockAdmin\Grid\QueryFactory;
use RockAdmin\Page\ColumnSchema;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\PageException;
use RockAdmin\Page\PageRepository;
use RockAdmin\Page\PageSchema;

/**
 * Where actions appear (spec 8.5): a column of type `actions` carrying an
 * `items` list, and a page's `header.buttons`.
 *
 * Two properties are asserted over and over here, because both were decided
 * before the code was written and both are invisible once broken:
 *
 * **An inline action is built by the one path every named action is built
 * by.** `PageRepository::buildAction()` holds five refusals — a `to` that is
 * not a reference, a `post` with no `confirm`, a `link` with one, a `when`
 * naming a column nobody declared, and (through `Permission`) a permission
 * nobody generates. A second construction path would apply them to named
 * actions and not to the inline ones, which is exactly where somebody writes
 * a `post` with no `confirm`. So every one of those refusals is asserted
 * again through an *inline* definition.
 *
 * **`['use' => '@action:…']` has to survive `Definitions::expand()`**, which
 * runs before resolution and used to claim every `use` key in the file —
 * answering a form the specification documents with "Unknown definition
 * namespace 'action'".
 */
#[CoversClass(ColumnType::class)]
#[CoversClass(ColumnSchema::class)]
#[CoversClass(PageRepository::class)]
final class ActionColumnTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-actioncol-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    // ------------------------------------------------------- the type and the schema

    public function testActionsIsAColumnTypeAPageFileMayWrite(): void
    {
        $this->assertSame(ColumnType::Actions, ColumnType::parse('actions'));
        $this->assertTrue(ColumnType::Actions->isActions());
        $this->assertFalse(ColumnType::Text->isActions());
    }

    public function testTheColumnSchemaDeclaresItemsWithADescriptionAndAnExample(): void
    {
        $key = ColumnSchema::create()->key('items');

        $this->assertNotNull($key, "The column schema does not declare 'items'.");
        $this->assertSame(ValueType::Array, $key->type);
        $this->assertNotSame('', $key->description);
        $this->assertNotNull($key->example);
    }

    public function testThePageSchemaDeclaresHeaderButtonsWithADescriptionAndAnExample(): void
    {
        $header = PageSchema::create()->key('header');

        $this->assertNotNull($header);
        $this->assertNotNull($header->children);

        $buttons = $header->children->key('buttons');

        $this->assertNotNull($buttons, "The page schema does not declare 'header.buttons'.");
        $this->assertSame(ValueType::Array, $buttons->type);
        $this->assertNotSame('', $buttons->description);
        $this->assertNotNull($buttons->example);
    }

    // ------------------------------------------------------- the three forms

    /**
     * The three forms 8.5 lists, mixed in one `items` list, in the order they
     * were written — a grid draws its controls left to right, so the order is
     * part of what was configured.
     *
     * `'in' => 'modal'` is not used as the adjustment, although 8.5's own
     * example does: `in` is an `open` key, and `open` arrives with the modal
     * it needs. `label` is an adjustment that exists today.
     */
    public function testTheThreeFormsOfAnItemAllProduceAnAction(): void
    {
        $this->writePage(
            actions: "'edit' => ['type' => 'link', 'to' => '@page:ads/{id}/edit', 'icon' => 'pencil'],\n"
                . "'peek' => ['type' => 'link', 'to' => '@page:ads/{id}', 'label' => 'Peek'],",
            columns: "'title' => [],\n"
                . "'tools' => ['type' => 'actions', 'items' => [\n"
                . "    'edit',\n"
                . "    ['use' => '@action:peek', 'label' => 'Look'],\n"
                . "    'log' => ['type' => 'link', 'to' => '@page:ads/{id}'],\n"
                . ']],',
        );

        $column = $this->repository()->get('ads')->region('grid')->column('tools');

        $this->assertSame(ColumnType::Actions, $column->type);
        $this->assertSame(
            ['edit', 'peek', 'log'],
            array_map(static fn (object $a): string => $a->key, $column->actions),
        );

        // The named form is the action as defined.
        $this->assertSame('pencil', $column->actions[0]->icon);
        $this->assertEquals(new PageReference('ads', '{id}/edit'), $column->actions[0]->to);

        // The adjusted form keeps what it did not override and overrides what
        // it did -- so the base really was looked up rather than rebuilt.
        $this->assertSame('Look', $column->actions[1]->label);
        $this->assertEquals(new PageReference('ads', '{id}'), $column->actions[1]->to);

        // The inline form is named by its own key, title-cased like any other.
        $this->assertSame('Log', $column->actions[2]->label);
    }

    /**
     * The collision this task had to resolve. `Definitions::expand()` runs
     * before `Resolver` and claimed every `use` key in the file, so the form
     * the specification documents answered "Unknown definition namespace
     * 'action'" — a refusal about a mechanism the page file was not using.
     */
    public function testTheUseFormIsNotMistakenForASharedDefinition(): void
    {
        $this->writePage(
            actions: "'edit' => ['type' => 'link', 'to' => '@page:ads/{id}/edit'],",
            columns: "'tools' => ['type' => 'actions', 'items' => [['use' => '@action:edit', 'label' => 'Change']]],",
        );

        $column = $this->repository()->get('ads')->region('grid')->column('tools');

        $this->assertSame('Change', $column->actions[0]->label);
    }

    public function testAnItemNamingNoActionOfThePageNamesTheNearest(): void
    {
        $message = $this->refusal(
            actions: "'edit' => ['type' => 'link', 'to' => '@page:ads/{id}/edit'],",
            columns: "'tools' => ['type' => 'actions', 'items' => ['edti']],",
        );

        $this->assertStringContainsString("column 'tools'", $message);
        $this->assertStringContainsString("'edti'", $message);
        $this->assertStringContainsString("Did you mean 'edit'", $message);
    }

    public function testAUseNamingNoActionOfThePageIsRefused(): void
    {
        $message = $this->refusal(
            actions: "'edit' => ['type' => 'link', 'to' => '@page:ads/{id}/edit'],",
            columns: "'tools' => ['type' => 'actions', 'items' => [['use' => '@action:edti']]],",
        );

        $this->assertStringContainsString("Did you mean 'edit'", $message);
    }

    public function testAUseThatIsNotAnActionReferenceIsRefused(): void
    {
        $message = $this->refusal(
            actions: "'edit' => ['type' => 'link', 'to' => '@page:ads/{id}/edit'],",
            columns: "'tools' => ['type' => 'actions', 'items' => [['use' => '@page:ads']]],",
        );

        $this->assertStringContainsString("column 'tools'", $message);
        $this->assertStringContainsString('@action:', $message);
    }

    public function testAnInlineDefinitionWithNoKeyIsRefused(): void
    {
        $message = $this->refusal(
            actions: '',
            columns: "'tools' => ['type' => 'actions', 'items' => [['type' => 'link', 'to' => '@page:ads']]],",
        );

        $this->assertStringContainsString("column 'tools'", $message);
        $this->assertStringContainsString("'use'", $message);
    }

    // --------------------------------------- one construction path, five refusals

    /**
     * Every refusal `buildAction()` holds, asserted through an action defined
     * *inline in a column*. A second construction path would pass each of
     * these and the suite would still be green on the named form.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function inlineRefusals(): iterable
    {
        yield 'a post with no confirm' => [
            "'wipe' => ['type' => 'post', 'to' => '@action:wipe']",
            'confirm',
        ];

        yield 'a link with a confirm' => [
            "'look' => ['type' => 'link', 'to' => '@page:ads', 'confirm' => 'Sure?']",
            'confirm',
        ];

        yield 'a bare URL' => [
            "'offsite' => ['type' => 'link', 'to' => 'https://example.test/x']",
            '@page:',
        ];

        yield 'a when naming no column' => [
            "'wipe' => ['type' => 'post', 'to' => '@action:wipe', 'confirm' => 'Sure?', 'when' => ['stat' => 'a']]",
            "Did you mean 'state'",
        ];

        yield 'a post pointing at a page' => [
            "'wipe' => ['type' => 'post', 'to' => '@page:ads', 'confirm' => 'Sure?']",
            '@action:',
        ];

        yield 'a refresh naming no region' => [
            "'wipe' => ['type' => 'post', 'to' => '@action:wipe', 'confirm' => 'Sure?', 'refresh' => ['grd']]",
            "Did you mean 'grid'",
        ];
    }

    #[DataProvider('inlineRefusals')]
    public function testAnInlineActionIsRefusedByTheSamePathANamedOneIs(string $inline, string $expected): void
    {
        $message = $this->refusal(
            actions: '',
            columns: "'tools' => ['type' => 'actions', 'items' => [{$inline}]],",
        );

        $this->assertStringContainsString("column 'tools'", $message);
        $this->assertStringContainsString($expected, $message);
    }

    // ------------------------------------------------------- a dead key is refused

    public function testAnActionsColumnWithNoItemsIsRefused(): void
    {
        $message = $this->refusal(actions: '', columns: "'tools' => ['type' => 'actions'],");

        $this->assertStringContainsString("column 'tools'", $message);
        $this->assertStringContainsString("'items'", $message);
    }

    public function testItemsOnAColumnThatIsNotAnActionsColumnIsRefused(): void
    {
        $message = $this->refusal(
            actions: "'edit' => ['type' => 'link', 'to' => '@page:ads/{id}/edit'],",
            columns: "'title' => ['items' => ['edit']],",
        );

        $this->assertStringContainsString("column 'title'", $message);
        $this->assertStringContainsString("'items'", $message);
        $this->assertStringContainsString('actions', $message);
    }

    /**
     * An actions column holds controls, not a value: it is never in the
     * select list, so sorting, searching, filtering and linking it are all
     * silently dead configuration — refused by name, exactly as the same keys
     * on a `collection` column already are.
     *
     * @return iterable<string, array{string}>
     */
    public static function deadKeys(): iterable
    {
        yield 'source' => ["'source' => 'id'"];
        yield 'sortable' => ["'sortable' => true"];
        yield 'searchable' => ["'searchable' => true"];
        yield 'link' => ["'link' => true"];
        yield 'filter' => ["'filter' => ['type' => 'text']"];
        yield 'collection' => [
            "'collection' => ['table' => 't', 'foreign_key' => 'f', 'column' => 'c']",
        ];
    }

    #[DataProvider('deadKeys')]
    public function testAKeyThatCouldDoNothingOnAnActionsColumnIsRefused(string $key): void
    {
        $message = $this->refusal(
            actions: "'edit' => ['type' => 'link', 'to' => '@page:ads/{id}/edit'],",
            columns: "'tools' => ['type' => 'actions', 'items' => ['edit'], {$key}],",
        );

        $this->assertStringContainsString("column 'tools'", $message);
    }

    /**
     * An actions column names no database column, so it must never reach the
     * select list — the same exclusion a `collection` column already gets. A
     * query asking for a column called `tools` is a SQL error on every row.
     */
    public function testAnActionsColumnIsNotSelected(): void
    {
        $this->writePage(
            actions: "'edit' => ['type' => 'link', 'to' => '@page:ads/{id}/edit'],",
            columns: "'title' => [],\n'tools' => ['type' => 'actions', 'items' => ['edit']],",
        );

        $page = $this->repository()->get('ads');
        $region = $page->region('grid');
        $query = (new QueryFactory())->build(
            $page,
            $region,
            \RockAdmin\Grid\GridState::fromQuery([], 'grid', $region),
        );

        $this->assertArrayNotHasKey('tools', $query->columns);
        $this->assertArrayHasKey('title', $query->columns);
    }

    // ------------------------------------------------------- the page header

    public function testHeaderButtonsAcceptTheSameThreeForms(): void
    {
        $this->writePage(
            actions: "'export' => ['type' => 'link', 'to' => '@page:ads'],",
            columns: "'title' => [],",
            header: "'buttons' => [\n"
                . "    'export',\n"
                . "    ['use' => '@action:export', 'label' => 'Export all'],\n"
                . "    'wipe' => ['type' => 'post', 'to' => '@action:wipe', 'confirm' => 'Wipe everything?'],\n"
                . '],',
        );

        $buttons = $this->repository()->get('ads')->headerButtons;

        $this->assertSame(
            ['export', 'export', 'wipe'],
            array_map(static fn (object $a): string => $a->key, $buttons),
        );
        $this->assertSame('Export all', $buttons[1]->label);
        $this->assertEquals(new ActionReference('wipe'), $buttons[2]->to);
    }

    /**
     * A header button is drawn once, above the grid, with no row behind it.
     * A `when` tests a row's values, so on a header button it can never be
     * true — and the button would silently never appear.
     */
    public function testAWhenOnAHeaderButtonIsRefused(): void
    {
        $message = $this->refusal(
            actions: '',
            columns: "'title' => [], 'state' => [],",
            header: "'buttons' => ['wipe' => ['type' => 'post', 'to' => '@action:wipe', "
                . "'confirm' => 'Sure?', 'when' => ['state' => 'active']]],",
        );

        $this->assertStringContainsString('header button', $message);
        $this->assertStringContainsString("'when'", $message);
    }

    /**
     * `{id}` is substituted from the row being rendered. A header button has
     * no row, so the placeholder would reach `UrlGenerator::reference()` and
     * be refused there, blaming a template three layers from the page file.
     */
    public function testARowPlaceholderInAHeaderButtonIsRefused(): void
    {
        $message = $this->refusal(
            actions: '',
            columns: "'title' => [],",
            header: "'buttons' => ['peek' => ['type' => 'link', 'to' => '@page:ads/{id}/edit']],",
        );

        $this->assertStringContainsString('header button', $message);
        $this->assertStringContainsString('{id}', $message);
    }

    // ------------------------------------------------------- fixtures

    private function repository(): PageRepository
    {
        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );
    }

    private function refusal(string $actions, string $columns, string $header = ''): string
    {
        $this->writePage($actions, $columns, $header);

        try {
            $this->repository()->get('ads');
        } catch (PageException $e) {
            return $e->getMessage();
        }

        $this->fail('The page loaded.');
    }

    private function writePage(string $actions, string $columns, string $header = ''): void
    {
        $body = "<?php\n\nreturn [\n"
            . "    'title' => 'Ads',\n"
            . "    'entity' => ['table' => 'ads'],\n"
            . ($header === '' ? '' : "    'header' => [\n{$header}\n    ],\n")
            . ($actions === '' ? '' : "    'actions' => [\n{$actions}\n    ],\n")
            . "    'regions' => [\n"
            . "        'grid' => ['type' => 'list', 'columns' => [\n"
            . "            'id' => [], 'state' => [],\n{$columns}\n"
            . "        ]],\n"
            . "        'form' => ['type' => 'form', 'form' => ['fields' => ['title' => []]]],\n"
            . "    ],\n"
            . "];\n";

        file_put_contents($this->root . '/pages/ads.php', $body);
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
}
