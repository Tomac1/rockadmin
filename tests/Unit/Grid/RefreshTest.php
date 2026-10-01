<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\View\ActionView;
use RockAdmin\View\Escaper;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * `refresh`, as far as PHP can see it.
 *
 * It was a configured key that did nothing for two milestones: loaded,
 * validated, refused when it named a region the page had not got, and then
 * carried to nothing. That is the third key in this project to have that
 * shape, and the pattern is always the same — the loader is the part somebody
 * writes a test for, and the part that consumes it is the part nobody notices
 * is missing. So what is pinned here is the one thing a PHP test can pin: the
 * value reaching the markup.
 *
 * What it does once it is there was measured in a browser, because it is a
 * fetch and a DOM swap and there is nothing here to run it with.
 */
#[CoversNothing]
final class RefreshTest extends TestCase
{
    public function testAnActionDeclaringRefreshSaysSoOnItsForm(): void
    {
        $html = $this->render($this->action(refresh: ['grid', 'stats']));

        $this->assertStringContainsString('data-ra-refresh="grid stats"', $html);
    }

    public function testAnActionWithNoRefreshCarriesNoAttribute(): void
    {
        // Its absence is the signal, and the signal has to be absent rather
        // than empty: `data-ra-refresh=""` would split to no keys and behave
        // the same, but it would read as a feature somebody had switched off
        // rather than one they never asked for.
        $html = $this->render($this->action());

        $this->assertStringNotContainsString('data-ra-refresh', $html);
    }

    public function testTheFormStillPostsNormallyWithTheAttributeOn(): void
    {
        // The attribute is a signal to a script, never a requirement. With no
        // script the form is what it always was: a POST to the action route
        // carrying the token and the row, which redirects.
        $html = $this->render($this->action(refresh: ['grid']));

        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('action="/admin/a/ads/archive"', $html);
        $this->assertStringContainsString('name="_csrf"', $html);
        $this->assertStringContainsString('name="_id"', $html);
    }

    public function testALinkNeverCarriesIt(): void
    {
        // A link navigates; there is nothing to refresh afterwards because
        // the page it refreshed would be gone.
        $html = $this->render(new ActionView(
            key: 'edit',
            label: 'Edit',
            url: '/admin/p/ads/7/edit',
            refresh: ['grid'],
        ));

        $this->assertStringNotContainsString('data-ra-refresh', $html);
    }

    /** @param list<string> $refresh */
    private function action(array $refresh = []): ActionView
    {
        return new ActionView(
            key: 'archive',
            label: 'Archive',
            url: '/admin/a/ads/archive',
            isPost: true,
            confirm: 'Archive this ad?',
            token: 'the-token',
            rowKey: '7',
            refresh: $refresh,
        );
    }

    private function render(ActionView $view): string
    {
        $renderer = new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/admin'));

        return $renderer->render('ui/action', $view);
    }
}
