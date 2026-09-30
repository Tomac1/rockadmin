<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\ReturnAddress;
use RockAdmin\Http\UrlGenerator;

/**
 * `_ret` is the open-redirect surface of this milestone, so this file is
 * adversarial on purpose: every case names the value and what must come back,
 * and a refused value must come back as `null` rather than as an empty
 * string -- `FormRegion` falls back to the page's index only on `null`, so an
 * empty string would be carried into the document as a real return address
 * and a form would post itself to nowhere.
 *
 * The `use PHPUnit\Framework\Attributes\DataProvider;` import above is
 * load-bearing. Without it PHPUnit ignores the attribute, runs the method
 * once with no arguments, and the file reports green having asserted
 * nothing -- which has happened twice in this repository.
 */
#[CoversClass(ReturnAddress::class)]
final class ReturnAddressTest extends TestCase
{
    /** @return iterable<string, array{mixed}> */
    public static function refusedValues(): iterable
    {
        // Relative: nothing says what it is relative to, so a browser
        // resolves it against whatever page happens to be showing.
        yield 'a relative path' => ['p/ads'];

        // Scheme-relative: "//evil.com" is a host, not a path.
        yield 'scheme-relative' => ['//evil.com'];

        yield 'an absolute url' => ['https://evil.com'];
        yield 'a protocol-less scheme' => ['javascript:alert(1)'];
        yield 'a data url' => ['data:text/html,<script>alert(1)</script>'];

        // A backslash is not a path separator to Request::normalizePath(),
        // but browsers treat "/\evil.com" exactly like "//evil.com".
        yield 'a backslash after the slash' => ['/\\evil.com'];
        yield 'a backslash anywhere' => ['/admin/p\\ads'];

        // A Location header is written verbatim. Anything that could end a
        // header line, or that a proxy might decode into something that
        // could, is refused rather than reasoned about.
        yield 'a percent-encoded crlf' => ['/admin/p/ads%0d%0aSet-Cookie:x'];
        yield 'a percent-encoded crlf in upper case' => ['/admin/p/ads%0D%0ASet-Cookie:x'];
        yield 'a raw newline' => ["/admin/p/ads\nSet-Cookie:x"];
        yield 'a raw carriage return' => ["/admin/p/ads\rSet-Cookie:x"];
        yield 'a tab' => ["/admin/p/\tads"];
        yield 'a vertical tab' => ["/admin/p/\x0bads"];
        yield 'a null byte' => ["/admin/p/ads\0"];

        // Dot segments resolve somewhere else in the browser before the
        // request is ever made, so "under the admin's path" would be a lie.
        yield 'a dot-dot segment' => ['/admin/../etc/passwd'];
        yield 'a trailing dot-dot segment' => ['/admin/p/..'];

        yield 'an array' => [['/admin/p/ads']];
        yield 'an integer' => [42];
        yield 'a float' => [1.5];
        yield 'a bool' => [true];
        yield 'null' => [null];
        yield 'an object' => [new \stdClass()];
        yield 'an empty string' => [''];
        yield 'ten thousand characters' => ['/admin/' . str_repeat('a', 10_000)];
    }

    #[DataProvider('refusedValues')]
    public function testARefusedValueIsNullAndNeverAnEmptyString(mixed $raw): void
    {
        $this->assertNull(ReturnAddress::from($raw));
    }

    /** @return iterable<string, array{string}> */
    public static function acceptedValues(): iterable
    {
        yield 'an admin page' => ['/admin/p/ads'];
        yield 'an admin page with grid state' => ['/admin/p/ads?grid%5Bsort%5D=-price&grid%5Bpage%5D=3'];
        yield 'the admin root' => ['/admin'];
        yield 'query mode' => ['/admin/index.php?ra=p%2Fads'];
        yield 'mounted at the root' => ['/p/ads'];
        yield 'a colon after the first segment' => ['/admin/p/ads?q=a:b'];
        yield 'two thousand characters' => ['/admin/' . str_repeat('a', 2_000)];
    }

    #[DataProvider('acceptedValues')]
    public function testAnInternalPathIsCarriedVerbatim(string $raw): void
    {
        $address = ReturnAddress::from($raw);

        $this->assertInstanceOf(ReturnAddress::class, $address);
        $this->assertSame($raw, $address->path());
    }

    public function testAPathUnderTheAdminsOwnBaseIsTheUrlToRedirectTo(): void
    {
        $address = ReturnAddress::from('/admin/p/ads?grid%5Bpage%5D=3');

        $this->assertInstanceOf(ReturnAddress::class, $address);
        $this->assertSame('/admin/p/ads?grid%5Bpage%5D=3', $address->url(new UrlGenerator('/admin')));
    }

    public function testTheAdminsBaseItselfIsUnderIt(): void
    {
        $address = ReturnAddress::from('/admin');

        $this->assertInstanceOf(ReturnAddress::class, $address);
        $this->assertSame('/admin', $address->url(new UrlGenerator('/admin')));
    }

    public function testAnAbsolutePathOutsideTheAdminFallsBackToTheAdminsRoot(): void
    {
        // Syntactically an internal path, so from() accepts it; only the
        // generator knows where this admin is mounted, so this is the first
        // point at which "outside the admin" is even a question.
        $address = ReturnAddress::from('/etc/passwd');

        $this->assertInstanceOf(ReturnAddress::class, $address);
        $this->assertSame('/admin/', $address->url(new UrlGenerator('/admin')));
    }

    public function testAPrefixThatMerelyStartsWithTheBasesLettersIsNotUnderIt(): void
    {
        $address = ReturnAddress::from('/administrator/evil');

        $this->assertInstanceOf(ReturnAddress::class, $address);
        $this->assertSame('/admin/', $address->url(new UrlGenerator('/admin')));
    }

    public function testQueryModeKeepsItsOwnAddress(): void
    {
        $address = ReturnAddress::from('/admin/index.php?ra=p%2Fads');

        $this->assertInstanceOf(ReturnAddress::class, $address);
        $this->assertSame(
            '/admin/index.php?ra=p%2Fads',
            $address->url(new UrlGenerator('/admin/index.php', UrlGenerator::MODE_QUERY)),
        );
    }

    public function testMountedAtTheRootEveryAbsolutePathIsInternal(): void
    {
        $address = ReturnAddress::from('/p/ads');

        $this->assertInstanceOf(ReturnAddress::class, $address);
        $this->assertSame('/p/ads', $address->url(new UrlGenerator('/')));
    }
}
