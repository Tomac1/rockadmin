<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RockAdmin\Grid\CellView;
use RockAdmin\Grid\FieldView;
use RockAdmin\Grid\PreviewView;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;
use RockAdmin\View\Escaper;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * The preview region's templates, rendered as they ship over hand-built
 * views — the same style as `ListTemplatesTest`. `field.php` reuses the
 * grid's own `region/list/cell/*.php` partials through `CellPartial`, so
 * this is about the promises specific to a preview: null vs. an honest
 * false or empty string, the wide-row rule, and the reused escaping —
 * `ListTemplatesTest::testEveryCellPartialEscapesWhatItPrints()` already
 * proves every cell partial itself escapes, so this does not repeat that
 * per-partial sweep, only that a preview's own wrapping does not undo it.
 */
#[CoversNothing]
final class PreviewTemplatesTest extends TestCase
{
    private function renderer(): Renderer
    {
        return new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/admin'));
    }

    /** @param list<FieldView> $fields */
    private function previewView(string $key = 'preview', string $title = 'Test', string $id = '1', array $fields = []): PreviewView
    {
        return new PreviewView($key, $title, $id, $fields);
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
            percent: null,
            variant: null,
        );
    }

    private function field(
        string $key = 'title',
        string $label = 'Title',
        ?CellView $cell = null,
        bool $wide = false,
    ): FieldView {
        return new FieldView($key, $label, $cell ?? $this->cell(key: $key), $wide);
    }

    // --- region.php ---

    public function testTheRegionCarriesItsKeyAndTheRowsOwnTitle(): void
    {
        $view = $this->previewView(key: 'preview', title: 'Horské kolo');

        $html = $this->renderer()->render('region/preview/region', $view);

        $this->assertStringContainsString('data-ra-region="preview"', $html);
        $this->assertStringContainsString('<h2 class="ra-preview-title">Horské kolo</h2>', $html);
        $this->assertStringContainsString('ra-preview', $html);
    }

    public function testAnEmptyFieldListSaysSoRatherThanRenderingAnEmptyList(): void
    {
        $view = $this->previewView(fields: []);

        $html = $this->renderer()->render('region/preview/region', $view);

        $this->assertStringContainsString('no fields to show', $html);
        $this->assertStringNotContainsString('<dl', $html);
    }

    public function testEveryFieldIsRenderedInOrder(): void
    {
        $view = $this->previewView(fields: [
            $this->field(key: 'a', label: 'A'),
            $this->field(key: 'b', label: 'B'),
        ]);

        $html = $this->renderer()->render('region/preview/region', $view);

        $this->assertLessThan(strpos($html, 'ra-field-b'), strpos($html, 'ra-field-a'));
    }

    // --- field.php ---

    public function testANullFieldRendersAsMissing(): void
    {
        $cell = $this->cell(value: null, text: '');
        $view = $this->field(cell: $cell);

        $html = $this->renderer()->render('region/preview/field', $view);

        $this->assertStringContainsString('ra-field-missing', $html);
        $this->assertStringContainsString('&mdash;', $html);
    }

    public function testAnHonestFalseBooleanIsNotShownAsMissing(): void
    {
        // CellFormatter formats Display::Check + false as empty text by its
        // own design (CellFormatter::formatBool()) -- the same text an
        // absent value produces. Only $value tells them apart: false is
        // never null.
        $cell = $this->cell(value: false, text: '', display: Display::Check, type: ColumnType::Bool);
        $view = $this->field(cell: $cell);

        $html = $this->renderer()->render('region/preview/field', $view);

        $this->assertStringNotContainsString('ra-field-missing', $html, 'an honest false is a value, not an absence');
        $this->assertStringContainsString('ra-grid-cell-bool', $html);
    }

    public function testAnHonestEmptyStringIsNotShownAsMissing(): void
    {
        $cell = $this->cell(value: '', text: '');
        $view = $this->field(cell: $cell);

        $html = $this->renderer()->render('region/preview/field', $view);

        $this->assertStringNotContainsString('ra-field-missing', $html, 'an empty string is a value, not an absence');
        $this->assertStringContainsString('ra-grid-cell-text', $html);
    }

    public function testAJsonFieldRendersThroughTheJsonCellPartial(): void
    {
        $cell = $this->cell(value: ['a' => 1], text: '{"a":1}', type: ColumnType::Json);
        $view = $this->field(cell: $cell, wide: true);

        $html = $this->renderer()->render('region/preview/field', $view);

        $this->assertStringContainsString('ra-grid-cell-json', $html);
        $this->assertStringContainsString('{&quot;a&quot;:1}', $html);
    }

    public function testALongTextFieldIsWideOnTheFieldsOwnClass(): void
    {
        $cell = $this->cell(value: str_repeat('x', 200), text: str_repeat('x', 200));
        $view = $this->field(cell: $cell, wide: true);

        $html = $this->renderer()->render('region/preview/field', $view);

        $this->assertStringContainsString('ra-field-wide', $html);
    }

    public function testACollectionFieldRendersAsJsonTheSameWayTheGridsCollectionColumnDoes(): void
    {
        // A collection column is always ColumnType::Json (PreviewRegionTest
        // and ListRegionTest both build one this way) -- this only proves
        // field.php draws it through the same partial as any other JSON
        // cell, not that PreviewRegion fetches it, which is
        // PreviewTest::testAPreviewWithACollectionFieldFetchesItOnceAlongsideTheRow's
        // job.
        $cell = $this->cell(key: 'tags', value: ['bazar', 'sleva'], text: '["bazar","sleva"]', type: ColumnType::Json);
        $view = $this->field(key: 'tags', label: 'Tags', cell: $cell, wide: true);

        $html = $this->renderer()->render('region/preview/field', $view);

        $this->assertStringContainsString('ra-grid-cell-json', $html);
        $this->assertStringContainsString('bazar', $html);
    }

    public function testAFieldEscapesHostileContent(): void
    {
        // Rule 6: everything is escaped with $e() and $raw() is the explicit
        // exception. field.php wraps the label and delegates the value to a
        // cell partial ListTemplatesTest already proves escapes -- this
        // proves the label side, and that the wrapping does not somehow
        // unescape what the partial already handled.
        $hostile = '<script>alert("x")</script> & "quoted"';
        $cell = $this->cell(value: $hostile, text: $hostile);
        $view = $this->field(label: $hostile, cell: $cell);

        $html = $this->renderer()->render('region/preview/field', $view);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('alert("x")', $html);
    }

    public function testTheRegionEscapesHostileContentInTheRowsTitle(): void
    {
        $hostile = '<script>alert("x")</script> & "quoted"';
        $view = $this->previewView(title: $hostile, fields: [$this->field()]);

        $html = $this->renderer()->render('region/preview/region', $view);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('alert("x")', $html);
    }
}
