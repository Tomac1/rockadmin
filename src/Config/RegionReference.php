<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * A reference to a region, which an `open` action displays in an overlay:
 * `@region:detail`, `@region:users.detail` (specification 8.5).
 *
 * The dot is what makes the two forms one syntax. `@region:detail` is a
 * region of the current page, so `$page` is null and whoever builds the URL
 * supplies it — exactly as `ActionReference` works, and for the same reason:
 * most references are to the page you are already on, and repeating its name
 * in every action of every page file is how a page rename becomes a day of
 * search-and-replace.
 *
 * `@region:users.detail` reads as *page `users`, region `detail`* — the part
 * before the dot is a page file's name without `.php`, the part after it is a
 * key in that page's `regions` block. That form is what makes 8.5's "a page
 * may be reachable by URL and absent from the menu" sound: the panel loaded
 * into a modal is a region of a real page, served by the ordinary
 * `/r/{page}/{region}` route, and governed by that page's own permissions.
 * There is no special-cased fragment anywhere and nothing to permission
 * separately.
 *
 * Like its siblings, this constructor does not validate. The syntax is
 * `Resolver`'s, because that is the only place the text came from
 * configuration; whether the page and the region exist is `PageRepository`'s,
 * because that is where the catalogue is.
 */
final class RegionReference
{
    /**
     * @param string  $region the key in that page's `regions` block
     * @param ?string $page   the page file's name without `.php`, or null for the current page
     */
    public function __construct(
        public readonly string $region,
        public readonly ?string $page = null,
    ) {
    }

    public function __toString(): string
    {
        return '@region:' . ($this->page === null ? '' : $this->page . '.') . $this->region;
    }

    /** @param array{region: string, page: ?string} $data written by var_export() */
    public static function __set_state(array $data): self
    {
        return new self($data['region'], $data['page']);
    }
}
