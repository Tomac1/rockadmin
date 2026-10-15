<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Grid\CellFormatter;
use RockAdmin\Grid\CellPartial;
use RockAdmin\Grid\CellView;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\ColumnDefinition;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;
use RockAdmin\Storage\FileLink;
use RockAdmin\Storage\LocalStorage;
use RockAdmin\Storage\Storage;
use RockAdmin\Storage\StorageException;
use RockAdmin\View\Escaper;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;
use RockAdmin\View\ViewException;

/**
 * An `image` column, from the value in the row to the markup in the cell.
 *
 * The one promise worth a test of its own: **what a browser fetches is built
 * by the storage, never taken from the column**. A column holds a key, the
 * key is data, and the whole point of resolving it at render time is that the
 * value itself never reaches an attribute. Everything else here is the four
 * ways that resolution declines, each of which a real installation reaches.
 */
#[CoversClass(CellFormatter::class)]
final class ImageCellTest extends TestCase
{
    private const string KEY = 'ads/2026/10/0123456789abcdef0123456789abcdef.png';

    private function column(
        ColumnType $type = ColumnType::Image,
        ?Display $display = null,
    ): ColumnDefinition {
        return new ColumnDefinition(
            filter: null,
            collection: null,
            key: 'photo',
            label: 'Photo',
            source: 'photo',
            type: $type,
            display: $display ?? $type->defaultDisplay(),
            sortable: false,
            link: false,
            align: $type->defaultAlignment(),
            width: null,
            class: '',
            options: [],
        );
    }

    private function storage(): Storage
    {
        // A real driver rather than a stand-in: `url()` is the method under
        // test here, and the one in `LocalStorage` is the one installations
        // without a bucket actually run.
        return new LocalStorage(
            sys_get_temp_dir() . '/ra-image-cell',
            new UrlGenerator('/admin'),
            '',
            new FileLink(new ArraySessionStore()),
        );
    }

    // -- the picture --------------------------------------------------

    public function testTheCellCarriesAnAddressTheStorageBuiltAndNotTheColumnValue(): void
    {
        $formatter = new CellFormatter($this->storage());

        $cell = $formatter->format($this->column(), self::KEY, null, 'ads');

        $source = $cell->attributes['src'] ?? null;

        self::assertIsString($source);
        self::assertNotSame(self::KEY, $source, 'The raw key must not be used as an address.');
        // The signature is `FileRouteTest`'s subject; here what matters is
        // that the address is the route's and the key is not the address.
        self::assertStringStartsWith('/admin/_file/' . self::KEY . '?', $source);
    }

    public function testTheAlternativeTextIsTheFilesOwnName(): void
    {
        $formatter = new CellFormatter($this->storage());

        $cell = $formatter->format($this->column(), self::KEY, null, 'ads');

        self::assertSame('0123456789abcdef0123456789abcdef.png', $cell->attributes['alt'] ?? null);
        self::assertSame('0123456789abcdef0123456789abcdef.png', $cell->text);
    }

    public function testAnImageColumnIsDrawnByTheImagePartial(): void
    {
        $cell = (new CellFormatter($this->storage()))->format($this->column(), self::KEY, null, 'ads');

        self::assertSame('region/list/cell/image', CellPartial::templateFor($cell));
    }

    // -- the four declines --------------------------------------------

    public function testWithNoStorageWiredTheCellShowsTheFilesName(): void
    {
        $cell = (new CellFormatter())->format($this->column(), self::KEY, null, 'ads');

        self::assertSame('0123456789abcdef0123456789abcdef.png', $cell->text);
        self::assertArrayNotHasKey('src', $cell->attributes);
    }

    public function testWithNoPageInHandNothingIsResolved(): void
    {
        $cell = (new CellFormatter($this->storage()))->format($this->column(), self::KEY);

        self::assertArrayNotHasKey('src', $cell->attributes);
    }

    public function testAKeyBelongingToAnotherPageIsNeverGivenAnAddress(): void
    {
        $formatter = new CellFormatter($this->storage());

        $cell = $formatter->format($this->column(), self::KEY, null, 'invoices');

        self::assertArrayNotHasKey(
            'src',
            $cell->attributes,
            'A row carrying another page\'s key must not become a working link to it.',
        );
        self::assertSame('0123456789abcdef0123456789abcdef.png', $cell->text);
    }

    /** @param non-empty-string $value */
    #[DataProvider('refusedValueProvider')]
    public function testAValueThatIsNotAKeyIsNeverGivenAnAddress(string $value): void
    {
        $formatter = new CellFormatter($this->storage());

        $cell = $formatter->format($this->column(), $value, null, 'ads');

        self::assertArrayNotHasKey('src', $cell->attributes);
    }

    /** @return iterable<string, array{string}> */
    public static function refusedValueProvider(): iterable
    {
        yield 'a traversal' => ['ads/../../etc/passwd'];
        yield 'an absolute path' => ['/etc/passwd'];
        yield 'a URL' => ['https://example.com/photo.png'];
        yield 'a javascript scheme' => ['javascript:alert(1)'];
        yield 'a backslash' => ['ads\\2026\\photo.png'];
    }

    public function testAStorageWithNoWayToBuildAUrlLeavesTheNameAndDoesNotRaise(): void
    {
        // A local directory wired without a UrlGenerator and without a public
        // base: `url()` raises, by design. A grid of forty rows must not turn
        // that into a 500.
        $mute = new LocalStorage(sys_get_temp_dir() . '/ra-image-cell');

        self::assertTrue($mute->accepts(self::KEY));

        try {
            $mute->url(self::KEY);
            self::fail('LocalStorage without a URL way is expected to refuse.');
        } catch (StorageException) {
            // the state under test
        }

        $cell = (new CellFormatter($mute))->format($this->column(), self::KEY, null, 'ads');

        self::assertSame('0123456789abcdef0123456789abcdef.png', $cell->text);
        self::assertArrayNotHasKey('src', $cell->attributes);
    }

    public function testAnEmptyColumnIsStillAnEmptyCell(): void
    {
        $cell = (new CellFormatter($this->storage()))->format($this->column(), null, null, 'ads');

        self::assertSame('', $cell->text);
        self::assertStringContainsString('ra-grid-cell-empty', $cell->classes);
    }

    // -- the markup ---------------------------------------------------

    private function render(CellView $cell): string
    {
        $renderer = new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/admin'));

        return $renderer->render('region/list/cell/image', $cell);
    }

    public function testTheTemplateDrawsTheStoragesAddressWithItsIdentityClass(): void
    {
        $cell = (new CellFormatter($this->storage()))->format($this->column(), self::KEY, null, 'ads');

        $html = $this->render($cell);

        self::assertStringContainsString('ra-grid-cell-image', $html);
        self::assertStringContainsString('<img', $html);
        self::assertStringContainsString('src="/admin/_file/', $html);
        self::assertStringContainsString('alt="0123456789abcdef0123456789abcdef.png"', $html);
    }

    public function testTheTemplateDrawsTextWhenThereIsNoAddress(): void
    {
        $cell = (new CellFormatter())->format($this->column(), self::KEY, null, 'ads');

        $html = $this->render($cell);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('ra-grid-cell-image-missing', $html);
        self::assertStringContainsString('0123456789abcdef0123456789abcdef.png', $html);
    }

    public function testASourceWithADangerousSchemeNeverReachesTheMarkup(): void
    {
        // The formatter would never produce this -- `accepts()` refuses the
        // value long before. The template is asked anyway, because `$href()`
        // checking the scheme is the second of the two defences and a
        // regression in either one has to fail a test. It refuses loudly
        // rather than quietly dropping the attribute, which is the right way
        // round: a `javascript:` URL reaching a template means something
        // upstream is broken, and a half-drawn cell would hide it.
        $cell = new CellView(
            key: 'photo',
            value: 'javascript:alert(1)',
            text: 'photo.png',
            type: ColumnType::Image,
            display: Display::Image,
            classes: 'ra-grid-cell ra-grid-cell-photo',
            url: null,
            attributes: ['src' => 'javascript:alert(1)', 'alt' => 'photo.png'],
            percent: null,
            variant: null,
        );

        $this->expectException(ViewException::class);

        $this->render($cell);
    }

    // -- a page that asked for the name instead -----------------------

    public function testAnImageColumnDisplayedAsPlainKeepsItsOwnSlug(): void
    {
        $column = $this->column(display: Display::Plain);
        $cell = (new CellFormatter($this->storage()))->format($column, self::KEY, null, 'ads');

        self::assertSame('region/list/cell/plain', CellPartial::templateFor($cell));

        $html = (new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/admin')))
            ->render('region/list/cell/plain', $cell);

        self::assertStringContainsString('ra-grid-cell-image', $html);
        self::assertStringContainsString('0123456789abcdef0123456789abcdef.png', $html);
    }
}
