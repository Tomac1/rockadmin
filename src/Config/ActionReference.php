<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * A reference to an action handler on the current page: `@action:archive`
 * (specification 8.5).
 *
 * It carries the verb and nothing else. 8.5 addresses an action on *the
 * current page* — `/a/<current>/archive` — so the page is not part of the
 * reference, and whoever builds the URL supplies it. Letting a reference name
 * another page's action would be a second, undocumented spelling of a thing
 * the specification does not have, and the gate reasons about a verb on a
 * page rather than about a verb on its own.
 */
final class ActionReference
{
    public function __construct(public readonly string $action)
    {
    }

    public function __toString(): string
    {
        return '@action:' . $this->action;
    }

    /** @param array{action: string} $data written by var_export() */
    public static function __set_state(array $data): self
    {
        return new self($data['action']);
    }
}
