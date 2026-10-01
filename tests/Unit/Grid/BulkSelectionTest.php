<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RockAdmin\Grid\CellView;
use RockAdmin\Grid\ListView;
use RockAdmin\Grid\PaginationView;
use RockAdmin\Grid\RowView;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;
use RockAdmin\View\ActionView;
use RockAdmin\View\Escaper;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * The selection column and the bulk bar, as markup.
 *
 * Two properties here are the whole reason this file exists, and both fail
 * silently when they are broken.
 *
 * **A checkbox must not be inside the selection form.** A `post` action draws
 * a `<form>` inside a `<td>`, so a selection form wrapping the table would
 * contain it — and nested forms are not legal HTML. The browser drops the
 * inner one without a word, so with JavaScript off every row action becomes a
 * button that does nothing, and nothing anywhere says why. The checkboxes
 * therefore point at the form by `form="…"` from outside it.
 *
 * **The header and the body must agree about how many cells a row has.** That
 * is the third time this project has had to hold two templates to one value;
 * milestone 7 shipped the disagreement twice.
 */
#[CoversClass(ListView::class)]
#[CoversNothing]
final class BulkSelectionTest extends TestCase
{
    public function testTheSelectionFormContainsNoCheckboxes(): void
    {
        $html = $this->render($this->view());

        $form = $this->selectionForm($html);

        $this->assertStringNotContainsString(
            '<input type="checkbox"',
            $form,
            'A checkbox inside the selection form means the form wraps the table, which means it wraps '
            . 'the forms row actions draw — and a nested form is dropped by the browser in silence.',
        );
        $this->assertStringNotContainsString('<table', $form);
    }

    public function testEveryCheckboxNamesTheFormItSubmitsWith(): void
    {
        $html = $this->render($this->view());

        preg_match_all('/<input[^>]*data-ra-select[\s>][^>]*>/', $html, $boxes);

        $this->assertNotEmpty($boxes[0], 'No selection checkbox was rendered at all.');

        foreach ($boxes[0] as $box) {
            $this->assertStringContainsString(
                'form="ra-bulk-grid"',
                $box,
                'A checkbox outside its form that does not name it is simply never submitted.',
            );
        }
    }

    public function testTheFormTheCheckboxesNameIsTheOneThatIsRendered(): void
    {
        // The id is built in two places — `ListRegion` writes it onto each row
        // before the view exists, and the view answers it for the form — so
        // this is the assertion that catches the two drifting apart. It would
        // not raise anything at runtime: `<input form="nothing">` is valid and
        // is never submitted.
        $html = $this->render($this->view());

        if (preg_match('/id="([^"]+)"/', $this->selectionForm($html), $form) !== 1) {
            $this->fail('The selection form carries no id, so nothing can point at it.');
        }

        $id = $form[1];

        preg_match_all('/<input[^>]*data-ra-select[\s>][^>]*>/', $html, $boxes);

        $this->assertNotEmpty($boxes[0]);

        foreach ($boxes[0] as $box) {
            $this->assertStringContainsString(
                'form="' . $id . '"',
                $box,
                'A checkbox names a form id that is not the one rendered. Nothing raises: an '
                . '<input form="nothing"> is valid HTML and is simply never submitted.',
            );
        }
    }

    public function testTheHeaderAndTheBodyAgreeAboutTheSelectionCell(): void
    {
        $html = $this->render($this->view());

        $this->assertSame(
            substr_count($html, 'ra-grid-head-select'),
            1,
            'The header draws exactly one selection cell.',
        );
        $this->assertSame(
            substr_count($html, 'ra-grid-cell-select'),
            2,
            'Each of the two rows draws exactly one selection cell.',
        );
    }

    public function testAGridWithNoBulkActionsHasNoSelectionColumnAtAll(): void
    {
        $html = $this->render($this->view(bulkActions: []));

        $this->assertStringNotContainsString('ra-grid-head-select', $html);
        $this->assertStringNotContainsString('ra-grid-cell-select', $html);
        $this->assertStringNotContainsString('ra-grid-bulk', $html);
    }

    public function testEachBulkButtonCarriesItsOwnAction(): void
    {
        // One form, several actions. `formaction` is how HTML says "this
        // submit goes somewhere else", so the token is written once and which
        // action runs is the button that was pressed — rather than a hidden
        // input a script would have to keep in step with the control.
        $html = $this->render($this->view());

        $this->assertStringContainsString('formaction="/admin/a/ads/archive"', $html);
        $this->assertStringContainsString('formaction="/admin/a/ads/delete"', $html);
    }

    public function testTheSelectionFormCarriesATokenAndTheBulkMarker(): void
    {
        $html = $this->render($this->view());
        $form = $this->selectionForm($html);

        $this->assertStringContainsString('value="the-token"', $form);
        $this->assertStringContainsString('name="_bulk"', $form);
    }

    public function testTheSelectAllBoxIsNeverSubmitted(): void
    {
        // It is the one control here that does nothing without JavaScript, so
        // it must not be part of what is submitted: a `name` on it would send
        // a value the server would have to know to ignore.
        $html = $this->render($this->view());

        if (preg_match('/<input[^>]*data-ra-select-all[^>]*>/', $html, $match) !== 1) {
            $this->fail('No select-all box was rendered.');
        }

        $this->assertStringNotContainsString('name=', $match[0]);
    }

    public function testARowWithNoKeyDrawsNoCheckbox(): void
    {
        // Nothing to put in the value. The cell is simply not drawn, which is
        // why `selectionFormId` is nullable per row rather than a flag on the
        // view: the thing that decides the cell exists is the same thing that
        // makes it work.
        $view = $this->view(rows: [
            new RowView(null, [$this->cell('x')], '', 'ra-grid-row', null, null),
        ]);

        $html = $this->render($view);

        $this->assertStringNotContainsString('ra-grid-cell-select', $html);
    }

    public function testAnEmptyGridDrawsNoBulkBar(): void
    {
        // A bar offering to act on a selection, above a table with nothing to
        // select, is a control that cannot be used.
        $html = $this->render($this->view(rows: []));

        $this->assertStringNotContainsString('ra-grid-bulk', $html);
    }

    // --- harness ---

    private function render(ListView $view): string
    {
        $renderer = new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/admin'));

        return $renderer->render('region/list/region', $view);
    }

    /**
     * Everything between the selection form's open and close tags.
     *
     * Found by walking back from the class to the `<form` that carries it,
     * because the template writes its attributes one per line and a search
     * for `<form class="…"` finds nothing. The first version of this did
     * exactly that and reported "no selection form was rendered" about a form
     * that was right there.
     */
    private function selectionForm(string $html): string
    {
        $at = strpos($html, 'class="ra-grid-bulk"');

        if ($at === false) {
            $this->fail('No selection form was rendered.');
        }

        $start = strrpos(substr($html, 0, $at), '<form');

        $this->assertNotFalse($start, 'The bulk class is not on a form.');

        $end = strpos($html, '</form>', $start);

        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }

    /**
     * @param list<ActionView> $bulkActions
     * @param list<RowView>    $rows
     */
    private function view(?array $bulkActions = null, ?array $rows = null): ListView
    {
        $bulkActions ??= [
            new ActionView('archive', 'Archive', '/admin/a/ads/archive', true, 'Archive these?', null, 'the-token'),
            new ActionView('bin', 'Delete', '/admin/a/ads/delete', true, 'Delete these?', null, 'the-token'),
        ];

        // No bulk actions means no selection form, so the rows carry no form
        // id to point at -- which is exactly what ListRegion decides, from the
        // same one value.
        $formId = $bulkActions === [] ? null : 'ra-bulk-grid';

        return new ListView(
            key: 'grid',
            pageName: 'ads',
            columns: [],
            rows: $rows ?? [
                new RowView(1, [$this->cell('one')], '/admin/p/ads/1', 'ra-grid-row', null, $formId),
                new RowView(2, [$this->cell('two')], '/admin/p/ads/2', 'ra-grid-row', null, $formId),
            ],
            filters: [],
            pagination: PaginationView::of(1, 20, 2, 2, static fn (int $n): string => "/admin/r/ads/grid?grid[page]={$n}"),
            search: '',
            searchable: false,
            regionUrl: '/admin/r/ads/grid',
            pageUrl: '/admin/p/ads',
            bulkActions: $bulkActions,
            bulkToken: 'the-token',
        );
    }

    private function cell(string $text): CellView
    {
        return new CellView(
            key: 'title',
            value: $text,
            text: $text,
            type: ColumnType::Text,
            display: Display::Plain,
            classes: 'ra-grid-cell ra-grid-cell-title',
            url: null,
            attributes: [],
            percent: null,
            variant: null,
        );
    }
}
