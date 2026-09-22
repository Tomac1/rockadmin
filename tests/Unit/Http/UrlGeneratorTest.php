<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\UrlGenerator;

#[CoversClass(UrlGenerator::class)]
final class UrlGeneratorTest extends TestCase
{
    public function testPathMode(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->assertSame('/admin/', $urls->to(''));
        $this->assertSame('/admin/p/ads', $urls->to('p/ads'));
        $this->assertSame('/admin/p/ads?page=3', $urls->to('p/ads', ['page' => 3]));
    }

    public function testQueryMode(): void
    {
        $urls = new UrlGenerator('/admin/index.php', 'query');

        $this->assertSame('/admin/index.php', $urls->to(''));
        $this->assertSame('/admin/index.php?ra=p%2Fads', $urls->to('p/ads'));
        $this->assertSame('/admin/index.php?ra=p%2Fads&page=3', $urls->to('p/ads', ['page' => 3]));
    }

    public function testRouteSubstitutesParameters(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->assertSame(
            '/admin/p/ads/42/edit',
            $urls->route('page.edit', ['page' => 'ads', 'id' => 42]),
        );
        $this->assertSame(
            '/admin/r/users/detail?id=7',
            $urls->route('region', ['page' => 'users', 'region' => 'detail'], ['id' => 7]),
        );
    }

    public function testRouteEncodesParameters(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->assertSame(
            '/admin/p/ads/a%2Fb%20c',
            $urls->route('page.detail', ['page' => 'ads', 'id' => 'a/b c']),
        );
    }

    public function testWildcardParameterKeepsItsSlashes(): void
    {
        $urls = new UrlGenerator('/admin');

        $this->assertSame('/admin/_assets/css/core.css', $urls->route('assets', ['path' => 'css/core.css']));
    }

    public function testRouteWithAMissingParameterThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('id');

        (new UrlGenerator('/admin'))->route('page.edit', ['page' => 'ads']);
    }

    public function testUnknownModeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UrlGenerator('/admin', 'magic');
    }
}
