<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Section 14 of the specification: no template contains a script tag, a
 * hardcoded URL, or unescaped output. This walks every shipped template and
 * checks each rule against it; the checks are private methods taking a
 * file's contents as a string, so the second half of this suite can feed
 * each one a violating snippet and prove it actually fails on something.
 *
 * A rule that has only ever run against templates that already pass it
 * proves nothing about its own shape — a typo that always evaluates true
 * would sit here for years.
 */
#[CoversNothing]
final class TemplateStandardsTest extends TestCase
{
    private const ALLOWED_ECHO_CALLS = ['$e', '$raw', '$attr', '$attrs', '$href', '$partial'];

    /** The one template allowed to write a <script src="…"> with an empty body. */
    private const SCRIPT_LOADING_TEMPLATE = 'layout/base';

    public function testTheStandardIsCheckedAgainstEveryTemplate(): void
    {
        // A provider that silently found nothing would make every rule below
        // pass. Milestone 4 ships fourteen templates; this fails loudly if a
        // future refactor moves the directory.
        $this->assertGreaterThanOrEqual(14, \count(iterator_to_array(self::templates())));
    }

    #[DataProvider('templates')]
    public function testNoScriptTagCarriesBehaviour(string $name, string $path): void
    {
        $violations = $this->scriptTagViolations(file_get_contents($path) ?: '', $name);

        $this->assertSame([], $violations, "{$name}: " . implode('; ', $violations));
    }

    #[DataProvider('templates')]
    public function testEveryShortEchoEscapes(string $name, string $path): void
    {
        $violations = $this->unescapedEchoViolations(file_get_contents($path) ?: '');

        $this->assertSame([], $violations, "{$name}: " . implode('; ', $violations));
    }

    #[DataProvider('templates')]
    public function testEveryUrlAttributeUsesHref(string $name, string $path): void
    {
        $violations = $this->urlAttributeViolations(file_get_contents($path) ?: '');

        $this->assertSame([], $violations, "{$name}: " . implode('; ', $violations));
    }

    #[DataProvider('templates')]
    public function testNoHardcodedUrl(string $name, string $path): void
    {
        $violations = $this->hardcodedUrlViolations(file_get_contents($path) ?: '');

        $this->assertSame([], $violations, "{$name}: " . implode('; ', $violations));
    }

    #[DataProvider('templates')]
    public function testTheTemplateCarriesAtLeastOneRaClass(string $name, string $path): void
    {
        $this->assertTrue(
            $this->hasRaClass(file_get_contents($path) ?: ''),
            "{$name}: carries no 'ra-' class and calls neither Classes::of() nor Classes::identity(), "
            . 'either of which would carry one at render time.',
        );
    }

    #[DataProvider('templates')]
    public function testTheViewIsDeclaredWhenTheTemplateUsesOne(string $name, string $path): void
    {
        $violation = $this->missingViewDeclaration(file_get_contents($path) ?: '');

        $this->assertNull($violation, "{$name}: {$violation}");
    }

    // --- Proof each rule bites: run every check against a violating snippet too. ---

    public function testTheScriptTagRuleCatchesAScriptWithABody(): void
    {
        $violations = $this->scriptTagViolations(
            '<div class="ra-widget"><script>alert(1)</script></div>',
            'ui/widget',
        );

        $this->assertNotSame([], $violations);
    }

    public function testTheScriptTagRuleCatchesAnEmptyScriptOutsideTheBaseLayout(): void
    {
        // A src-only script is allowed only in the one template that writes a
        // whole document; anywhere else it either does not run or runs twice.
        $violations = $this->scriptTagViolations(
            '<div class="ra-widget"><script src="/js/x.js"></script></div>',
            'ui/widget',
        );

        $this->assertNotSame([], $violations);
    }

    public function testTheScriptTagRuleAllowsASrcOnlyScriptInTheBaseLayout(): void
    {
        $violations = $this->scriptTagViolations(
            '<script class="ra-script" src="<?= $href($script) ?>"></script>',
            self::SCRIPT_LOADING_TEMPLATE,
        );

        $this->assertSame([], $violations);
    }

    public function testTheScriptTagRuleIsCaseInsensitive(): void
    {
        $violations = $this->scriptTagViolations(
            '<div class="ra-widget"><SCRIPT>alert(1)</SCRIPT></div>',
            'ui/widget',
        );

        $this->assertNotSame([], $violations);
    }

    public function testTheEscapingRuleCatchesAShortEchoThatCallsNothing(): void
    {
        $violations = $this->unescapedEchoViolations(
            '<li class="ra-item<?= $flag ? \' on\' : \'\' ?>"></li>',
        );

        $this->assertNotSame([], $violations);
    }

    public function testTheEscapingRuleAcceptsEveryAllowedHelper(): void
    {
        $snippet = '<?= $e($a) ?> <?= $raw($b) ?> <?= $attr($c) ?> <?= $attrs($d) ?> <?= $href($f) ?> <?= $partial("x") ?>';

        $this->assertSame([], $this->unescapedEchoViolations($snippet));
    }

    public function testTheUrlAttributeRuleCatchesEUsedForAHref(): void
    {
        $violations = $this->urlAttributeViolations('<a href="<?= $e($view->url) ?>">x</a>');

        $this->assertNotSame([], $violations);
    }

    /** @return array<string, array{string}> */
    public static function urlAttributesWrittenEveryWay(): array
    {
        return [
            'double quoted' => ['<a href="<?= $e($view->url) ?>">x</a>'],
            'single quoted' => ["<a href='<?= \$e(\$view->url) ?>'>x</a>"],
            'unquoted' => ['<a href=<?= $e($view->url) ?>>x</a>'],
            'spaced around the equals' => ['<a href = "<?= $e($view->url) ?>">x</a>'],
            'an image source' => ['<img src="<?= $e($view->icon) ?>">'],
            'a form action' => ['<form action="<?= $e($view->url) ?>">'],
        ];
    }

    #[DataProvider('urlAttributesWrittenEveryWay')]
    public function testTheUrlAttributeRuleDoesNotDependOnHowTheAttributeIsQuoted(string $markup): void
    {
        // A rule that only recognised one quoting style would be checking a
        // house convention rather than the property it exists for. The single
        // quoted form is the one that was passing.
        $this->assertNotSame([], $this->urlAttributeViolations($markup));
    }

    public function testTheUrlAttributeRuleAcceptsHrefForAHref(): void
    {
        $violations = $this->urlAttributeViolations('<a href="<?= $href($view->url) ?>">x</a>');

        $this->assertSame([], $violations);
    }

    public function testTheHardcodedUrlRuleCatchesALeadingSlashHref(): void
    {
        $violations = $this->hardcodedUrlViolations('<a href="/p/ads">Ads</a>');

        $this->assertNotSame([], $violations);
    }

    public function testTheHardcodedUrlRuleCatchesAnAbsoluteScheme(): void
    {
        $violations = $this->hardcodedUrlViolations('<a href="https://example.com">Ads</a>');

        $this->assertNotSame([], $violations);
    }

    public function testTheHardcodedUrlRuleIgnoresAComment(): void
    {
        $violations = $this->hardcodedUrlViolations('<!-- see https://example.com/docs --><a href="<?= $href($u) ?>">x</a>');

        $this->assertSame([], $violations);
    }

    public function testTheRaClassRuleCatchesATemplateWithNoRaClassAnywhere(): void
    {
        $this->assertFalse($this->hasRaClass('<span class="plain"><?= $e($view) ?></span>'));
    }

    public function testTheRaClassRuleAcceptsAClassesHelperCall(): void
    {
        $this->assertTrue($this->hasRaClass(
            '<span class="<?= $e(\RockAdmin\View\Classes::of(\'badge\')) ?>"></span>',
        ));
    }

    public function testTheViewDeclarationRuleCatchesAMissingVarTag(): void
    {
        $violation = $this->missingViewDeclaration('<?php ?><span><?= $e($view->title) ?></span>');

        $this->assertNotNull($violation);
    }

    public function testTheViewDeclarationRuleAcceptsATemplateThatDeclaresIt(): void
    {
        $violation = $this->missingViewDeclaration(
            "<?php\n/**\n * @var \\RockAdmin\\View\\PageView \$view\n */\n?><span><?= \$e(\$view->title) ?></span>",
        );

        $this->assertNull($violation);
    }

    public function testTheViewDeclarationRuleToleratesATemplateThatUsesNoView(): void
    {
        $violation = $this->missingViewDeclaration('<div class="ra-modal"></div>');

        $this->assertNull($violation);
    }

    // --- The rules themselves. ---

    /**
     * Rule 1: no <script> that carries behaviour, in any casing, anywhere.
     * A <script src="…"> with an empty body is allowed only in the one
     * template that writes a whole document and has to load its assets
     * somewhere; every other template can be returned as a fragment and
     * inserted into a live page, where a script tag either does not run or
     * runs twice.
     *
     * @return list<string>
     */
    private function scriptTagViolations(string $contents, string $templateName): array
    {
        $violations = [];

        // The opening tag's attributes are matched a character or a whole quoted
        // string at a time, because an attribute value carrying a short echo tag
        // contains its own closing angle bracket (the one that ends the PHP
        // echo tag), which a plain [^>]* would stop at.
        $pattern = '/<script\b(?:"[^"]*"|\'[^\']*\'|[^>])*>(.*?)<\/script\s*>/is';

        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        foreach ($matches[0] as $index => [$whole, $offset]) {
            $body = trim($matches[1][$index][0]);
            $line = $this->lineAt($contents, (int) $offset);

            if ($body !== '') {
                $violations[] = "line {$line}: <script> carries a body";

                continue;
            }

            if ($templateName !== self::SCRIPT_LOADING_TEMPLATE) {
                $violations[] = "line {$line}: <script> is only allowed, empty and src-only, "
                    . 'in ' . self::SCRIPT_LOADING_TEMPLATE;
            }
        }

        return $violations;
    }

    /**
     * Rule 2: every short echo tag is immediately followed, ignoring
     * whitespace, by a call to one of the escaping helpers.
     *
     * @return list<string>
     */
    private function unescapedEchoViolations(string $contents): array
    {
        $violations = [];

        if (preg_match_all('/<\?=\s*/', $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $pattern = '/^(' . implode('|', array_map(
            static fn (string $call): string => preg_quote($call, '/'),
            self::ALLOWED_ECHO_CALLS,
        )) . ')\(/';

        foreach ($matches[0] as [$whole, $offset]) {
            $rest = substr($contents, $offset + \strlen($whole), 40);
            $line = $this->lineAt($contents, (int) $offset);

            if (preg_match($pattern, $rest) !== 1) {
                $violations[] = 'line ' . $line . ': the short echo tag is not immediately followed by '
                    . implode('(), ', self::ALLOWED_ECHO_CALLS) . '()';
            }
        }

        return $violations;
    }

    /**
     * Rule 2b: href=, src= and action= attributes whose value is a short
     * echo tag must call $href(), not $e(). $e() escapes the characters that
     * would end the attribute but says nothing about the scheme, so $e() on
     * a URL from a data column is how a javascript: link reaches a page.
     *
     * @return list<string>
     */
    private function urlAttributeViolations(string $contents): array
    {
        $violations = [];

        // Both quote styles, and none at all. Matching only double quotes
        // would have left the rule checking a house convention rather than
        // the property it exists for: a single-quoted URL attribute is
        // exactly the case this rule is about, and it was passing.
        $pattern = '/\b(href|src|action)\s*=\s*[\'"]?<\?=\s*(\$\w+)\(/';

        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        foreach ($matches[0] as $index => [, $offset]) {
            $attribute = $matches[1][$index][0];
            $call = $matches[2][$index][0];
            $line = $this->lineAt($contents, (int) $offset);

            if ($call !== '$href') {
                $violations[] = "line {$line}: {$attribute}=\"<?= {$call}(...) ?>\" should call \$href(), not {$call}()";
            }
        }

        return $violations;
    }

    /**
     * Rule 3: no hardcoded URL. Every URL comes from $url(), $route() or a
     * view object, never from a literal written into the template.
     *
     * @return list<string>
     */
    private function hardcodedUrlViolations(string $contents): array
    {
        $withoutComments = preg_replace('/<!--.*?-->/s', '', $contents) ?? $contents;
        $violations = [];

        $patterns = [
            '/\b(?:href|src|action)\s*=\s*["\']\//' => 'a href=, src= or action= attribute starting with "/"',
            '/https?:\/\//' => 'a literal http:// or https:// URL',
        ];

        foreach ($patterns as $pattern => $description) {
            if (preg_match_all($pattern, $withoutComments, $matches, PREG_OFFSET_CAPTURE) === false) {
                continue;
            }

            foreach ($matches[0] as [, $offset]) {
                $line = $this->lineAt($withoutComments, (int) $offset);
                $violations[] = "line {$line}: {$description}";
            }
        }

        return $violations;
    }

    /**
     * Rule 4: the file carries at least one 'ra-' class. A template with no
     * elements at all is not a thing this project has, so there is no
     * exemption. A class assembled through Classes::of() or
     * Classes::identity() always carries the 'ra-' prefix at render time
     * even though the literal string is not in the source, so a call to
     * either counts.
     */
    private function hasRaClass(string $contents): bool
    {
        return str_contains($contents, 'ra-') || str_contains($contents, 'Classes::');
    }

    /**
     * Rule 5: a template that reads $view declares its type in the
     * docblock, unless it never reads $view at all.
     */
    private function missingViewDeclaration(string $contents): ?string
    {
        if (!preg_match('/\$view\b/', $contents)) {
            return null;
        }

        if (preg_match('/@var\s+.+?\$view\b/', $contents) === 1) {
            return null;
        }

        return 'uses $view but declares no @var for it';
    }

    private function lineAt(string $contents, int $offset): int
    {
        return substr_count($contents, "\n", 0, $offset) + 1;
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function templates(): iterable
    {
        foreach (self::templateFiles() as $file) {
            $path = $file->getPathname();
            $relative = substr(str_replace('\\', '/', $path), \strlen(self::templatesRoot()) + 1);
            $name = substr($relative, 0, -4);

            yield $name => [$name, $path];
        }
    }

    /** @return list<SplFileInfo> */
    private static function templateFiles(): array
    {
        $root = self::templatesRoot();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        $files = [];

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        usort($files, static fn (SplFileInfo $a, SplFileInfo $b): int => $a->getPathname() <=> $b->getPathname());

        return $files;
    }

    private static function templatesRoot(): string
    {
        return \dirname(__DIR__, 3) . '/templates';
    }
}
