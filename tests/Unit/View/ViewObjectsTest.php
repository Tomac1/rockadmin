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

    /** @return array<string, array{string, string, string}> */
    public static function pageIdentities(): array
    {
        return [
            'a list page' => ['users', 'list', 'ra-page ra-page-users ra-page-type-list'],
            'a form page' => ['ads', 'form', 'ra-page ra-page-ads ra-page-type-form'],
            'a dashboard' => ['dashboard', 'stat', 'ra-page ra-page-dashboard ra-page-type-stat'],
        ];
    }

    #[DataProvider('pageIdentities')]
    public function testAPageCarriesItsIdentityIntoTheBodyClasses(
        string $key,
        string $type,
        string $expected,
    ): void {
        // Both halves vary, because a body class built from one of them and a
        // hardcoded other would pass any single case.
        $page = new PageView($key, 'Title', $this->shell(), type: $type);

        $this->assertSame($expected, $page->bodyClasses());
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

    /** @return array<string, array{string, string, string}> */
    public static function buttonIdentities(): array
    {
        return [
            'create, primary' => ['create', 'primary', 'ra-btn ra-btn-create btn btn-primary'],
            'export, secondary' => ['export', 'secondary', 'ra-btn ra-btn-export btn btn-secondary'],
            'delete, danger' => ['delete', 'danger', 'ra-btn ra-btn-delete btn btn-danger'],
        ];
    }

    #[DataProvider('buttonIdentities')]
    public function testAButtonCarriesStructuralAndIdentityClasses(
        string $key,
        string $style,
        string $expected,
    ): void {
        $button = new ButtonView($key, 'Label', '/admin/p/ads/x', style: $style);

        $this->assertSame($expected, $button->classes());
    }

    public function testAMenuItemKnowsWhetherItIsActive(): void
    {
        $plain = new MenuItemView('Ads', '/admin/p/ads');
        $active = new MenuItemView('Ads', '/admin/p/ads', active: true);

        $this->assertSame('ra-menu-item nav-link', $plain->classes());
        $this->assertSame('ra-menu-item ra-menu-item-active nav-link active', $active->classes());
    }

    public function testAMenuItemCarriesAnIdentityClassWhenGivenAKey(): void
    {
        // Milestone 5 writes the real menu against this. Without a key
        // (the case above) a menu item carries no identity class, exactly
        // as before.
        $plain = new MenuItemView('Ads', '/admin/p/ads', key: 'ads');
        $active = new MenuItemView('Ads', '/admin/p/ads', active: true, key: 'ads');

        $this->assertSame('ra-menu-item ra-menu-item-ads nav-link', $plain->classes());
        $this->assertSame('ra-menu-item ra-menu-item-ads ra-menu-item-active nav-link active', $active->classes());
    }

    public function testAMenuItemsKeyIsRefusedTheSameWayAnyIdentityIs(): void
    {
        $this->expectException(ViewException::class);

        (new MenuItemView('Ads', '/admin/p/ads', key: 'not valid!'))->classes();
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
