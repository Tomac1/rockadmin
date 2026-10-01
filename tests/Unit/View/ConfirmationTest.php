<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\View\Escaper;
use RockAdmin\View\PageView;
use RockAdmin\View\Renderer;
use RockAdmin\View\ShellView;
use RockAdmin\View\TemplateResolver;

/**
 * Confirmations are dialogs, never `window.confirm()`.
 *
 * A project rule rather than a preference. The native dialog looks nothing
 * like the rest of the admin, cannot carry the action's own wording or
 * weight, and blocks the whole page — including anything driving the browser,
 * which is how it came to freeze a tab twice while milestone 8's actions were
 * being checked.
 *
 * The rule is enforced here rather than remembered, because `window.confirm`
 * is four words away whenever somebody wants a quick yes-or-no and it works
 * well enough to survive review.
 */
#[CoversNothing]
final class ConfirmationTest extends TestCase
{
    public function testNoScriptInThisProjectOpensANativeDialog(): void
    {
        foreach ($this->scripts() as $path) {
            $code = $this->withoutCommentsOrStrings((string) file_get_contents($path));

            foreach (['confirm', 'alert', 'prompt'] as $native) {
                $this->assertSame(
                    0,
                    preg_match('/(?<![\w.$])(?:window\s*\.\s*)?' . $native . '\s*\(/', $code),
                    basename($path) . " calls {$native}(). Every confirmation in this admin is the "
                    . 'dialog in templates/ui/confirm.php, which looks like the admin, carries the '
                    . "action's own question, and does not freeze the page.",
                );
            }
        }
    }

    public function testTheShellCarriesTheConfirmationDialog(): void
    {
        $html = $this->shell();

        $this->assertStringContainsString('id="ra-confirm"', $html);
        $this->assertSame(1, substr_count($html, 'id="ra-confirm"'));
    }

    public function testItIsHiddenUntilSomethingAsks(): void
    {
        // Same shape as the modal and the offcanvas: the design system shows
        // a component because it exists, so `hidden` is what closes it. Three
        // components have now been visible on every page for exactly this
        // reason.
        if (preg_match('/<div[^>]*id="ra-confirm"[^>]*>/', $this->shell(), $match) !== 1) {
            $this->fail('No confirmation dialog was rendered.');
        }

        $this->assertStringContainsString('hidden', $match[0]);
    }

    public function testItIsItsOwnElementRatherThanTheSharedModal(): void
    {
        // A Delete button can sit inside a panel that was itself opened into
        // the shared modal, and confirming it would then destroy the thing
        // being confirmed. Two dialogs that can be open at once need two
        // elements.
        $html = $this->shell();

        $this->assertStringContainsString('id="ra-modal"', $html);
        $this->assertStringContainsString('id="ra-confirm"', $html);
        $this->assertNotSame(
            strpos($html, 'id="ra-modal"'),
            strpos($html, 'id="ra-confirm"'),
        );
    }

    public function testItAnnouncesItselfAsAQuestion(): void
    {
        // `alertdialog`, not `dialog`: it interrupts to ask something, which
        // is what the role is for, and it makes a screen reader read the
        // question on open rather than waiting to be asked for it.
        $html = $this->shell();

        $this->assertStringContainsString('role="alertdialog"', $html);
        $this->assertStringContainsString('aria-describedby="ra-confirm-question"', $html);
    }

    public function testItOffersBothAnswersAsRealButtons(): void
    {
        $html = $this->shell();

        $this->assertStringContainsString('ra-confirm-yes', $html);
        $this->assertStringContainsString('ra-confirm-no', $html);

        // `type="button"`, because the dialog is not inside a form and a
        // default-type button in one would submit it.
        preg_match_all('/<button[^>]*ra-confirm-(?:yes|no)[^>]*>/', $html, $buttons);

        $this->assertCount(2, $buttons[0]);

        foreach ($buttons[0] as $button) {
            $this->assertStringContainsString('type="button"', $button);
        }
    }

    // --- harness ---

    /** @return list<string> every JavaScript file this package ships */
    private function scripts(): array
    {
        $found = glob(\dirname(__DIR__, 3) . '/assets/js/*.js');

        $this->assertNotEmpty($found, 'No scripts were examined, so this proves nothing.');

        return array_values(array_map(strval(...), $found ?: []));
    }

    /**
     * The code with its comments and string literals taken out.
     *
     * Both have to go: this file's own rule is explained in a comment that
     * names `window.confirm()`, and the explanation must not be the thing
     * that trips the check. A string is removed for the same reason — a
     * message mentioning the word is not a call.
     */
    private function withoutCommentsOrStrings(string $code): string
    {
        $stripped = preg_replace('~/\*.*?\*/~s', '', $code);
        $this->assertIsString($stripped);

        $stripped = preg_replace('~//[^\n]*~', '', $stripped);
        $this->assertIsString($stripped);

        $stripped = preg_replace('~([\'"])(?:\\\\.|(?!\1).)*\1~s', "''", $stripped);
        $this->assertIsString($stripped);

        return $stripped;
    }

    private function shell(): string
    {
        $renderer = new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/admin'));

        return $renderer->render('layout/base', new PageView(
            'ads',
            'Ads',
            new ShellView('RockAdmin'),
            slots: ['main' => '<div class="ra-region">grid</div>'],
        ));
    }
}
