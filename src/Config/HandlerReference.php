<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * A reference to a project's own write logic: `@handler:save_ad`.
 *
 * The fifth reference namespace, and the only one that points outside the
 * configuration entirely — `@page:`, `@region:` and `@action:` all name
 * something another file declares, and `@enum:` names a list. This names an
 * object the host wired up.
 *
 * **It is in the configuration, not only in the wiring, on purpose.** A page
 * file saying `'handler' => '@handler:save_ad'` is a page file somebody can
 * read and know that saving is not the default. Registration alone would
 * make it tell half the truth, and the whole argument for describing an
 * admin in configuration is that the configuration is the thing you read to
 * know what happens.
 *
 * Like its siblings this constructor does not validate. The syntax is
 * `Resolver`'s; whether anything is registered under the name is `Operations`'
 * business, because that is where the catalogue is — the same split `@page:`
 * and a role's permissions already follow.
 */
final class HandlerReference
{
    public function __construct(public readonly string $name)
    {
    }

    public function __toString(): string
    {
        return '@handler:' . $this->name;
    }

    /** @param array{name: string} $data written by var_export() */
    public static function __set_state(array $data): self
    {
        return new self($data['name']);
    }
}
