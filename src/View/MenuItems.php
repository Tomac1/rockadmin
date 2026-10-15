<?php

declare(strict_types=1);

namespace RockAdmin\View;

use RockAdmin\Config\ConfigException;
use RockAdmin\Http\UrlGenerator;

/**
 * The navigation, read out of `menu` in rockadmin.php.
 *
 * It was a list of `MenuItemView` built in PHP until the bootstrap existed,
 * which meant every project wrote its navigation in its composition root and
 * no project could be started without one. The items are data — a label, a
 * destination and an order — so they belong in the file that is kept in git
 * with the pages.
 *
 * **The key is the item's identity and its permission.** An entry keyed `ads`
 * links to the `ads` page, is hidden from anybody the gate would refuse
 * `ads.view`, and is marked active while that page is open. That is `Menu`'s
 * existing rule and this does not change it; it only means a key is worth
 * getting right.
 *
 * Three ways to say where an item goes, in the order they are looked at:
 * `route` for one of the admin's own named routes, `url` for anything else,
 * and nothing at all for the common case — an item keyed after a page links
 * to that page.
 */
final class MenuItems
{
    /**
     * @param  array<string, mixed> $menu the `menu` block, already validated against the schema
     * @return list<MenuItemView>
     */
    public static function fromConfig(array $menu, UrlGenerator $urls): array
    {
        $items = [];

        foreach ($menu as $key => $entry) {
            if (!\is_array($entry)) {
                throw new ConfigException("Menu item '{$key}' must be an array.");
            }

            /** @var array<string, mixed> $entry narrows array<mixed, mixed> */
            $items[] = self::item((string) $key, $entry, $urls);
        }

        return $items;
    }

    /** @param array<string, mixed> $entry */
    private static function item(string $key, array $entry, UrlGenerator $urls): MenuItemView
    {
        $children = $entry['children'] ?? [];
        $childItems = [];

        if (\is_array($children)) {
            /** @var array<string, mixed> $children narrows array<mixed, mixed> */
            $childItems = self::fromConfig($children, $urls);
        }

        return new MenuItemView(
            self::text($entry, 'label') ?? $key,
            self::url($key, $entry, $urls),
            icon: self::text($entry, 'icon'),
            children: $childItems,
            key: $key,
        );
    }

    /** @param array<string, mixed> $entry */
    private static function url(string $key, array $entry, UrlGenerator $urls): string
    {
        $route = self::text($entry, 'route');

        if ($route !== null) {
            return $urls->route($route);
        }

        $url = self::text($entry, 'url');

        if ($url !== null) {
            // Taken as written, including an absolute one: a menu is also
            // where a link out of the admin belongs -- the public site, a
            // dashboard somebody else runs -- and rewriting those would be
            // this class deciding something the project already decided.
            return $url;
        }

        // The common case, and the reason most entries are one line: the key
        // names a page, and a page is at `p/{name}`.
        return $urls->to('p/' . $key);
    }

    /** @param array<string, mixed> $entry */
    private static function text(array $entry, string $name): ?string
    {
        $value = $entry[$name] ?? null;

        return \is_string($value) && $value !== '' ? $value : null;
    }
}
