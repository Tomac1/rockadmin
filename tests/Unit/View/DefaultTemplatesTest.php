<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\View\ButtonView;
use RockAdmin\View\Escaper;
use RockAdmin\View\FlashView;
use RockAdmin\View\MenuItemView;
use RockAdmin\View\PageView;
use RockAdmin\View\Renderer;
use RockAdmin\View\ShellView;
use RockAdmin\View\TemplateResolver;

/**
 * The default templates, rendered as they ship. This test is about the
 * promises the specification makes — identity classes, escaping, slot
 * behaviour, dark mode — not about markup, so it never asserts on whitespace
 * or on an exact tag. A styling change should not break it; a broken promise
 * should.
 */
#[CoversNothing]
final class DefaultTemplatesTest extends TestCase
{
    private function renderer(): Renderer
    {
        return new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/admin'));
    }

    /**
     * @param array<string, string> $slots
     * @param list<ButtonView>      $buttons
     */
    private function page(
        string $key = 'users',
        array $slots = ['main' => '<div class="ra-region">grid</div>'],
        ?ShellView $shell = null,
        array $buttons = [],
        string $description = '',
    ): PageView {
        return new PageView(
            $key,
            'Users',
            $shell ?? new ShellView('RockAdmin'),
            description: $description,
            buttons: $buttons,
            slots: $slots,
        );
    }

    public function testTheBaseLayoutWritesADocumentAroundItsMainSlot(): void
    {
        $html = $this->renderer()->render('layout/base', $this->page());

        $this->assertStringContainsString('<!doctype html>', strtolower($html));
        $this->assertStringContainsString('<div class="ra-region">grid</div>', $html);
    }

    public function testTheBodyCarriesThePageIdentityClasses(): void
    {
        // .ra-page-users .ra-grid-cell { } has to work without every element
        // carrying an identity class of its own.
        $html = $this->renderer()->render('layout/base', $this->page());

        $this->assertStringContainsString('ra-page ra-page-users ra-page-type-list', $html);
    }

    public function testTheBrandIsEscaped(): void
    {
        $html = $this->renderer()->render(
            'layout/base',
            $this->page(shell: new ShellView('<b>Brand</b>')),
        );

        $this->assertStringContainsString('&lt;b&gt;Brand&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Brand</b>', $html);
    }

    public function testStylesheetsAppearInTheOrderAssetsGaveThem(): void
    {
        // Project CSS loads last so it overrides without !important.
        $shell = new ShellView('RockAdmin', styles: ['/a.css', '/b.css'], scripts: ['/c.js']);

        $html = $this->renderer()->render('layout/base', $this->page(shell: $shell));

        $this->assertLessThan(strpos($html, '/b.css'), (int) strpos($html, '/a.css'));
        $this->assertStringContainsString('/c.js', $html);
    }

    public function testDarkModeOnWritesBootstrapsThemeAttribute(): void
    {
        $html = $this->renderer()->render(
            'layout/base',
            $this->page(shell: new ShellView('RockAdmin', darkMode: 'on')),
        );

        $this->assertStringContainsString('data-bs-theme="dark"', $html);
    }

    public function testDarkModeAutoLeavesTheDecisionToTheStylesheet(): void
    {
        $html = $this->renderer()->render('layout/base', $this->page());

        $this->assertStringNotContainsString('data-bs-theme', $html);
    }

    public function testTheMenuMarksTheActiveItem(): void
    {
        $shell = new ShellView('RockAdmin', menu: [
            new MenuItemView('Ads', '/admin/p/ads'),
            new MenuItemView('Users', '/admin/p/users', active: true),
        ]);

        $html = $this->renderer()->render('layout/base', $this->page(shell: $shell));

        $this->assertStringContainsString('ra-menu-item-active', $html);
        $this->assertSame(1, substr_count($html, 'ra-menu-item-active'));
    }

    public function testAFlashRendersAsAToast(): void
    {
        $shell = new ShellView('RockAdmin', flashes: [new FlashView('success', 'Saved.')]);

        $html = $this->renderer()->render('layout/base', $this->page(shell: $shell));

        $this->assertStringContainsString('ra-flash ra-flash-success', $html);
        $this->assertStringContainsString('Saved.', $html);

        // Bootstrap 5.3 ships `.toast:not(.show){display:none}`. Without the
        // `show` class the flash is in the DOM but invisible; asserting the
        // classes and the message text alone, as this test did before, is
        // also true of markup nobody can see.
        if (preg_match('/class="([^"]*)"\s+role="status"[^>]*data-ra-toast/', $html, $match) !== 1) {
            $this->fail('No element carrying the toast class was rendered.');
        }

        $classes = explode(' ', $match[1]);
        $this->assertContains('show', $classes, 'The toast is missing the "show" class and is therefore invisible.');
    }

    public function testNoFlashesLeaveTheToastContainerEmptyRatherThanAbsent(): void
    {
        // The container is a fixed insertion point for fragments inserted
        // later (milestone 6): it must exist on every page, flash or not.
        $html = $this->renderer()->render('layout/base', $this->page());

        $this->assertStringContainsString('ra-toast-container', $html);
        $this->assertStringNotContainsString('ra-flash', $html);
    }

    public function testTwoColumnsToleratesAMissingSlot(): void
    {
        // Layouts do not know their regions, so a layout used with one region
        // renders an empty column rather than failing.
        $html = $this->renderer()->render('layout/two-column', $this->page(slots: ['main' => 'left']));

        $this->assertStringContainsString('left', $html);
        $this->assertStringContainsString('ra-layout-two-column', $html);
    }

    public function testTheSingleLayoutRendersItsMainSlotAndNothingElse(): void
    {
        $html = $this->renderer()->render('layout/single', $this->page(slots: ['main' => 'only content']));

        $this->assertStringContainsString('only content', $html);
        $this->assertStringContainsString('ra-layout-single', $html);
    }

    public function testTheSidebarDetailLayoutRendersListAndDetail(): void
    {
        $html = $this->renderer()->render(
            'layout/sidebar-detail',
            $this->page(slots: ['list' => 'the list', 'detail' => 'the detail']),
        );

        $this->assertStringContainsString('the list', $html);
        $this->assertStringContainsString('the detail', $html);
        $this->assertStringContainsString('ra-layout-sidebar-detail', $html);
    }

    public function testTheSidebarDetailLayoutToleratesAMissingDetailSlot(): void
    {
        // Before anything is selected, there is no detail to show yet.
        $html = $this->renderer()->render('layout/sidebar-detail', $this->page(slots: ['list' => 'the list']));

        $this->assertStringContainsString('the list', $html);
        $this->assertStringContainsString('ra-layout-sidebar-detail', $html);
    }

    public function testThePageHeaderRendersOneButtonPerButton(): void
    {
        $page = $this->page(buttons: [
            new ButtonView('create', 'New', '/admin/p/users/create', style: 'primary'),
            new ButtonView('export', 'Export', '/admin/p/users/export'),
        ]);

        $html = $this->renderer()->render('page/header', $page);

        $this->assertStringContainsString('ra-btn-create', $html);
        $this->assertStringContainsString('ra-btn-export', $html);
    }

    public function testThePageHeaderOmitsTheDescriptionWhenItIsEmpty(): void
    {
        // An empty <p> in the header pushes the grid down on every page that
        // has no description, which is most of them.
        $html = $this->renderer()->render('page/header', $this->page());

        $this->assertStringNotContainsString('ra-page-description', $html);
    }

    public function testThePageHeaderShowsTheDescriptionWhenItIsGiven(): void
    {
        $html = $this->renderer()->render('page/header', $this->page(description: 'Manage the users.'));

        $this->assertStringContainsString('ra-page-description', $html);
        $this->assertStringContainsString('Manage the users.', $html);
    }

    public function testError403NamesItsStatus(): void
    {
        $html = $this->renderer()->render('error/403', $this->errorView(403, 'Forbidden'));

        $this->assertStringContainsString('403', $html);
        $this->assertStringContainsString('Forbidden', $html);
        $this->assertStringContainsString('ra-error-403', $html);
    }

    public function testError404NamesItsStatus(): void
    {
        $html = $this->renderer()->render('error/404', $this->errorView(404, 'Not found'));

        $this->assertStringContainsString('404', $html);
        $this->assertStringContainsString('Not found', $html);
        $this->assertStringContainsString('ra-error-404', $html);
    }

    public function testError500NamesItsStatus(): void
    {
        $html = $this->renderer()->render('error/500', $this->errorView(500, 'Something went wrong'));

        $this->assertStringContainsString('500', $html);
        $this->assertStringContainsString('Something went wrong', $html);
        $this->assertStringContainsString('ra-error-500', $html);
    }

    public function testError500ShowsTheDetailOnlyInDebug(): void
    {
        $withoutDebug = $this->renderer()->render('error/500', $this->errorView(500, 'Something went wrong'));
        $withDebug = $this->renderer()->render('error/500', $this->errorView(
            500,
            'Something went wrong',
            debug: true,
            exceptionClass: 'LogicException',
            exceptionMessage: 'boom',
        ));

        $this->assertStringNotContainsString('LogicException', $withoutDebug);
        $this->assertStringContainsString('LogicException', $withDebug);
        $this->assertStringContainsString('boom', $withDebug);
    }

    public function testConfigErrorNamesTheProblem(): void
    {
        $html = $this->renderer()->render('error/config-error', ['message' => 'Unknown key "foo.bar" in rockadmin.php']);

        $this->assertStringContainsString('Configuration error', $html);
        $this->assertStringContainsString('Unknown key &quot;foo.bar&quot; in rockadmin.php', $html);
    }

    /** @return array{status: int, title: string, debug: bool, exceptionClass: ?string, exceptionMessage: ?string, file: ?string, line: ?int} */
    private function errorView(
        int $status,
        string $title,
        bool $debug = false,
        ?string $exceptionClass = null,
        ?string $exceptionMessage = null,
    ): array {
        return [
            'status' => $status,
            'title' => $title,
            'debug' => $debug,
            'exceptionClass' => $exceptionClass,
            'exceptionMessage' => $exceptionMessage,
            'file' => $debug ? __FILE__ : null,
            'line' => $debug ? __LINE__ : null,
        ];
    }

    public function testTheShellCarriesOneEmptyModalAndOneEmptyOffcanvas(): void
    {
        // Regions do not carry their own overlay markup: there is one of each
        // in the document, and core.js fills the right one from a fragment's
        // root attributes. That is what lets an action open any region
        // anywhere, and milestone 6 depends on these containers existing.
        $html = $this->renderer()->render('layout/base', $this->page());

        $this->assertStringContainsString('id="ra-modal"', $html);
        $this->assertStringContainsString('id="ra-offcanvas"', $html);

        $this->assertSame(1, substr_count($html, 'id="ra-modal"'));
        $this->assertSame(1, substr_count($html, 'id="ra-offcanvas"'));

        // Empty, because their contents arrive as a fragment.
        $this->assertStringContainsString('<div class="ra-modal-content modal-content"></div>', $html);
        $this->assertStringContainsString('<div class="ra-offcanvas-body offcanvas-body"></div>', $html);
    }
}
