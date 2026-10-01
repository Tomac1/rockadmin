<?php

declare(strict_types=1);

namespace RockAdmin\View;

use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\Gate;
use RockAdmin\Auth\Identity;
use RockAdmin\Page\PageRepository;

/**
 * The navigation, as this particular reader may see it.
 *
 * Two things happen here, and they used to happen three times — `PageHandler`,
 * `DetailHandler` and `FormHandler` each carried their own copy of "mark the
 * current one active". Three copies of a rule is how a menu comes to highlight
 * the wrong item on one page in four, so the rule lives here and the handlers
 * hold one of these instead of a list of items.
 *
 * **A page the reader may not view is not drawn** (spec 9.2): a visible link
 * that answers 403 is a bug, not a safeguard, and the answer comes from the
 * same `Gate::allows()` the kernel calls at step 7, never from a second rule
 * of its own.
 *
 * **What an item needs is derived, not declared.** An item whose key names a
 * page needs that page's `view` permission, because that is precisely what
 * `Gate::ROUTES` asks of `page.index` — writing the permission into each
 * `MenuItemView` by hand would be a second list to keep in step with the
 * first, and the pages already generate their own permission names.
 *
 * **An item that names no page needs a signed-in reader and nothing more.**
 * The items this admin ships that are not pages — the dashboard, logging out —
 * are `RouteAccess::Authenticated` in the gate's own table, and they can hold
 * no `page.action` at all: the catalogue is generated from the pages, so an
 * invented permission name would be denied to everybody, including a role
 * holding `*`. So "signed in" is the gate's rule for them, read here rather
 * than guessed at, and an anonymous reader is drawn no navigation whatsoever.
 */
final class Menu
{
    /** @param list<MenuItemView> $items as the host declared them, in order */
    public function __construct(
        private readonly array $items,
        private readonly Gate $gate,
        /**
         * Resolved per render, not per wiring: the menu is built once and the
         * reader belongs to the request. `Identity` memoises the lookup, so
         * this costs no query the kernel has not already paid for.
         */
        private readonly Identity $identity,
        /**
         * Which keys name pages. Nothing else can answer it: a menu item
         * carries a label and a URL, and a URL is not something to infer a
         * permission from.
         */
        private readonly PageRepository $pages,
    ) {
    }

    /**
     * The items this reader may see, with the one for `$currentPage` marked
     * active.
     *
     * @return list<MenuItemView>
     */
    public function forPage(string $currentPage): array
    {
        return $this->visible($this->items, $this->identity->user(), $currentPage);
    }

    /**
     * @param  list<MenuItemView>  $items
     * @return list<MenuItemView>
     */
    private function visible(array $items, ?AuthUser $user, string $currentPage): array
    {
        $visible = [];

        foreach ($items as $item) {
            if (!$this->maySee($user, $item)) {
                continue;
            }

            $visible[] = new MenuItemView(
                $item->label,
                $item->url,
                $item->icon,
                active: $item->key === $currentPage,
                // Children are filtered by the same rule: a submenu is a menu.
                // A parent whose children are all forbidden is still drawn,
                // because the parent is a link in its own right and its own
                // permission already decided it.
                children: $this->visible($item->children, $user, $currentPage),
                key: $item->key,
            );
        }

        return $visible;
    }

    private function maySee(?AuthUser $user, MenuItemView $item): bool
    {
        if ($item->key === null || !$this->pages->has($item->key)) {
            return $user !== null;
        }

        return $this->gate->allows($user, $item->key . '.view');
    }
}
