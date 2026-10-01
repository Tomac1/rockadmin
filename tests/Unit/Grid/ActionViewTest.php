<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\Gate;
use RockAdmin\Config\Enums;
use RockAdmin\Db\Result;
use RockAdmin\Db\Sql;
use RockAdmin\Grid\ActionBuilder;
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
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * An actions column as it is actually drawn.
 *
 * Four things this asserts rather than assumes:
 *
 * **A `{id}` target renders the row's own key**, through
 * `RowPlaceholders::reference()`. Building the URL from the reference directly
 * would bypass every guarantee that class makes — the encoding, the refusal of
 * a column the row does not carry, of a `{{…}}`, of a value with a slash — and
 * the only symptom would be `UrlGenerator` blaming a template.
 *
 * **The gate decides what is drawn** (spec 9.2), through the same
 * `Gate::allows()` the kernel calls, not a second rule.
 *
 * **`when` decides what is offered**, per row, from that row's own values.
 *
 * **The header and the body agree about how many cells a row has.** This
 * project shipped a header/body mismatch twice, and an `actions` column is a
 * third way to do it: a page with an actions column *and* a writable grid
 * could claim two trailing cells, or claim one and draw two. The counts are
 * read out of a parsed document, never matched out of the string — counting
 * `<th` in HTML counts the substring wherever it appears.
 */
#[CoversClass(ActionBuilder::class)]
#[CoversClass(ListRegion::class)]
#[CoversClass(ListView::class)]
final class ActionViewTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-actionview-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    // ------------------------------------------------------- the row placeholder

    public function testARowPlaceholderInATargetRendersTheRowsOwnKey(): void
    {
        $html = $this->render($this->grid(['ads.*']));

        $hrefs = $this->attributes($html, '//td[contains(@class, "ra-grid-cell-tools")]//a', 'href');

        $this->assertSame(['/admin/p/ads/7/edit', '/admin/p/ads/8/edit'], $hrefs);
    }

    /**
     * The value is substituted raw and encoded exactly once, by
     * `UrlGenerator::build()`. A key with a space in it therefore arrives
     * percent-encoded and addresses the row it was drawn for.
     */
    public function testAKeyNeedingEncodingStillAddressesItsOwnRow(): void
    {
        $html = $this->render($this->grid(['ads.*'], [
            ['id' => 'a b', 'title' => 'One', 'state' => 'active'],
        ]));

        $this->assertSame(
            ['/admin/p/ads/a%20b/edit'],
            $this->attributes($html, '//td[contains(@class, "ra-grid-cell-tools")]//a', 'href'),
        );
    }

    /**
     * A row that does not carry the column the placeholder names is refused
     * loudly, by `RowPlaceholders` — which is the proof that the substitution
     * really goes through it rather than being done here.
     */
    public function testARowMissingThePlaceholdersColumnIsRefusedNamingTheColumn(): void
    {
        $this->expectExceptionMessage('{id}');

        $this->render($this->grid(['ads.*'], [['title' => 'One', 'state' => 'active']]));
    }

    // ------------------------------------------------------- the gate

    public function testAnActionTheReaderMayNotPerformIsNotDrawn(): void
    {
        $html = $this->render($this->grid(['ads.view']));

        $this->assertSame(0, $this->elements($html, '//*[contains(@class, "ra-action-edit")]'));
        $this->assertSame(0, $this->elements($html, '//*[contains(@class, "ra-action-archive")]'));
        $this->assertStringContainsString('ra-grid-table', $html, 'the viewer still sees the grid');
    }

    public function testAnActionTheReaderMayPerformIsDrawn(): void
    {
        $html = $this->render($this->grid(['ads.*']));

        $this->assertSame(2, $this->elements($html, '//*[contains(@class, "ra-action-edit")]'), 'one per row');
    }

    // ------------------------------------------------------- when

    public function testAnActionIsOfferedOnlyOnARowItsWhenMatches(): void
    {
        $html = $this->render($this->grid(['ads.*'], [
            ['id' => 7, 'title' => 'One', 'state' => 'active'],
            ['id' => 8, 'title' => 'Two', 'state' => 'archived'],
        ]));

        $this->assertSame(2, $this->elements($html, '//*[contains(@class, "ra-action-edit")]'));
        $this->assertSame(
            1,
            $this->elements($html, '//*[contains(@class, "ra-action-archive")]'),
            'only the active row may be archived',
        );
    }

    // ------------------------------------------------------- a post

    public function testAPostActionIsAFormCarryingTheTokenAndAConfirmation(): void
    {
        $html = $this->render($this->grid(['ads.*'], [
            ['id' => 7, 'state' => 'active'],
            ['id' => 8, 'state' => 'archived'],
        ]));
        $document = $this->document($html);
        $xpath = new DOMXPath($document);

        $forms = $xpath->query('//td[contains(@class, "ra-grid-cell-tools")]//form');
        $this->assertNotFalse($forms);
        $this->assertSame(1, $forms->length, 'one archivable row');

        $form = $forms->item(0);
        $this->assertInstanceOf(DOMElement::class, $form);
        $this->assertSame('post', strtolower($form->getAttribute('method')));
        $this->assertSame('/admin/a/ads/archive', $form->getAttribute('action'));

        $tokens = $xpath->query('.//input[@name="' . Csrf::FIELD . '"]', $form);
        $this->assertNotFalse($tokens);
        $this->assertSame(1, $tokens->length, 'a post action carries the CSRF token');

        $this->assertSame(
            ['Archive this ad?'],
            $this->attributes($html, '//button[@data-ra-confirm]', 'data-ra-confirm'),
        );
    }

    public function testALinkActionIsAnOrdinaryAnchorWithNoForm(): void
    {
        $html = $this->render($this->grid(['ads.*'], [['id' => 7, 'title' => 'One', 'state' => 'archived']]));

        $this->assertSame(1, $this->elements($html, '//a[contains(@class, "ra-action-edit")]'));
        $this->assertSame(0, $this->elements($html, '//td[contains(@class, "ra-grid-cell-tools")]//form'));
    }

    // ------------------------------------------------------- the cell counts

    /**
     * The trap with a name and two commits. Three columns are declared — `id`,
     * `title` and the `tools` actions column — so a row has three cells and
     * the header three, whoever is looking. The built-in trailing Edit cell
     * must *not* appear as a fourth: a page that declares an actions column
     * has said where its row controls go.
     *
     * @return iterable<string, array{list<string>, int}>
     */
    public static function cellCountsWithAnActionsColumn(): iterable
    {
        yield 'a viewer' => [['ads.view'], 3];
        yield 'an editor' => [['ads.*'], 3];
        yield 'update without view' => [['ads.update'], 3];
    }

    /** @param list<string> $permissions */
    #[DataProvider('cellCountsWithAnActionsColumn')]
    public function testTheHeaderAndTheBodyAgreeWithAnActionsColumn(array $permissions, int $expected): void
    {
        $counts = $this->cellCountsOf($this->render($this->grid($permissions)));

        $this->assertSame($expected, $counts['header']);
        $this->assertSame([$expected, $expected], $counts['rows']);
    }

    /**
     * And without one, the built-in trailing cell behaves exactly as it did
     * before this milestone — so adding the `actions` column type did not
     * change the page that does not use it.
     *
     * @return iterable<string, array{list<string>, int}>
     */
    public static function cellCountsWithoutAnActionsColumn(): iterable
    {
        yield 'a viewer has no actions cell' => [['plain.view'], 2];
        yield 'an editor has one' => [['plain.*'], 3];
    }

    /** @param list<string> $permissions */
    #[DataProvider('cellCountsWithoutAnActionsColumn')]
    public function testTheHeaderAndTheBodyAgreeWithoutAnActionsColumn(array $permissions, int $expected): void
    {
        $counts = $this->cellCountsOf($this->render($this->grid($permissions, page: 'plain')));

        $this->assertSame($expected, $counts['header']);
        $this->assertSame([$expected, $expected], $counts['rows']);
    }

    /**
     * An actions column takes over the row's controls, so the grid's own Edit
     * link is not drawn beside it: two Edit controls in one row, one of them
     * configured and one of them not, is the kind of thing nobody notices
     * until a project asks why it cannot remove the second.
     */
    public function testAnActionsColumnReplacesTheGridsOwnEditCell(): void
    {
        $view = $this->grid(['ads.*']);

        $this->assertFalse($view->hasRowActions());

        $html = $this->render($view);

        $this->assertSame(0, $this->elements($html, '//*[contains(@class, "ra-grid-edit")]'));
        $this->assertSame(0, $this->elements($html, '//*[contains(@class, "ra-grid-cell-actions")]'));
    }

    // ------------------------------------------------------- fixtures

    /**
     * @param  list<string>               $permissions
     * @param  ?list<array<string,mixed>> $rows
     */
    private function grid(array $permissions, ?array $rows = null, string $page = 'ads'): ListView
    {
        $this->writePages();

        $pages = $this->repository();
        $gate = Access::gate([$pages->get('ads'), $pages->get('plain')], [
            'tester' => ['permissions' => $permissions],
        ]);
        $identity = Access::identity(['tester']);

        $definition = $pages->get($page);
        $region = $definition->region('grid');

        $source = new FakeRowSource([new Result(
            $rows ?? [
                ['id' => 7, 'title' => 'One', 'state' => 'active'],
                ['id' => 8, 'title' => 'Two', 'state' => 'active'],
            ],
            2,
            [new Sql('SELECT 1')],
        )]);

        $list = new ListRegion(
            $source,
            new QueryFactory(),
            new CellFormatter(),
            new UrlGenerator('/admin'),
            $gate,
            $identity,
            new Csrf(new ArraySessionStore()),
        );

        return $list->render($definition, $region, GridState::fromQuery([], 'grid', $region));
    }

    private function repository(): PageRepository
    {
        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );
    }

    /**
     * `ads` carries an actions column: an `edit` link with a row placeholder
     * and an `archive` post restricted to the active rows. `plain` carries
     * none, so the grid's own trailing cell is what decides its width.
     */
    private function writePages(): void
    {
        file_put_contents($this->root . '/pages/ads.php', <<<'PHP'
            <?php

            return [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'actions' => [
                    'edit' => [
                        'type' => 'link',
                        'to' => '@page:ads/{id}/edit',
                        'permission' => 'ads.update',
                    ],
                    'archive' => [
                        'type' => 'post',
                        'to' => '@action:archive',
                        'confirm' => 'Archive this ad?',
                        'permission' => 'ads.update',
                        'when' => ['state' => 'active'],
                    ],
                ],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => [
                        'id' => [],
                        'state' => [],
                        'tools' => ['type' => 'actions', 'items' => ['edit', 'archive']],
                    ]],
                    'form' => ['type' => 'form', 'form' => ['fields' => ['title' => []]]],
                ],
            ];
            PHP);

        file_put_contents($this->root . '/pages/plain.php', <<<'PHP'
            <?php

            return [
                'title' => 'Plain',
                'entity' => ['table' => 'plain'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['id' => [], 'title' => []]],
                    'form' => ['type' => 'form', 'form' => ['fields' => ['title' => []]]],
                ],
            ];
            PHP);
    }

    private function render(ListView $view): string
    {
        return (new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/admin')))
            ->render('region/list/region', $view);
    }

    // ------------------------------------------------------- reading the HTML

    /**
     * How many cells the header has, and how many each body row has.
     *
     * Parsed, never matched: a regular expression over `<th` counts the
     * substring wherever it appears, and the defect this guards against was
     * invisible to exactly that kind of counting.
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
            $this->assertInstanceOf(DOMElement::class, $row);
            $cells = $xpath->query('./td', $row);
            $this->assertNotFalse($cells);
            $counts[] = $cells->length;
        }

        return ['header' => $headers->length, 'rows' => $counts];
    }

    private function elements(string $html, string $query): int
    {
        $found = (new DOMXPath($this->document($html)))->query($query);

        $this->assertNotFalse($found);

        return $found->length;
    }

    /** @return list<string> */
    private function attributes(string $html, string $query, string $attribute): array
    {
        $found = (new DOMXPath($this->document($html)))->query($query);

        $this->assertNotFalse($found);

        $values = [];

        foreach ($found as $node) {
            $this->assertInstanceOf(DOMElement::class, $node);
            $values[] = $node->getAttribute($attribute);
        }

        return $values;
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
