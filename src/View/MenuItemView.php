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
        /**
         * A count drawn beside the label — 14 orders waiting, 3 products
         * unpublished. Null draws nothing, which is different from zero:
         * zero is a fact worth showing on a queue that is usually not empty,
         * and a menu that cannot tell them apart would hide it.
         */
        public readonly ?int $count = null,
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

        // No appearance class: the design system names a navigation link by
        // where it sits -- `ra-snav-btn` in the bar, `ra-snav-dd__item` in a
        // dropdown -- and only the template knows which. Bootstrap's
        // `nav-link` used to be appended here and is not replaced by anything,
        // because there is nothing to replace it with that would be right in
        // both places.
        return implode(' ', $names);
    }
}
