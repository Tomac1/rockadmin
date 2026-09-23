<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The theme, checked for the one class of mistake that reads as a styling bug
 * and is really a cascade bug.
 *
 * The shell's navbar keeps its dark background in both light and dark mode, so
 * it declares its own foreground tokens. A declaration on the element itself
 * can never be overridden by a rule targeting :root or <html>: that reaches
 * the element only by inheritance, and inheritance does not apply where the
 * element has its own value. So if those declarations reference a variable
 * that a dark-mode block redefines, the navbar silently follows the page
 * theme after all — which is how its menu came to be near-black text on a
 * near-black bar.
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
        $this->css = file_get_contents(\dirname(__DIR__, 3) . '/assets/css/rockadmin.css') ?: '';

        $this->assertNotSame('', $this->css, 'The theme stylesheet is missing or empty.');
    }

    public function testTheShellsOwnColoursAreNotRedefinedByAnyThemeBlock(): void
    {
        $themed = $this->propertiesRedefinedInDarkMode();

        // The guard against a vacuous run: dark mode must actually redefine
        // something, or every assertion below passes by finding nothing.
        $this->assertNotEmpty($themed, 'No dark-mode block redefines any custom property.');

        foreach (['--ra-shell-bg', '--ra-shell-fg', '--ra-shell-fg-rgb'] as $token) {
            $this->assertNotContains(
                $token,
                $themed,
                "{$token} is redefined in a dark-mode block. The shell's colours are the same in both "
                . 'themes on purpose; a theme-varying value here is what made the navbar menu illegible.',
            );
        }
    }

    public function testTheNavbarsTokensDoNotFollowThePageTheme(): void
    {
        $themed = $this->propertiesRedefinedInDarkMode();
        $block = $this->ruleBody('.ra-navbar');

        $this->assertNotSame('', $block, 'No .ra-navbar rule found in the theme.');
        $this->assertStringContainsString('--bs-navbar-color', $block);

        foreach ($this->variablesReferencedIn($block) as $reference) {
            $this->assertNotContains(
                $reference,
                $themed,
                ".ra-navbar reads {$reference}, which a dark-mode block redefines. Declared on the "
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
