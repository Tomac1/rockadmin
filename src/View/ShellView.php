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
        /**
         * The CSRF token the logout form posts, or null when the shell draws
         * no logout form.
         *
         * `LogoutHandler` refuses a post without one, so a shell that carries
         * a `logoutUrl` and no token draws a button that cannot work. Milestone
         * 5 added the check; this is the other half of it, and it is on the
         * shell rather than in the template because a template may not reach
         * for a `Csrf` of its own.
         */
        public readonly ?string $csrfToken = null,
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
