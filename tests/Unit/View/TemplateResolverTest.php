<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\View\TemplateResolver;
use RockAdmin\View\ViewException;

#[CoversClass(TemplateResolver::class)]
final class TemplateResolverTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        // The resolver reports forward slashes, so the expectations must use
        // them too — sys_get_temp_dir() returns backslashes on Windows.
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-templates-' . bin2hex(random_bytes(6));
        $this->write('sdk/ui/button.php', 'sdk button');
        $this->write('sdk/layout/base.php', 'sdk base');
        $this->write('project/ui/button.php', 'project button');
        $this->write('theme/ui/badge.php', 'theme badge');
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    private function write(string $relative, string $contents): void
    {
        $file = $this->root . '/' . $relative;
        $directory = \dirname($file);

        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        file_put_contents($file, $contents);
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

    /**
     * @param array<string, string> $overrides
     */
    private function resolver(array $overrides = []): TemplateResolver
    {
        return new TemplateResolver(
            [$this->root . '/project', $this->root . '/theme'],
            $this->root . '/sdk',
            $overrides,
        );
    }

    public function testTheFirstPathThatHasTheTemplateWins(): void
    {
        $this->assertSame(
            'project button',
            file_get_contents($this->resolver()->resolve('ui/button')),
        );
    }

    public function testAPathFurtherDownIsSearchedWhenTheFirstDoesNotHaveIt(): void
    {
        $this->assertSame(
            'theme badge',
            file_get_contents($this->resolver()->resolve('ui/badge')),
        );
    }

    public function testTheSdkDefaultIsAlwaysSearchedLast(): void
    {
        $this->assertSame(
            'sdk base',
            file_get_contents($this->resolver()->resolve('layout/base')),
        );
    }

    public function testHasAnswersWithoutThrowing(): void
    {
        $resolver = $this->resolver();

        $this->assertTrue($resolver->has('ui/button'));
        $this->assertFalse($resolver->has('ui/nothing'));
    }

    public function testAMissingTemplateNamesEveryPlaceItWasLookedFor(): void
    {
        // The error a project hits most often. It has to say where to put the
        // file, not merely that one is absent.
        // Asserted by catching rather than with expectExceptionMessage(),
        // because a second call to that method replaces the first and three
        // of these four checks would silently not happen.
        try {
            $this->resolver()->resolve('ui/nothing');
            $this->fail('A missing template should throw.');
        } catch (ViewException $e) {
            $this->assertStringContainsString('ui/nothing', $e->getMessage());
            $this->assertStringContainsString($this->root . '/project', $e->getMessage());
            $this->assertStringContainsString($this->root . '/theme', $e->getMessage());
            $this->assertStringContainsString($this->root . '/sdk', $e->getMessage());
        }
    }

    public function testAnOverrideRedirectsOneNameToAnother(): void
    {
        $this->write('project/ads/button.php', 'ads button');

        $resolver = $this->resolver(['ui/button' => 'ads/button']);

        $this->assertSame('ads button', file_get_contents($resolver->resolve('ui/button')));
    }

    public function testAnOverrideMayBeWrittenWithItsExtension(): void
    {
        // The specification's per-page override syntax writes the extension:
        // 'templates' => ['row' => 'ads/row.php'].
        $this->write('project/ads/button.php', 'ads button');

        $resolver = $this->resolver(['ui/button' => 'ads/button.php']);

        $this->assertSame('ads button', file_get_contents($resolver->resolve('ui/button')));
    }

    public function testAnOverrideStillGoesThroughTheCascade(): void
    {
        // An override names a template, not a file. It cannot reach outside
        // the configured directories, and the SDK default still backs it.
        $resolver = $this->resolver(['ui/button' => 'layout/base']);

        $this->assertSame('sdk base', file_get_contents($resolver->resolve('ui/button')));
    }

    public function testAnOverrideIsNotAppliedTwice(): void
    {
        // ui/button -> ui/badge must not then follow a ui/badge override, or a
        // pair of overrides becomes a loop nobody wrote.
        $resolver = $this->resolver(['ui/button' => 'ui/badge', 'ui/badge' => 'layout/base']);

        $this->assertSame('theme badge', file_get_contents($resolver->resolve('ui/button')));
    }

    public function testWithOverridesLeavesTheOriginalAlone(): void
    {
        $this->write('project/ads/button.php', 'ads button');

        $resolver = $this->resolver();
        $scoped = $resolver->withOverrides(['ui/button' => 'ads/button']);

        $this->assertSame('ads button', file_get_contents($scoped->resolve('ui/button')));
        $this->assertSame('project button', file_get_contents($resolver->resolve('ui/button')));
    }

    /** @return array<string, array{string}> */
    public static function malformedNames(): array
    {
        return [
            'parent directory' => ['../secrets', 'cannot leave it'],
            'parent directory inside' => ['ui/../../secrets', 'cannot leave it'],
            'single dot' => ['ui/./button', 'cannot leave it'],
            'absolute' => ['/etc/passwd', 'empty segment'],
            'windows absolute' => ['C:/windows/win.ini', 'drive or stream wrapper'],
            'stream wrapper' => ['php://filter/resource=x', 'drive or stream wrapper'],
            'backslash' => ['ui\\button', 'every platform'],
            'null byte' => ["ui/button\0.php", 'null byte'],
            'empty' => ['', 'An empty'],
            'trailing slash' => ['ui/', 'empty segment'],
            'double slash' => ['ui//button', 'empty segment'],
        ];
    }

    #[DataProvider('malformedNames')]
    public function testAMalformedNameIsRefusedAndSaysWhy(string $name, string $reason): void
    {
        // Each guard carries its own message. Asserting only the exception
        // class would let the guards be merged or reordered with no test
        // noticing, and would leave whoever wrote the name in configuration
        // reading the source to find out which rule they broke.
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage($reason);

        $this->resolver()->resolve($name);
    }

    public function testAMalformedOverrideValueIsRefusedToo(): void
    {
        // The override comes from configuration, so it is the more likely of
        // the two to carry something odd — and it must not be a way past the
        // rules the name itself is held to.
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('cannot leave it');

        $this->resolver(['ui/button' => '../../etc/passwd'])->resolve('ui/button');
    }

    public function testResolutionsRecordWhatCameFromWhere(): void
    {
        // The development console shows this, and it is the fastest answer to
        // "why is my override not being used".
        $resolver = $this->resolver();
        $resolver->resolve('ui/button');
        $resolver->resolve('layout/base');

        $this->assertSame(
            [
                ['name' => 'ui/button', 'file' => $this->root . '/project/ui/button.php', 'override' => null],
                ['name' => 'layout/base', 'file' => $this->root . '/sdk/layout/base.php', 'override' => null],
            ],
            $resolver->resolutions(),
        );
    }

    public function testResolutionsRecordAnOverrideThatWasApplied(): void
    {
        $this->write('project/ads/button.php', 'ads button');

        $resolver = $this->resolver(['ui/button' => 'ads/button']);
        $resolver->resolve('ui/button');

        $this->assertSame(
            [['name' => 'ui/button', 'file' => $this->root . '/project/ads/button.php', 'override' => 'ads/button']],
            $resolver->resolutions(),
        );
    }

    public function testTheSameTemplateIsRecordedOnceHoweverOftenItRenders(): void
    {
        // A grid resolves region/list/row once per row. The console wants the
        // list of templates in play, not a thousand identical lines.
        $resolver = $this->resolver();
        $resolver->resolve('ui/button');
        $resolver->resolve('ui/button');

        $this->assertCount(1, $resolver->resolutions());
    }

    public function testAResolutionThroughACopyMadeByWithOverridesIsVisibleOnTheOriginal(): void
    {
        // A page-level template override is the normal path for any page
        // that configures `templates`, and rendering happens through the
        // copy withOverrides() returns, never through the original. Before
        // the log was shared, the original's resolutions() stayed empty for
        // exactly the pages the development console exists to explain.
        $resolver = $this->resolver();
        $scoped = $resolver->withOverrides(['ui/button' => 'ui/badge']);

        $scoped->resolve('ui/button');

        $this->assertSame($scoped->resolutions(), $resolver->resolutions());
        $this->assertSame(
            [['name' => 'ui/button', 'file' => $this->root . '/theme/ui/badge.php', 'override' => 'ui/badge']],
            $resolver->resolutions(),
        );
    }

    public function testAChainOfCopiesAllShareTheSameLog(): void
    {
        $resolver = $this->resolver();
        $first = $resolver->withOverrides(['ui/button' => 'ui/badge']);
        $second = $first->withOverrides([]);

        $second->resolve('ui/button');
        $resolver->resolve('layout/base');

        $this->assertCount(2, $resolver->resolutions());
        $this->assertCount(2, $second->resolutions());
    }

    public function testHasDoesNotRecordAResolution(): void
    {
        // A probe for whether a template exists is not the same as having
        // rendered it. TemplateErrorPage calls has() twice per error before
        // it ever renders anything; both calls logging would show
        // error/500 in the console whether or not it actually rendered.
        $resolver = $this->resolver();

        $resolver->has('ui/button');
        $resolver->has('ui/nothing');

        $this->assertSame([], $resolver->resolutions());
    }

    public function testHasStillAnswersCorrectlyAfterNotRecording(): void
    {
        $resolver = $this->resolver();

        $this->assertTrue($resolver->has('ui/button'));
        $this->assertFalse($resolver->has('ui/nothing'));
        $this->assertSame([], $resolver->resolutions());
    }
}
