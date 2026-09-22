<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\Request;

#[CoversClass(Request::class)]
final class RequestTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function pathCases(): array
    {
        return [
            'empty stays empty'        => ['', ''],
            'leading slash removed'    => ['/p/users', 'p/users'],
            'trailing slash removed'   => ['p/users/', 'p/users'],
            'both slashes removed'     => ['/p/users/', 'p/users'],
            'duplicate slashes'        => ['p//users', 'p/users'],
            'only slashes'             => ['///', ''],
            'dot segment dropped'      => ['p/./users', 'p/users'],
            'parent segment dropped'   => ['_assets/../../etc/passwd', '_assets/etc/passwd'],
            'unicode kept'             => ['p/uživatelé', 'p/uživatelé'],
        ];
    }

    #[DataProvider('pathCases')]
    public function testNormalizePath(string $input, string $expected): void
    {
        $this->assertSame($expected, Request::normalizePath($input));
    }

    public function testConstructorNormalisesThePath(): void
    {
        $request = new Request('GET', '/p/users/');

        $this->assertSame('p/users', $request->path);
    }

    public function testMethodIsUppercased(): void
    {
        $this->assertSame('POST', (new Request('post', ''))->method);
    }

    public function testIsPost(): void
    {
        $this->assertTrue((new Request('POST', ''))->isPost());
        $this->assertFalse((new Request('GET', ''))->isPost());
    }

    public function testQueryAndInputReturnDefaultsWhenMissing(): void
    {
        $request = new Request('POST', 'a/ads/create', ['page' => '3'], ['title' => 'Bike']);

        $this->assertSame('3', $request->query('page'));
        $this->assertNull($request->query('missing'));
        $this->assertSame('fallback', $request->query('missing', 'fallback'));
        $this->assertSame('Bike', $request->input('title'));
        $this->assertSame('fallback', $request->input('missing', 'fallback'));
    }

    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $request = new Request('GET', '', [], [], [], ['X-Csrf-Token' => 'abc']);

        $this->assertSame('abc', $request->header('x-csrf-token'));
        $this->assertSame('abc', $request->header('X-CSRF-TOKEN'));
        $this->assertNull($request->header('x-other'));
    }

    #[BackupGlobals(true)]
    public function testFromGlobalsReadsTheSuperglobals(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'post',
            'HTTP_X_CSRF_TOKEN' => 'abc',
            'HTTP_ACCEPT_LANGUAGE' => 'cs',
            'DOCUMENT_ROOT' => '/var/www',
        ];
        $_GET = ['page' => '3'];
        $_POST = ['title' => 'Bike'];
        $_COOKIE = ['session' => 'xyz', 'array_cookie' => ['not', 'a', 'string']];

        $request = Request::fromGlobals('/p/ads/');

        $this->assertSame('POST', $request->method);
        $this->assertSame('p/ads', $request->path);
        $this->assertSame('3', $request->query('page'));
        $this->assertSame('Bike', $request->input('title'));
        $this->assertSame('abc', $request->header('X-CSRF-TOKEN'));
        $this->assertSame('cs', $request->header('accept-language'));
        $this->assertNull($request->header('document-root'));
        $this->assertSame(['session' => 'xyz'], $request->cookies);
    }
}
