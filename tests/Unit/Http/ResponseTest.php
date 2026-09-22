<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\Response;

#[CoversClass(Response::class)]
final class ResponseTest extends TestCase
{
    public function testHtmlSetsContentTypeAndStatus(): void
    {
        $response = Response::html('<p>Hello</p>');

        $this->assertSame(200, $response->status);
        $this->assertSame('<p>Hello</p>', $response->body);
        $this->assertSame('text/html; charset=utf-8', $response->headers['content-type']);
    }

    public function testRedirectCarriesLocationAndDefaultStatus(): void
    {
        $response = Response::redirect('/admin/p/ads');

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/p/ads', $response->headers['location']);
        $this->assertSame('', $response->body);
    }

    public function testNotFoundUsesStatus404(): void
    {
        $this->assertSame(404, Response::notFound()->status);
    }

    public function testWithHeaderReturnsANewInstanceAndLowercasesTheName(): void
    {
        $original = Response::html('x');
        $modified = $original->withHeader('X-Robots-Tag', 'noindex');

        $this->assertNotSame($original, $modified);
        $this->assertArrayNotHasKey('x-robots-tag', $original->headers);
        $this->assertSame('noindex', $modified->headers['x-robots-tag']);
        $this->assertSame('text/html; charset=utf-8', $modified->headers['content-type']);
    }
}
