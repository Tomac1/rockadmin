<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\View\ButtonView;
use RockAdmin\View\FlashView;
use RockAdmin\View\MenuItemView;
use RockAdmin\View\PageView;
use RockAdmin\View\ShellView;
use RockAdmin\View\ViewException;

#[CoversClass(ButtonView::class)]
#[CoversClass(FlashView::class)]
#[CoversClass(MenuItemView::class)]
#[CoversClass(PageView::class)]
#[CoversClass(ShellView::class)]
final class ViewObjectsTest extends TestCase
{
    private function shell(): ShellView
    {
        return new ShellView('RockAdmin');
    }

    public function testAPageCarriesItsIdentityIntoTheBodyClasses(): void
    {
        $page = new PageView('users', 'Users', $this->shell(), type: 'list');

        $this->assertSame('ra-page ra-page-users ra-page-type-list', $page->bodyClasses());
    }

    public function testASlotReturnsWhatWasRenderedIntoIt(): void
    {
        $page = new PageView('users', 'Users', $this->shell(), slots: ['main' => '<div>grid</div>']);

        $this->assertTrue($page->hasSlot('main'));
        $this->assertSame('<div>grid</div>', $page->slot('main'));
    }

    public function testAnAbsentSlotRendersAsNothing(): void
    {
        // A two-column layout used with one region should leave the other
        // column empty rather than fail: layouts do not know their regions.
        $page = new PageView('users', 'Users', $this->shell());

        $this->assertFalse($page->hasSlot('side'));
        $this->assertSame('', $page->slot('side'));
    }

    public function testAButtonCarriesStructuralAndIdentityClasses(): void
    {
        $button = new ButtonView('create', 'New ad', '/admin/p/ads/create', style: 'primary');

        $this->assertSame('ra-btn ra-btn-create btn btn-primary', $button->classes());
    }

    public function testAMenuItemKnowsWhetherItIsActive(): void
    {
        $plain = new MenuItemView('Ads', '/admin/p/ads');
        $active = new MenuItemView('Ads', '/admin/p/ads', active: true);

        $this->assertSame('ra-menu-item nav-link', $plain->classes());
        $this->assertSame('ra-menu-item ra-menu-item-active nav-link active', $active->classes());
    }

    /** @return array<string, array{string, string}> */
    public static function flashLevels(): array
    {
        return [
            'success' => ['success', 'ra-flash ra-flash-success text-bg-success'],
            'info' => ['info', 'ra-flash ra-flash-info text-bg-info'],
            'warning' => ['warning', 'ra-flash ra-flash-warning text-bg-warning'],
            'danger' => ['danger', 'ra-flash ra-flash-danger text-bg-danger'],
        ];
    }

    #[DataProvider('flashLevels')]
    public function testAFlashMapsItsLevelToClasses(string $level, string $expected): void
    {
        $this->assertSame($expected, (new FlashView($level, 'Saved.'))->classes());
    }

    public function testAnUnknownFlashLevelIsRefused(): void
    {
        // Silently rendering an unstyled toast is how a warning ends up
        // looking like a confirmation.
        $this->expectException(ViewException::class);

        new FlashView('purple', 'Saved.');
    }

    /** @return array<string, array{string, ?string}> */
    public static function darkModes(): array
    {
        return [
            'auto follows the operating system' => ['auto', null],
            'on' => ['on', 'dark'],
            'off' => ['off', 'light'],
        ];
    }

    #[DataProvider('darkModes')]
    public function testDarkModeBecomesBootstrapsThemeAttribute(string $mode, ?string $expected): void
    {
        // Bootstrap 5.3 reads data-bs-theme. 'auto' writes no attribute,
        // leaving the media query in rockadmin.css to decide.
        $this->assertSame($expected, (new ShellView('X', darkMode: $mode))->themeAttribute());
    }

    public function testAnUnknownDarkModeIsRefused(): void
    {
        $this->expectException(ViewException::class);

        new ShellView('X', darkMode: 'sometimes');
    }
}
