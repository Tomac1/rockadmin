<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The theme, checked for the one class of mistake that reads as a styling bug
 * and is really a cascade bug.
 *
 * The navigation bars are dark whatever the page behind them does, so their
 * colours must not be drawn from a token the page's theme redefines. A
 * declaration on the element itself can never be overridden by a rule
 * targeting :root: that reaches the element only by inheritance, and
 * inheritance does not apply where the element has its own value. So a navbar
 * reading a theme-varying token follows the page theme anyway — which is how
 * its menu came to be near-black text on a near-black bar, twice.
 *
 * The tokens moved when the design system arrived: `--ra-shell-*` became
 * `--nav-primary-*` and `--nav-secondary-*`, declared in the vendored
 * `ds/tokens/colors.css`. The property did not move, so neither did this
 * test — it was re-pointed rather than deleted, because the mistake it
 * catches is a property of cascades and not of one stylesheet.
 *
 * This cannot measure contrast; there is no browser here. It checks the
 * structural property that made the contrast wrong, which is the part a
 * future edit is likely to reintroduce.
 */
#[CoversNothing]
final class ThemeTest extends TestCase
{
    private string $css;

    protected function setUp(): void
    {
        // Both sheets: the design system declares the navigation's colours,
        // this project's own may still reference them.
        $root = \dirname(__DIR__, 3) . '/assets/css/';

        $this->css = implode('
', array_map(
            static fn (string $file): string => file_get_contents($root . $file) ?: '',
            ['ds/tokens/colors.css', 'ds/components.css', 'rockadmin.css'],
        ));

        $this->assertNotSame('', trim($this->css), 'The stylesheets are missing or empty.');
    }

    public function testEveryStylesheetParsesToTheEnd(): void
    {
        // An unbalanced brace or an unterminated comment raises nothing: the
        // browser's parser swallows everything after it and the page renders
        // with part of its stylesheet silently missing. That is exactly what
        // happened here -- a rule cut in half left 143 lines of this file
        // dead, the login page lost its layout entirely, and it read as
        // "Bootstrap is leaking" rather than as a syntax error, because there
        // is no error to see.
        //
        // The first version of this test counted `/*` against `*\/` and
        // reported the vendored sheet as broken, because a `/*` inside a
        // comment is text to CSS and a delimiter to a counter. So it scans
        // the way CSS reads: one pass, no nesting, comments stripped before
        // the braces are counted at all.
        $root = \dirname(__DIR__, 3) . '/assets/css/';

        foreach (['rockadmin.css', 'fonts.css', 'ds/components.css', 'ds/tokens/colors.css'] as $file) {
            $css = file_get_contents($root . $file) ?: '';

            $this->assertNotSame('', $css, "{$file} is missing or empty.");

            $stripped = preg_replace('~/\*.*?\*/~s', '', $css);

            $this->assertIsString($stripped);
            $this->assertStringNotContainsString(
                '/*',
                $stripped,
                "{$file} has an unterminated comment; everything after it is ignored.",
            );
            $this->assertSame(
                substr_count($stripped, '{'),
                substr_count($stripped, '}'),
                "{$file} has an unclosed rule; everything after it is ignored.",
            );
        }
    }

    public function testTheNavigationsOwnColoursAreNotRedefinedByAnyThemeBlock(): void
    {
        $themed = $this->propertiesRedefinedInDarkMode();

        // No dark-mode block means nothing to collide with, which is the
        // state the design system arrived in: it ships one palette. Said out
        // loud rather than asserted away, because the day a dark block is
        // added this test starts doing its job again without being edited.
        if ($themed === []) {
            $this->assertStringNotContainsString(
                'prefers-color-scheme: dark',
                $this->css,
                'A dark-mode block exists but redefines nothing — the parser has stopped seeing it.',
            );

            return;
        }

        foreach (['--nav-primary-bg', '--nav-primary-text', '--nav-secondary-bg', '--nav-secondary-text'] as $token) {
            $this->assertNotContains(
                $token,
                $themed,
                "{$token} is redefined in a dark-mode block. The navigation is dark in both themes on "
                . 'purpose; a theme-varying value here is what made its menu illegible, twice.',
            );
        }
    }

    public function testTheNavbarsTokensDoNotFollowThePageTheme(): void
    {
        $themed = $this->propertiesRedefinedInDarkMode();
        $block = $this->ruleBody('.ra-topnav');

        $this->assertNotSame('', $block, 'No .ra-topnav rule found in the stylesheets.');

        foreach ($this->variablesReferencedIn($block) as $reference) {
            $this->assertNotContains(
                $reference,
                $themed,
                ".ra-topnav reads {$reference}, which a dark-mode block redefines. Declared on the "
                . 'element itself, these tokens cannot be overridden from :root — so the navbar would '
                . 'follow the page theme while its background does not.',
            );
        }
    }

    /**
     * Every custom property declared inside a dark-mode block.
     *
     * @return list<string>
     */
    private function propertiesRedefinedInDarkMode(): array
    {
        $names = [];

        foreach (['@media (prefers-color-scheme: dark)', '[data-bs-theme="dark"]'] as $marker) {
            $offset = 0;

            while (($start = strpos($this->css, $marker, $offset)) !== false) {
                $offset = $start + \strlen($marker);
                $chunk = substr($this->css, $start, 4000);

                if (preg_match_all('/(--[a-z0-9-]+)\s*:/i', $chunk, $matches) >= 1) {
                    foreach ($matches[1] as $name) {
                        $names[] = $name;
                    }
                }
            }
        }

        return array_values(array_unique($names));
    }

    /** The declarations of the first rule whose selector is exactly $selector. */
    private function ruleBody(string $selector): string
    {
        if (preg_match('/(?:^|\})\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/m', $this->css, $m) !== 1) {
            return '';
        }

        return $m[1];
    }

    /** @return list<string> */
    private function variablesReferencedIn(string $block): array
    {
        preg_match_all('/var\(\s*(--[a-z0-9-]+)/i', $block, $matches);

        return array_values(array_unique($matches[1]));
    }
}
