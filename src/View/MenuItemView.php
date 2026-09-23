<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * A navigation menu item.
 *
 * Carries a label, URL, optional icon, active state, and child items for
 * hierarchical menus. An optional key names which one it is — milestone 5
 * writes the real menu against this, the way PageView and ButtonView already
 * carry an identity class for their own key.
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
        public readonly ?string $key = null,
    ) {
    }

    public function classes(): string
    {
        // 'active' is state, not identity, so it is not something Classes::of()
        // has a slot for — it does not vary per menu item the way a key does,
        // it varies per request. The structural and identity classes still go
        // through Classes so a malformed key fails the same way a malformed
        // page or button key does, rather than being written into a class
        // attribute unchecked.
        $names = [Classes::of('menu-item', $this->key)];

        if ($this->active) {
            $names[] = Classes::identity('menu-item', 'active');
        }

        $names[] = 'nav-link';

        if ($this->active) {
            $names[] = 'active';
        }

        return implode(' ', $names);
    }
}
