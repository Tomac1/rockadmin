<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\View\Escaper;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;
use RockAdmin\View\ViewException;

#[CoversClass(Renderer::class)]
final class RendererTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ra-render-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/sdk', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    private function template(string $name, string $contents): void
    {
        file_put_contents($this->root . '/sdk/' . $name . '.php', $contents);
    }

    private function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $this->remove($path . '/' . $entry);
        }

        rmdir($path);
    }

    private function renderer(): Renderer
    {
        return new Renderer(
            new TemplateResolver([], $this->root . '/sdk'),
            new Escaper(),
            new UrlGenerator('/admin'),
        );
    }

    public function testATemplateSeesItsViewObject(): void
    {
        $this->template('hello', '<p><?= $e($view->name) ?></p>');

        $view = new class () {
            public string $name = 'Tomáš';
        };

        $this->assertSame('<p>Tomáš</p>', $this->renderer()->render('hello', $view));
    }

    public function testOutputIsReturnedNotPrinted(): void
    {
        $this->template('hello', 'hi');

        ob_start();
        $out = $this->renderer()->render('hello');
        $leaked = (string) ob_get_clean();

        $this->assertSame('hi', $out);
        $this->assertSame('', $leaked);
    }

    public function testTheEscaperIsReachableAsE(): void
    {
        $this->template('x', '<?= $e("<b>") ?>');

        $this->assertSame('&lt;b&gt;', $this->renderer()->render('x'));
    }

    public function testRawWritesUnescaped(): void
    {
        $this->template('x', '<?= $raw("<b>bold</b>") ?>');

        $this->assertSame('<b>bold</b>', $this->renderer()->render('x'));
    }

    public function testAttrsBuildsAnAttributeList(): void
    {
        $this->template('x', '<tr<?= $attrs(["data-id" => 42, "hidden" => true]) ?>>');

        $this->assertSame('<tr data-id="42" hidden>', $this->renderer()->render('x'));
    }

    public function testUrlAndRouteGoThroughTheGenerator(): void
    {
        $this->template('x', '<?= $e($url("p/ads", ["page" => 2])) ?>|<?= $e($route("page.detail", ["page" => "ads", "id" => 7])) ?>');

        $this->assertSame('/admin/p/ads?page=2|/admin/p/ads/7', $this->renderer()->render('x'));
    }

    public function testPartialRendersAnotherTemplateWithItsOwnView(): void
    {
        $this->template('outer', 'a<?= $partial("inner", "B") ?>c');
        $this->template('inner', '<?= $e($view) ?>');

        $this->assertSame('aBc', $this->renderer()->render('outer'));
    }

    public function testAPartialDoesNotSeeTheOuterView(): void
    {
        // Leaking the caller's $view is how a partial silently keeps working
        // after someone forgets to pass it one.
        $this->template('outer', '<?= $partial("inner") ?>');
        $this->template('inner', '<?= $view === null ? "none" : "leaked" ?>');

        $this->assertSame('none', $this->renderer()->render('outer', 'outer view'));
    }

    public function testATemplateCannotSeeTheRendererOrTheResolver(): void
    {
        // Rule 4 of the project: no layer reaches two levels down. If $this or
        // a resolver is in scope, a template can build a query eventually.
        $this->template('x', '<?= isset($this) ? "this" : "no-this" ?>|<?= get_defined_vars() === [] ? "none" : implode(",", array_keys(get_defined_vars())) ?>');

        $out = $this->renderer()->render('x');

        $this->assertStringStartsWith('no-this|', $out);
        $this->assertSame(
            ['attr', 'attrs', 'e', 'href', 'partial', 'raw', 'route', 'url', 'view'],
            $this->definedVariables($out),
        );
    }

    /** @return list<string> */
    private function definedVariables(string $output): array
    {
        $names = explode(',', explode('|', $output)[1]);
        sort($names);

        return $names;
    }

    public function testAThrowingTemplateLeavesNoOutputBufferBehind(): void
    {
        $this->template('boom', 'partial output<?php throw new \RuntimeException("boom"); ?>');

        $depth = ob_get_level();

        try {
            $this->renderer()->render('boom');
            $this->fail('The exception should have propagated.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame($depth, ob_get_level(), 'The renderer left an output buffer open.');
    }

    public function testAThrowingPartialAlsoUnwindsCleanly(): void
    {
        $this->template('outer', 'a<?= $partial("boom") ?>');
        $this->template('boom', '<?php throw new \RuntimeException("inner"); ?>');

        $depth = ob_get_level();

        try {
            $this->renderer()->render('outer');
            $this->fail('The exception should have propagated.');
        } catch (\RuntimeException $e) {
            $this->assertSame('inner', $e->getMessage());
        }

        $this->assertSame($depth, ob_get_level());
    }

    public function testAMissingTemplateThrows(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('nothing');

        $this->renderer()->render('nothing');
    }

    public function testRenderingTheSameTemplateTwiceIsIndependent(): void
    {
        // A template that declared a function or leaked state would fail the
        // second time. A grid renders one row template per row.
        $this->template('row', '<?= $e($view) ?>');

        $renderer = $this->renderer();

        $this->assertSame('1', $renderer->render('row', 1));
        $this->assertSame('2', $renderer->render('row', 2));
    }

    public function testWithOverridesScopesTheResolver(): void
    {
        mkdir($this->root . '/sdk/ui', 0o777, true);
        $this->template('ui/button', 'default');
        mkdir($this->root . '/sdk/ads', 0o777, true);
        file_put_contents($this->root . '/sdk/ads/button.php', 'ads');

        $renderer = $this->renderer();
        $scoped = $renderer->withOverrides(['ui/button' => 'ads/button']);

        $this->assertSame('ads', $scoped->render('ui/button'));
        $this->assertSame('default', $renderer->render('ui/button'));

        unlink($this->root . '/sdk/ads/button.php');
        rmdir($this->root . '/sdk/ads');
        unlink($this->root . '/sdk/ui/button.php');
        rmdir($this->root . '/sdk/ui');
    }

    public function testHrefRefusesASchemeThatExecutes(): void
    {
        $this->template('x', '<a href="<?= $href($view) ?>">x</a>');

        $this->expectException(ViewException::class);

        $this->renderer()->render('x', 'javascript:alert(1)');
    }
}
