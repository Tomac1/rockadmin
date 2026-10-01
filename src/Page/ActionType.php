<?php

declare(strict_types=1);

namespace RockAdmin\Page;

use RockAdmin\Config\Schema;

/**
 * What an action does when it is activated.
 *
 * A closed set, for the reason `ColumnType` is one: a type nobody declared is
 * a typo rather than a feature. The parsing lives in parse() instead of the
 * enum's own from(), which cannot be redeclared and whose \ValueError names
 * no alternatives.
 *
 * `Open` is declared here and refused by parse(). Specification 8.5 documents
 * it at length — it loads a region into a modal or an offcanvas — and a
 * silent "unknown type" for something the reference manual describes is worse
 * than saying "not yet": the person wrote a documented thing, and the admin
 * should tell them when it will work rather than pretend they made it up.
 */
enum ActionType: string
{
    case Link = 'link';
    case Post = 'post';
    case Open = 'open';

    /** @throws PageException when the value names no type, or names `open` */
    public static function parse(string $value): self
    {
        $type = self::tryFrom($value);

        if ($type === self::Open) {
            throw new PageException(
                "The action type 'open' loads a region into a modal or an offcanvas. It is in the "
                . 'specification and it is not built yet: it needs focus management, escape handling and '
                . "`@region:` addressing, which arrive in a later milestone. Until then an action is 'link' "
                . "or 'post'.",
            );
        }

        if ($type !== null) {
            return $type;
        }

        $names = self::buildableNames();
        $nearest = Schema::nearestOf([...$names, self::Open->value], $value);

        if ($nearest !== null) {
            throw new PageException("Unknown action type '{$value}'. Did you mean '{$nearest}'?");
        }

        throw new PageException(
            "Unknown action type '{$value}'. The types are: '" . implode("', '", $names) . "'.",
        );
    }

    /** Whether activating this changes data, and so must be confirmed and must POST. */
    public function isWrite(): bool
    {
        return $this === self::Post;
    }

    /**
     * The reference namespace this type's `to` must name.
     *
     * A `link` navigates, so it points at a page; a `post` submits, so it
     * points at an action handler. The other way round is a GET to something
     * that only answers POST, or a POST to a page that has no handler — both
     * invisible at load and a 404 at click time.
     */
    public function referenceNamespace(): string
    {
        return match ($this) {
            self::Link => 'page',
            self::Post => 'action',
            self::Open => 'region',
        };
    }

    /**
     * The types a page file may actually use today.
     *
     * @return list<string>
     */
    public static function buildableNames(): array
    {
        return array_values(array_map(
            static fn (self $case): string => $case->value,
            array_filter(self::cases(), static fn (self $case): bool => $case !== self::Open),
        ));
    }
}
