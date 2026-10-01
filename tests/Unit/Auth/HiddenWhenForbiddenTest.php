<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Auth;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\Gate;
use RockAdmin\Auth\Identity;
use RockAdmin\Config\Enums;
use RockAdmin\Db\Result;
use RockAdmin\Db\RowReader;
use RockAdmin\Db\Sql;
use RockAdmin\Form\FormRegion;
use RockAdmin\Form\FormView;
use RockAdmin\Grid\CellFormatter;
use RockAdmin\Grid\GridState;
use RockAdmin\Grid\ListRegion;
use RockAdmin\Grid\ListView;
use RockAdmin\Grid\QueryFactory;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\Access;
use RockAdmin\Tests\Support\FakeRowSource;
use RockAdmin\View\Escaper;
use RockAdmin\View\Menu;
use RockAdmin\View\MenuItemView;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * Never draw what the gate would refuse (spec 9.2).
 *
 * A visible button that returns 403 is a bug, not a safeguard: the menu, the
 * create button, a row's edit link and the delete button are all decided by
 * the same `Gate::allows()` the kernel calls at step 7, so there is no second
 * rule for what rendering may show.
 *
 * **The cell counts are asserted, not the classes.** Milestone 7 shipped a
 * grid whose header said there was no actions column while every row drew
 * one, and every class on that page was correct — the defect was visible only
 * as a body one cell wider than its header. So the header and the body are
 * parsed with `DOMDocument` and counted as elements; counting `<th` in a
 * string counts the substring wherever it appears and proves nothing.
 *
 * `null` in place of a permission list means nobody is signed in.
 */
#[CoversClass(Menu::class)]
#[CoversClass(ListRegion::class)]
#[CoversClass(FormRegion::class)]
#[CoversClass(ListView::class)]
final class HiddenWhenForbiddenTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-hidden-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);

        $this->writePage('ads', true);
        $this->writePage('reports', false);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    // ------------------------------------------------------- the grid

    public function testAViewerSeesThePageAndNoCreateButtonAndNoEditLinks(): void
    {
        $view = $this->grid(['*.view']);

        $this->assertNull($view->createUrl, 'a viewer may not create');
        $this->assertFalse($view->hasRowActions(), 'a viewer has nothing to do to a row');

        foreach ($view->rows as $row) {
            $this->assertNull($row->editUrl, 'a viewer may not edit');
        }

        $html = $this->render($view);

        $this->assertSame(0, $this->elementsWithClass($html, 'ra-grid-create'));
        $this->assertSame(0, $this->elementsWithClass($html, 'ra-grid-edit'));
        $this->assertStringContainsString('ra-grid-table', $html, 'the viewer still sees the grid itself');
    }

    public function testAnEditorSeesTheCreateButtonAndAnEditLinkOnEveryRow(): void
    {
        $view = $this->grid(['ads.*']);

        $this->assertNotNull($view->createUrl);
        $this->assertTrue($view->hasRowActions());

        $html = $this->render($view);

        $this->assertSame(1, $this->elementsWithClass($html, 'ra-grid-create'));
        $this->assertSame(2, $this->elementsWithClass($html, 'ra-grid-edit'), 'one per row');
    }

    /**
     * The header and the body must agree about how many cells a row has,
     * whoever is looking: two columns, plus an actions column exactly when
     * the rows carry one.
     *
     * @return iterable<string, array{list<string>, int}>
     */
    public static function cellCounts(): iterable
    {
        yield 'a viewer has no actions column' => [['*.view'], 2];
        yield 'an editor has one' => [['ads.*'], 3];
        yield 'update alone is still an actions column' => [['ads.view', 'ads.update'], 3];
        yield 'create without update draws no row action' => [['ads.view', 'ads.create'], 2];
    }

    /** @param list<string> $permissions */
    #[DataProvider('cellCounts')]
    public function testTheHeaderAndTheBodyAgreeAboutHowManyCellsARowHas(
        array $permissions,
        int $expected,
    ): void {
        $counts = $this->cellCountsOf($this->render($this->grid($permissions)));

        $this->assertSame($expected, $counts['header']);
        $this->assertSame([$expected, $expected], $counts['rows']);
    }

    /**
     * A page with no form region generates no write permission at all, so
     * even `*` draws no create button and no edit link on it. That is the one
     * fact — `Permission::forPage()` reads `firstFormRegion()` — reaching the
     * grid through the gate, rather than being read a second time inside it.
     */
    public function testAReadOnlyPageDrawsNoActionsEvenForASuperadmin(): void
    {
        $view = $this->grid(['*'], 'reports');

        $this->assertNull($view->createUrl);
        $this->assertFalse($view->hasRowActions());

        $counts = $this->cellCountsOf($this->render($view));

        $this->assertSame(2, $counts['header']);
        $this->assertSame([2, 2], $counts['rows']);
    }

    // ------------------------------------------------------- the form

    public function testAUserWithUpdateButNotDeleteSeesAnEditLinkAndNoDeleteButton(): void
    {
        $permissions = ['ads.view', 'ads.update'];

        $this->assertSame(
            2,
            $this->elementsWithClass($this->render($this->grid($permissions)), 'ra-grid-edit'),
            'two rows, two edit links',
        );

        $form = $this->form($permissions);

        $this->assertNull($form->deleteAction, 'nothing may delete without ads.delete');
        $this->assertSame(0, $this->elementsWithClass($this->renderForm($form), 'ra-form-delete'));
    }

    public function testAUserWithDeleteSeesTheDeleteButton(): void
    {
        $form = $this->form(['ads.*']);

        $this->assertNotNull($form->deleteAction);
        $this->assertSame(1, $this->elementsWithClass($this->renderForm($form), 'ra-form-delete'));
    }

    // ------------------------------------------------------- the menu

    public function testTheMenuOmitsPagesTheUserMayNotViewAndKeepsTheOnesTheyMay(): void
    {
        $this->assertSame(['Dashboard', 'Ads'], $this->menuLabels(['ads.*']), 'reports is not theirs to see');
    }

    public function testTheMenuKeepsEveryPageAViewerMaySee(): void
    {
        $this->assertSame(['Dashboard', 'Ads', 'Reports'], $this->menuLabels(['*.view']));
    }

    public function testTheMenuMarksThePageBeingLookedAt(): void
    {
        $items = $this->menu(['*.view'])->forPage('ads');

        $this->assertSame(['dashboard', 'ads', 'reports'], array_map(
            static fn (MenuItemView $item): ?string => $item->key,
            $items,
        ));
        $this->assertSame([false, true, false], array_map(
            static fn (MenuItemView $item): bool => $item->active,
            $items,
        ));
    }

    // ------------------------------------------------------- nobody

    /**
     * Nothing should reach a region without passing the kernel's gate, so an
     * anonymous render is arguably impossible — but `demo/index.php` and any
     * test can build one, and the honest answer for "nobody" is "nothing":
     * not a crash, and not a page full of buttons that 403.
     */
    public function testAnAnonymousRenderDrawsNoneOfThemAndDoesNotCrash(): void
    {
        $view = $this->grid(null);

        $this->assertNull($view->createUrl);
        $this->assertFalse($view->hasRowActions());

        $counts = $this->cellCountsOf($this->render($view));

        $this->assertSame(2, $counts['header']);
        $this->assertSame([2, 2], $counts['rows']);

        $this->assertNull($this->form(null)->deleteAction);
        $this->assertSame([], $this->menuLabels(null));
    }

    // ------------------------------------------------------- fixtures

    /**
     * The gate and the identity a permission list stands for. Null is nobody
     * at all: no session, and a gate that therefore grants nothing.
     *
     * @param  ?list<string>          $permissions
     * @return array{Gate, Identity}
     */
    private function access(?array $permissions): array
    {
        $pages = $this->pages();
        $roles = ['tester' => ['permissions' => $permissions ?? []]];
        $gate = Access::gate([$pages->get('ads'), $pages->get('reports')], $roles);

        return [$gate, $permissions === null ? Access::identityFor(null) : Access::identity(['tester'])];
    }

    /** @param ?list<string> $permissions */
    private function grid(?array $permissions, string $page = 'ads'): ListView
    {
        [$gate, $identity] = $this->access($permissions);

        $definition = $this->pages()->get($page);
        $region = $definition->region('grid');

        $rows = new FakeRowSource([new Result(
            [['id' => 7, 'title' => 'One'], ['id' => 8, 'title' => 'Two']],
            2,
            [new Sql('SELECT 1')],
        )]);

        $list = new ListRegion(
            $rows,
            new QueryFactory(),
            new CellFormatter(),
            new UrlGenerator('/admin'),
            $gate,
            $identity,
        );

        return $list->render($definition, $region, GridState::fromQuery([], 'grid', $region));
    }

    /** @param ?list<string> $permissions */
    private function form(?array $permissions): FormView
    {
        [$gate, $identity] = $this->access($permissions);

        $definition = $this->pages()->get('ads');
        $region = $definition->region('form');

        $rows = new FakeRowSource([new Result(
            [['id' => 7, 'title' => 'One']],
            null,
            [new Sql('SELECT 1')],
        )]);

        $forms = new FormRegion(
            new RowReader($rows),
            new UrlGenerator('/admin'),
            new Csrf(new ArraySessionStore()),
            $gate,
            $identity,
        );

        $view = $forms->edit($definition, $region, '7');

        $this->assertNotNull($view, 'the fixture row must be found');

        return $view;
    }

    /** @param ?list<string> $permissions */
    private function menu(?array $permissions): Menu
    {
        [$gate, $identity] = $this->access($permissions);

        return new Menu(
            [
                new MenuItemView('Dashboard', '/admin', key: 'dashboard'),
                new MenuItemView('Ads', '/admin/p/ads', key: 'ads'),
                new MenuItemView('Reports', '/admin/p/reports', key: 'reports'),
            ],
            $gate,
            $identity,
            $this->pages(),
        );
    }

    /**
     * @param  ?list<string> $permissions
     * @return list<string>
     */
    private function menuLabels(?array $permissions): array
    {
        return array_map(
            static fn (MenuItemView $item): string => $item->label,
            $this->menu($permissions)->forPage('ads'),
        );
    }

    private function pages(): PageRepository
    {
        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );
    }

    private function render(ListView $view): string
    {
        return $this->renderer()->render('region/list/region', $view);
    }

    private function renderForm(FormView $view): string
    {
        return $this->renderer()->render('region/form/region', $view);
    }

    private function renderer(): Renderer
    {
        return new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/admin'));
    }

    // ------------------------------------------------------- reading the HTML

    /**
     * How many cells the header has, and how many each body row has.
     *
     * Parsed, never matched: a regular expression over `<th` counts the
     * substring wherever it appears, including inside an attribute, and the
     * milestone 7 defect this test exists for was invisible to exactly that
     * kind of counting.
     *
     * @return array{header: int, rows: list<int>}
     */
    private function cellCountsOf(string $html): array
    {
        $xpath = new DOMXPath($this->document($html));

        $headers = $xpath->query('//thead/tr/th');
        $rows = $xpath->query('//tbody/tr');

        $this->assertNotFalse($headers);
        $this->assertNotFalse($rows);

        $counts = [];

        foreach ($rows as $row) {
            // A node list iterates as DOMNode|DOMNameSpaceNode; only an
            // element can hold cells, and asserting that rather than casting
            // keeps the failure honest if the document ever changes shape.
            $this->assertInstanceOf(DOMElement::class, $row);

            $cells = $xpath->query('./td', $row);
            $this->assertNotFalse($cells);
            $counts[] = $cells->length;
        }

        return ['header' => $headers->length, 'rows' => $counts];
    }

    /** How many elements carry this class, counted as elements. */
    private function elementsWithClass(string $html, string $class): int
    {
        $xpath = new DOMXPath($this->document($html));
        $found = $xpath->query(
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]",
        );

        $this->assertNotFalse($found);

        return $found->length;
    }

    private function document(string $html): DOMDocument
    {
        $dom = new DOMDocument();
        $loaded = $dom->loadHTML(
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>t</title></head><body>'
            . $html . '</body></html>',
            \LIBXML_NOERROR | \LIBXML_NOWARNING,
        );

        $this->assertTrue($loaded, 'the rendered fragment must parse as HTML');

        return $dom;
    }

    // ------------------------------------------------------- page files

    private function writePage(string $name, bool $writable): void
    {
        $form = $writable
            ? "'form' => ['type' => 'form', 'form' => ['fields' => ['title' => ['type' => 'text']]]],"
            : '';

        $body = "<?php\n\nreturn [\n"
            . "    'title' => '" . ucfirst($name) . "',\n"
            . "    'entity' => ['table' => '{$name}'],\n"
            . "    'regions' => [\n"
            . "        'grid' => [\n"
            . "            'type' => 'list',\n"
            . "            'columns' => ['id' => ['source' => 'id'], 'title' => ['source' => 'title']],\n"
            . "        ],\n"
            . "        {$form}\n"
            . "    ],\n"
            . "];\n";

        file_put_contents($this->root . '/pages/' . $name . '.php', $body);
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
