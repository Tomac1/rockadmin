<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RockAdmin\View\ButtonView;
use SplFileInfo;

/**
 * Buttons, checked against the vocabulary the design system actually defines.
 *
 * Two mistakes look identical in the markup and neither raises anything.
 *
 * `.ra-btn` carries no height and no padding of its own — the size comes from
 * a modifier, `--sm` through `--xl`. A button without one falls back to the
 * browser's default and renders 18px tall, which is what "the button looks
 * flat" turned out to mean. And a modifier the design system does not define,
 * `ra-btn--xs`, simply does nothing while reading like it works.
 *
 * Five of seven buttons shipped with one or the other, and they were found by
 * somebody looking at the sign-in page rather than by any of 2179 tests.
 */
#[CoversNothing]
final class ButtonModifierTest extends TestCase
{
    public function testEveryButtonNamesAModifierTheDesignSystemDefines(): void
    {
        $known = $this->modifiersTheDesignSystemDefines();
        $sizes = array_values(array_intersect(['sm', 'md', 'lg', 'xl'], $known));

        $this->assertContains('primary', $known, 'The design system stopped defining button modifiers.');
        $this->assertNotEmpty($sizes, 'The design system stopped defining button sizes.');

        $examined = 0;

        foreach ($this->templates() as $file) {
            foreach ($this->buttonClassLists($file) as $classes) {
                $examined++;

                $modifiers = [];

                foreach (preg_split('/\s+/', trim($classes)) ?: [] as $token) {
                    if (str_starts_with($token, 'ra-btn--')) {
                        $modifiers[] = substr($token, 8);
                    }
                }

                $where = basename($file) . ': "' . $classes . '"';

                foreach ($modifiers as $modifier) {
                    $this->assertContains(
                        $modifier,
                        $known,
                        $where . " — ra-btn--{$modifier} is not a modifier the design system defines, so it does nothing.",
                    );
                }

                $this->assertNotEmpty(
                    array_intersect($modifiers, $sizes),
                    $where . ' — a button with no size modifier gets the browser\'s default padding, not the design system\'s.',
                );
            }
        }

        // Without this the whole test passes by finding nothing, which is how
        // it behaved for its first three revisions while I read its silence
        // as the code being wrong.
        $this->assertGreaterThan(4, $examined, 'No buttons were examined; the pattern has stopped matching.');
    }

    public function testAButtonBuiltInPhpNamesModifiersTheDesignSystemDefines(): void
    {
        // The test above reads templates, so it saw none of this: the page
        // header's buttons are `ButtonView`s and their class list is composed
        // in PHP. They shipped as `ra-btn btn btn-primary` -- Bootstrap's
        // vocabulary, no size, 16px tall -- and passed every test here while
        // doing it.
        $known = $this->modifiersTheDesignSystemDefines();
        $sizes = array_values(array_intersect(['sm', 'md', 'lg', 'xl'], $known));

        // Styles a host may pass, including ones the design system has no
        // button for: each must still come out as a button.
        $styles = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark', 'link', 'chartreuse'];

        foreach ($styles as $style) {
            $classes = preg_split('/\s+/', (new ButtonView('export', 'Label', '/x', style: $style))->classes()) ?: [];

            $this->assertContains('ra-btn', $classes, "style '{$style}' does not produce a button at all.");

            $modifiers = [];

            foreach ($classes as $token) {
                if (str_starts_with($token, 'ra-btn--')) {
                    $modifiers[] = substr($token, 8);
                }
            }

            foreach ($modifiers as $modifier) {
                $this->assertContains(
                    $modifier,
                    $known,
                    "style '{$style}' emits ra-btn--{$modifier}, which the design system does not define.",
                );
            }

            $this->assertNotEmpty(
                array_intersect($modifiers, $sizes),
                "style '{$style}' emits a button with no size modifier.",
            );
        }
    }

    /** @return list<string> */
    private function modifiersTheDesignSystemDefines(): array
    {
        $css = file_get_contents(\dirname(__DIR__, 3) . '/assets/css/ds/components.css') ?: '';

        preg_match_all('/^\.ra-btn--([a-z-]+)/m', $css, $found);

        return array_values(array_unique($found[1]));
    }

    /** @return list<string> every template file, however deep */
    private function templates(): array
    {
        $files = [];
        $tree = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(\dirname(__DIR__, 3) . '/templates'),
        );

        foreach ($tree as $entry) {
            if ($entry instanceof SplFileInfo && $entry->getExtension() === 'php') {
                $files[] = $entry->getPathname();
            }
        }

        return $files;
    }

    /** @return list<string> the class attribute of every element carrying ra-btn */
    private function buttonClassLists(string $file): array
    {
        $markup = file_get_contents($file) ?: '';

        if (!str_contains($markup, 'ra-btn')) {
            return [];
        }

        preg_match_all('/class="([^"]*)"/', $markup, $uses);

        // `ra-btn` as a whole token, not as a substring: this project has its
        // own `ra-btn-label` for the text inside a button, and a substring
        // match reported it as a button with no size. A class is a token in a
        // list -- the same thing that made an error count read 18 instead of 3
        // an hour ago.
        return array_values(array_filter(
            $uses[1],
            static fn (string $classes): bool => \in_array('ra-btn', preg_split('/\s+/', trim($classes)) ?: [], true),
        ));
    }
}
