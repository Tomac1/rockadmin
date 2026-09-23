<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Config;
use RockAdmin\Config\Enums;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\View\Assets;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;
use RockAdmin\View\ViewFactory;

/**
 * template_paths, assets.css, assets.js and theme.dark are declared in
 * RootSchema and, before ViewFactory existed, read by nothing: a project had
 * no way to switch on this milestone's headline feature. These tests are
 * about that reachability, not about the classes ViewFactory wires — each of
 * those already has its own suite.
 */
#[CoversClass(ViewFactory::class)]
final class ViewFactoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ra-view-factory-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/project/ui', 0o777, true);
        file_put_contents($this->root . '/project/ui/button.php', 'project button');
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/project/ui/button.php');
        @rmdir($this->root . '/project/ui');
        @rmdir($this->root . '/project');
        @rmdir($this->root);
    }

    /** @param array<string, mixed> $data */
    private function config(array $data): Config
    {
        return new Config($data, Enums::fromConfig([]));
    }

    public function testTemplatePathsFromConfigurationReachTheResolver(): void
    {
        $factory = new ViewFactory(
            $this->config(['template_paths' => [$this->root . '/project']]),
            new UrlGenerator('/admin'),
        );

        $this->assertSame(
            'project button',
            file_get_contents($factory->templates()->resolve('ui/button')),
        );
    }

    public function testNoTemplatePathsLeavesOnlyTheSdkDefaults(): void
    {
        $factory = new ViewFactory($this->config([]), new UrlGenerator('/admin'));

        // layout/base ships with the SDK; nothing in an empty configuration
        // should stop it resolving.
        $this->assertStringContainsString('templates', $factory->templates()->resolve('layout/base'));
    }

    public function testAssetsCssAndJsFromConfigurationReachAssets(): void
    {
        $factory = new ViewFactory(
            $this->config(['assets' => ['css' => ['/css/admin.css'], 'js' => ['/js/admin.js']]]),
            new UrlGenerator('/admin'),
        );

        $this->assertContains('/css/admin.css', $factory->assets()->styles());
        $this->assertContains('/js/admin.js', $factory->assets()->scripts());
    }

    public function testBrandFallsBackToTheSchemaDefault(): void
    {
        $factory = new ViewFactory($this->config([]), new UrlGenerator('/admin'));

        $this->assertSame('RockAdmin', $factory->brand());
    }

    public function testBrandComesFromConfiguration(): void
    {
        $factory = new ViewFactory($this->config(['brand' => 'Cyklobazar admin']), new UrlGenerator('/admin'));

        $this->assertSame('Cyklobazar admin', $factory->brand());
    }

    public function testDarkModeFallsBackToAuto(): void
    {
        $factory = new ViewFactory($this->config([]), new UrlGenerator('/admin'));

        $this->assertSame('auto', $factory->darkMode());
    }

    public function testDarkModeComesFromConfiguration(): void
    {
        $factory = new ViewFactory($this->config(['theme' => ['dark' => 'on']]), new UrlGenerator('/admin'));

        $this->assertSame('on', $factory->darkMode());
    }

    public function testTheRendererIsBuiltFromTheSameTemplateResolver(): void
    {
        $factory = new ViewFactory(
            $this->config(['template_paths' => [$this->root . '/project']]),
            new UrlGenerator('/admin'),
        );

        $this->assertSame('project button', $factory->renderer()->render('ui/button'));
    }

    public function testTheTypesReturnedAreUsable(): void
    {
        $factory = new ViewFactory($this->config([]), new UrlGenerator('/admin'));

        $this->assertInstanceOf(TemplateResolver::class, $factory->templates());
        $this->assertInstanceOf(Renderer::class, $factory->renderer());
        $this->assertInstanceOf(Assets::class, $factory->assets());
    }
}
