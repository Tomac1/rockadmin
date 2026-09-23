<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * A navigation menu item.
 *
 * Carries a label, URL, optional icon, active state, and child items for
 * hierarchical menus.
 */
final class MenuItemView
{
    /** @param list<MenuItemView> $children */
    public function __construct(
        public readonly string $label,
        public readonly string $url,
        public readonly ?string $icon = null,
        public readonly bool $active = false,
        public readonly array $children = [],
    ) {
    }

    public function classes(): string
    {
        if ($this->active) {
            return 'ra-menu-item ra-menu-item-active nav-link active';
        }

        return 'ra-menu-item nav-link';
    }
}
