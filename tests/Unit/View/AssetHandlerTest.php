<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\NotFoundException;
use RockAdmin\Http\Request;
use RockAdmin\Http\Route;
use RockAdmin\View\AssetHandler;

#[CoversClass(AssetHandler::class)]
final class AssetHandlerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ra-serve-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/css', 0o777, true);
        file_put_contents($this->root . '/css/rockadmin.css', ':root{--ra-x:1}');
        file_put_contents($this->root . '/secret.txt', 'not an asset');
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/css/rockadmin.css');
        @unlink($this->root . '/secret.txt');
        @rmdir($this->root . '/css');
        @rmdir($this->root);
    }

    private function serve(string $path): \RockAdmin\Http\Response
    {
        return (new AssetHandler($this->root))->handle(
            new Route('assets', ['path' => $path]),
            new Request('GET', '_assets/' . $path),
        );
    }

    public function testAKnownFileIsServedWithItsContentType(): void
    {
        $response = $this->serve('css/rockadmin.css');

        $this->assertSame(200, $response->status);
        $this->assertSame(':root{--ra-x:1}', $response->body);
        $this->assertSame('text/css; charset=utf-8', $response->headers['content-type']);
    }

    public function testAnAssetIsCachedForeverBecauseItsUrlCarriesItsHash(): void
    {
        $response = $this->serve('css/rockadmin.css');

        $this->assertSame('public, max-age=31536000, immutable', $response->headers['cache-control']);
    }

    public function testASniffedContentTypeIsRefused(): void
    {
        $this->assertSame('nosniff', $this->serve('css/rockadmin.css')->headers['x-content-type-options']);
    }

    /** @return array<string, array{string}> */
    public static function traversals(): array
    {
        return [
            'parent' => ['../secret.txt'],
            'parent inside' => ['css/../../secret.txt'],
            'absolute' => ['/etc/passwd'],
            'null byte' => ["css/rockadmin.css\0.png"],
            'backslash' => ['css\\..\\secret.txt'],
        ];
    }

    #[DataProvider('traversals')]
    public function testAPathLeavingTheAssetDirectoryIsNotFound(string $path): void
    {
        // The route's wildcard hands this handler whatever the URL held, so
        // this is the only thing between a visitor and the file system.
        $this->expectException(NotFoundException::class);

        $this->serve($path);
    }

    public function testAFileOutsideTheKnownTypesIsNotServed(): void
    {
        // An allowed-extension list, not a denied one: a new file type is a
        // decision, not an accident.
        $this->expectException(NotFoundException::class);

        $this->serve('secret.txt');
    }

    public function testAMissingFileIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        $this->serve('css/nothing.css');
    }
}
