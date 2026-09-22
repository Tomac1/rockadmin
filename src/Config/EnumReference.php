<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * A reference to a shared enumeration, resolved when its options are needed.
 *
 * Static enumerations could be inlined here, but database-backed ones cannot,
 * and one shape for both is worth more than saving a lookup.
 */
final class EnumReference
{
    public function __construct(public readonly string $key)
    {
    }

    public function __toString(): string
    {
        return "@enum:{$this->key}";
    }

    /** @param array{key: string} $data written by var_export() */
    public static function __set_state(array $data): self
    {
        return new self($data['key']);
    }
}
