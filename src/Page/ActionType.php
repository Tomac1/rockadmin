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
 * All three are buildable as of milestone 9. `Open` was declared here and
 * refused for two milestones, with a message naming the one that would build
 * it — because a silent "unknown type" for something the reference manual
 * describes at length is worse than saying "not yet": the person wrote a
 * documented thing, and the admin should tell them when it will work rather
 * than pretend they made it up.
 */
enum ActionType: string
{
    case Link = 'link';
    case Post = 'post';
    case Open = 'open';

    /** @throws PageException when the value names no type */
    public static function parse(string $value): self
    {
        $type = self::tryFrom($value);

        if ($type !== null) {
            return $type;
        }

        $names = self::buildableNames();
        $nearest = Schema::nearestOf($names, $value);

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

    /** Whether activating this shows a region in an overlay rather than navigating or writing. */
    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    /**
     * Which key says where this type goes, and the reference namespace it
     * must name.
     *
     * A `link` navigates, so it points at a page through `to`; a `post`
     * submits, so it points at an action handler through `to`. The other way
     * round is a GET to something that only answers POST, or a POST to a page
     * that has no handler — both invisible at load and a 404 at click time.
     *
     * An `open` is the odd one and the specification names it differently on
     * purpose: it has a `target`, not a `to`, because it does not go anywhere.
     * The page stays where it is and a region appears over it.
     *
     * @return array{0: string, 1: string} the configuration key, the namespace it must name
     */
    public function destination(): array
    {
        return match ($this) {
            self::Link => ['to', 'page'],
            self::Post => ['to', 'action'],
            self::Open => ['target', 'region'],
        };
    }

    /** The reference namespace this type's destination must name. */
    public function referenceNamespace(): string
    {
        return $this->destination()[1];
    }

    /** The configuration key this type's destination is written under. */
    public function destinationKey(): string
    {
        return $this->destination()[0];
    }

    /**
     * Every type a page file may use.
     *
     * @return list<string>
     */
    public static function buildableNames(): array
    {
        return array_values(array_map(static fn (self $case): string => $case->value, self::cases()));
    }
}
