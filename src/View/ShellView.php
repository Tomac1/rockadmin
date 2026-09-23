<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * The outer wrapper for an admin page.
 *
 * Provides branding, navigation menu, flash messages, stylesheets, scripts,
 * and user authentication controls. The dark mode setting controls the
 * Bootstrap theme attribute.
 */
final class ShellView
{
    private const ALLOWED_DARK_MODES = ['auto', 'on', 'off'];

    /**
     * @param list<MenuItemView> $menu
     * @param list<FlashView>    $flashes
     * @param list<string>       $styles  stylesheet URLs, in order
     * @param list<string>       $scripts script URLs, in order
     */
    public function __construct(
        public readonly string $brand,
        public readonly array $menu = [],
        public readonly array $flashes = [],
        public readonly array $styles = [],
        public readonly array $scripts = [],
        public readonly ?string $userName = null,
        public readonly ?string $profileUrl = null,
        public readonly ?string $logoutUrl = null,
        public readonly string $darkMode = 'auto',
    ) {
        if (!\in_array($this->darkMode, self::ALLOWED_DARK_MODES, true)) {
            throw new ViewException(
                "Dark mode '{$this->darkMode}' not allowed; must be one of: " . implode(', ', self::ALLOWED_DARK_MODES) . '.',
            );
        }
    }

    public function themeAttribute(): ?string
    {
        return match ($this->darkMode) {
            'auto' => null,
            'on' => 'dark',
            'off' => 'light',
        };
    }
}
