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

    public function testAnUppercaseExtensionIsServedTheSameAsLowercase(): void
    {
        mkdir($this->root . '/img', 0o777, true);
        file_put_contents($this->root . '/img/LOGO.PNG', 'not really a png, only the extension matters here');

        try {
            $response = $this->serve('img/LOGO.PNG');

            $this->assertSame(200, $response->status);
            $this->assertSame('image/png', $response->headers['content-type']);
        } finally {
            @unlink($this->root . '/img/LOGO.PNG');
            @rmdir($this->root . '/img');
        }
    }

    /** @return array<string, array{string, string}> */
    public static function newlyAllowedTypes(): array
    {
        return [
            'ttf' => ['fonts/x.ttf', 'font/ttf'],
            'otf' => ['fonts/x.otf', 'font/otf'],
            'avif' => ['img/a.avif', 'image/avif'],
            'mjs' => ['js/x.mjs', 'application/javascript; charset=utf-8'],
        ];
    }

    #[DataProvider('newlyAllowedTypes')]
    public function testAFormerlyRefusedExtensionIsNowServed(string $path, string $contentType): void
    {
        $file = $this->root . '/' . $path;
        mkdir(\dirname($file), 0o777, true);
        file_put_contents($file, 'x');

        try {
            $response = $this->serve($path);

            $this->assertSame(200, $response->status);
            $this->assertSame($contentType, $response->headers['content-type']);
        } finally {
            @unlink($file);
            @rmdir(\dirname($file));
        }
    }

    public function testTheExtensionComesFromTheFilenameNotTheWholePath(): void
    {
        // getExtension() used to split the whole path on '.', so a directory
        // segment carrying a dot ("v1.2/logo.png") would read "2/logo" —
        // well, would read the last segment after the last dot in the whole
        // string, i.e. "png", by coincidence; the real failure case is a dot
        // in a directory name with no further dot in the filename.
        mkdir($this->root . '/v1.2', 0o777, true);
        file_put_contents($this->root . '/v1.2/logo', 'not a real png, only the extension matters');

        try {
            $this->expectException(NotFoundException::class);

            $this->serve('v1.2/logo');
        } finally {
            @unlink($this->root . '/v1.2/logo');
            @rmdir($this->root . '/v1.2');
        }
    }
}
