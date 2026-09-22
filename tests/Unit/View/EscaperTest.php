<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\View\Escaper;
use RockAdmin\View\ViewException;

#[CoversClass(Escaper::class)]
final class EscaperTest extends TestCase
{
    private Escaper $escaper;

    protected function setUp(): void
    {
        $this->escaper = new Escaper();
    }

    public function testTextEscapesEveryCharacterThatEndsAnElementOrAnAttribute(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;',
            $this->escaper->text("<script>alert('x')</script>"),
        );
        $this->assertSame('a &amp;amp; b', $this->escaper->text('a &amp; b'));
        $this->assertSame('&quot;quoted&quot;', $this->escaper->text('"quoted"'));
    }

    public function testTextLeavesAccentedCharactersAlone(): void
    {
        // The admin is used in Czech. Escaping must not mangle what it does
        // not need to touch, or every label arrives as entities.
        $this->assertSame('uživatelé', $this->escaper->text('uživatelé'));
    }

    public function testTextReplacesInvalidUtf8RatherThanReturningNothing(): void
    {
        // htmlspecialchars() without ENT_SUBSTITUTE returns '' on malformed
        // input, which silently deletes a label instead of showing it broken.
        // The lone 0xC3 is replaced; the '(' after it is valid and survives.
        $this->assertSame("\u{FFFD}(", $this->escaper->text("\xC3\x28"));
    }

    /** @return array<string, array{mixed, string}> */
    public static function scalars(): array
    {
        return [
            'int' => [42, '42'],
            'float' => [1.5, '1.5'],
            'true' => [true, '1'],
            'false' => [false, ''],
            'null' => [null, ''],
            'stringable' => [new class () implements \Stringable {
                public function __toString(): string
                {
                    return '<b>';
                }
            }, '&lt;b&gt;'],
        ];
    }

    #[DataProvider('scalars')]
    public function testTextAcceptsAnythingATemplateMightHold(mixed $value, string $expected): void
    {
        $this->assertSame($expected, $this->escaper->text($value));
    }

    public function testTextRefusesAnArray(): void
    {
        // An array reaching $e() means the view object is wrong. Printing
        // "Array" would hide that until someone reads the page carefully.
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('array');

        $this->escaper->text(['a']);
    }

    public function testUrlPassesOrdinaryLinksThrough(): void
    {
        $this->assertSame('/admin/p/ads?page=2', $this->escaper->url('/admin/p/ads?page=2'));
        $this->assertSame('https://example.com/x', $this->escaper->url('https://example.com/x'));
        $this->assertSame('mailto:a@example.com', $this->escaper->url('mailto:a@example.com'));
    }

    public function testUrlEscapesWhatWouldEndTheAttribute(): void
    {
        $this->assertSame('/a?q=&quot;x&quot;&amp;y=1', $this->escaper->url('/a?q="x"&y=1'));
    }

    /** @return array<string, array{string}> */
    public static function dangerousSchemes(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript cased' => ['JavaScript:alert(1)'],
            'javascript padded' => ['  javascript:alert(1)'],
            'javascript with a tab inside the scheme' => ["java\tscript:alert(1)"],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'vbscript' => ['vbscript:msgbox(1)'],
        ];
    }

    #[DataProvider('dangerousSchemes')]
    public function testUrlRefusesASchemeThatExecutes(string $url): void
    {
        $this->expectException(ViewException::class);

        $this->escaper->url($url);
    }

    public function testAttributesBuildsALeadingSpacedList(): void
    {
        $this->assertSame(
            ' id="row-42" data-ra-id="42"',
            $this->escaper->attributes(['id' => 'row-42', 'data-ra-id' => 42]),
        );
    }

    public function testAttributesEscapesValues(): void
    {
        $this->assertSame(
            ' title="Ad &quot;42&quot; &amp; co"',
            $this->escaper->attributes(['title' => 'Ad "42" & co']),
        );
    }

    public function testAttributesDropsNullAndFalseAndKeepsEmptyStrings(): void
    {
        // null and false mean "this attribute is not present"; an empty string
        // means "present and empty", which is how value="" reaches a form.
        $this->assertSame(
            ' value=""',
            $this->escaper->attributes(['disabled' => false, 'title' => null, 'value' => '']),
        );
    }

    public function testAttributesWritesTrueAsABareAttribute(): void
    {
        $this->assertSame(' disabled', $this->escaper->attributes(['disabled' => true]));
    }

    public function testAttributesReturnsNothingForAnEmptyList(): void
    {
        $this->assertSame('', $this->escaper->attributes([]));
    }

    /** @return array<string, array{string}> */
    public static function malformedAttributeNames(): array
    {
        return [
            'a space smuggles a second attribute' => ['id onclick=alert(1)'],
            'a quote ends the name' => ['id"'],
            'an angle bracket ends the tag' => ['id>'],
            'an equals sign' => ['id=x'],
            'empty' => [''],
        ];
    }

    #[DataProvider('malformedAttributeNames')]
    public function testAttributesRefusesANameThatCouldSmuggleAnotherAttribute(string $name): void
    {
        // Attribute names come from configuration, which a person writes.
        // Escaping the value is not enough if the name can carry a payload.
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('attribute name');

        $this->escaper->attributes([$name => 'x']);
    }

    public function testRawReturnsItsInputUntouched(): void
    {
        // raw() exists to be visible in a diff and greppable in CI, not to
        // transform anything.
        $this->assertSame('<b>bold</b>', $this->escaper->raw('<b>bold</b>'));
    }
}
