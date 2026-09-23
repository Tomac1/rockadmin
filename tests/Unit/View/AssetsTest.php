<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\View\Assets;
use RockAdmin\View\ViewException;

#[CoversClass(Assets::class)]
final class AssetsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ra-assets-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/css', 0o777, true);
        mkdir($this->root . '/js', 0o777, true);
        mkdir($this->root . '/vendor/bootstrap', 0o777, true);
        file_put_contents($this->root . '/css/rockadmin.css', ':root{}');
        file_put_contents($this->root . '/js/core.js', '// core');
        file_put_contents($this->root . '/vendor/bootstrap/bootstrap.min.css', '.btn{}');
        file_put_contents($this->root . '/vendor/bootstrap/bootstrap.bundle.min.js', '// bs');
    }

    protected function tearDown(): void
    {
        foreach (['css/rockadmin.css', 'js/core.js', 'vendor/bootstrap/bootstrap.min.css', 'vendor/bootstrap/bootstrap.bundle.min.js'] as $file) {
            @unlink($this->root . '/' . $file);
        }

        foreach (['vendor/bootstrap', 'vendor', 'css', 'js', ''] as $directory) {
            @rmdir(rtrim($this->root . '/' . $directory, '/'));
        }
    }

    /**
     * @param list<string> $css
     * @param list<string> $js
     */
    private function assets(array $css = [], array $js = []): Assets
    {
        return new Assets(new UrlGenerator('/admin'), $css, $js, $this->root);
    }

    public function testBootstrapComesBeforeTheThemeSoTheThemeOverridesIt(): void
    {
        $styles = $this->assets()->styles();

        $this->assertCount(2, $styles);
        $this->assertStringContainsString('bootstrap.min.css', $styles[0]);
        $this->assertStringContainsString('rockadmin.css', $styles[1]);
    }

    public function testProjectStylesComeLastSoTheyOverrideWithoutImportant(): void
    {
        $styles = $this->assets(['/css/admin-theme.css'])->styles();

        $this->assertCount(3, $styles);
        $this->assertSame('/css/admin-theme.css', $styles[2]);
    }

    public function testBootstrapsBundleComesBeforeCoreJs(): void
    {
        $scripts = $this->assets()->scripts();

        $this->assertCount(2, $scripts);
        $this->assertStringContainsString('bootstrap.bundle.min.js', $scripts[0]);
        $this->assertStringContainsString('core.js', $scripts[1]);
    }

    public function testProjectScriptsComeLast(): void
    {
        $scripts = $this->assets([], ['/js/admin-extra.js'])->scripts();

        $this->assertSame('/js/admin-extra.js', $scripts[2]);
    }

    public function testAnAssetUrlGoesThroughTheAssetsRoute(): void
    {
        $url = $this->assets()->url('css/rockadmin.css');

        $this->assertStringStartsWith('/admin/_assets/css/rockadmin.css?v=', $url);
    }

    public function testTheVersionIsTheContentHashSoATouchedFileKeepsItsUrl(): void
    {
        // A deploy that copies files changes every modification time. Hashing
        // the content means only what actually changed is re-fetched.
        $before = $this->assets()->url('css/rockadmin.css');
        touch($this->root . '/css/rockadmin.css', time() + 60);
        $after = $this->assets()->url('css/rockadmin.css');

        $this->assertSame($before, $after);

        file_put_contents($this->root . '/css/rockadmin.css', ':root{--x:1}');

        $this->assertNotSame($before, $this->assets()->url('css/rockadmin.css'));
    }

    public function testOneInstanceHashesAFileOnceHoweverOftenItIsAsked(): void
    {
        // The memoisation the brief asks for, pinned. Changing the file
        // underneath a live instance and getting the first URL back is what
        // proves the second call did not read the disk again — a page links
        // the same stylesheet once, but a fragment request rebuilds the list.
        $assets = $this->assets();
        $first = $assets->url('css/rockadmin.css');

        file_put_contents($this->root . '/css/rockadmin.css', ':root{--changed:1}');

        $this->assertSame($first, $assets->url('css/rockadmin.css'));
        $this->assertNotSame($first, $this->assets()->url('css/rockadmin.css'));
    }

    public function testAnUnknownAssetIsRefusedRatherThanLinkedTo(): void
    {
        // A dead stylesheet link is a page that renders unstyled and looks
        // like a CSS bug. Failing here names the file instead.
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('css/nothing.css');

        $this->assets()->url('css/nothing.css');
    }

    public function testAnExtensionAssetHandlerCannotServeIsRefusedHere(): void
    {
        // Verified by execution before this fix: fonts/x.ttf, img/LOGO.PNG
        // and img/a.avif each minted a URL that then 404s, because url()
        // never checked the extension against what AssetHandler will serve.
        // Refusing here is what turns that 404 into a message naming the
        // file that a person wrote in configuration or a template.
        mkdir($this->root . '/fonts', 0o777, true);
        file_put_contents($this->root . '/fonts/x.notaformat', 'x');

        try {
            $this->expectException(ViewException::class);
            $this->expectExceptionMessage('fonts/x.notaformat');

            $this->assets()->url('fonts/x.notaformat');
        } finally {
            @unlink($this->root . '/fonts/x.notaformat');
            @rmdir($this->root . '/fonts');
        }
    }

    public function testAnUppercaseExtensionIsRecognisedTheSameAsLowercase(): void
    {
        // img/LOGO.PNG must resolve exactly as img/logo.png does: the
        // extension lookup is case-insensitive on both sides of the asset
        // route, or a URL minted here 404s the moment AssetHandler does its
        // own case-sensitive comparison.
        mkdir($this->root . '/img', 0o777, true);
        file_put_contents($this->root . '/img/LOGO.PNG', 'not really a png, only the extension matters here');

        try {
            $url = $this->assets()->url('img/LOGO.PNG');

            $this->assertStringContainsString('img/LOGO.PNG', $url);
        } finally {
            @unlink($this->root . '/img/LOGO.PNG');
            @rmdir($this->root . '/img');
        }
    }

    public function testFontAndModernImageExtensionsAreServable(): void
    {
        // ttf, otf, avif and mjs were missing from the allowed list entirely,
        // so a project asset in any of these formats 404d however it was
        // spelled.
        mkdir($this->root . '/fonts', 0o777, true);
        foreach (['a.ttf', 'a.otf', 'a.avif', 'a.mjs'] as $file) {
            file_put_contents($this->root . '/fonts/' . $file, 'x');
        }

        try {
            foreach (['a.ttf', 'a.otf', 'a.avif', 'a.mjs'] as $file) {
                $url = $this->assets()->url('fonts/' . $file);

                $this->assertStringContainsString('fonts/' . $file, $url);
            }
        } finally {
            foreach (['a.ttf', 'a.otf', 'a.avif', 'a.mjs'] as $file) {
                @unlink($this->root . '/fonts/' . $file);
            }
            @rmdir($this->root . '/fonts');
        }
    }
}
