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
        /**
         * The workspaces this reader may switch between, or none.
         *
         * The design system draws a switcher in the top bar and milestone 9
         * builds what it switches; until then nothing supplies a list and the
         * control is not drawn. Empty rather than null because "no
         * workspaces" and "workspaces not built yet" render identically and
         * always will — a shell has nothing to say about the difference.
         *
         * @var list<WorkspaceView>
         */
        public readonly array $workspaces = [],
    ) {
        if (!\in_array($this->darkMode, self::ALLOWED_DARK_MODES, true)) {
            throw new ViewException(
                "Dark mode '{$this->darkMode}' not allowed; must be one of: " . implode(', ', self::ALLOWED_DARK_MODES) . '.',
            );
        }
    }

    /**
     * The value of the document's `data-ra-theme`, or null for `auto`.
     *
     * It used to be spelled `data-bs-theme`, which was Bootstrap's own
     * switch and worked because Bootstrap shipped a dark palette. The design
     * system ships one palette and no dark rules at all, so today this
     * attribute styles nothing by itself -- it is the hook a project's own
     * stylesheet targets, and the place a dark palette would attach if one
     * is ever written.
     *
     * Kept rather than deleted because the configuration key is public and
     * what it means has not changed: the admin follows the operating system,
     * or it does not. What changed is who answers.
     */
    public function themeAttribute(): ?string
    {
        return match ($this->darkMode) {
            'auto' => null,
            'on' => 'dark',
            'off' => 'light',
        };
    }
}
